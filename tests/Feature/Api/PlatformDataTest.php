<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Jobs\ExportPlatformData;
use App\Models\Activity;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ExportReady;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PlatformDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_starts_an_export_in_the_background(): void
    {
        Queue::fake();
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->postJson(route('api.data-exports.store'));

        $response->assertAccepted();
        Queue::assertPushed(ExportPlatformData::class, fn (ExportPlatformData $job): bool => $job->requester->is($owner));
    }

    public function test_other_rate_limits_do_not_use_up_the_export_limit(): void
    {
        Queue::fake();
        Mail::fake();
        $owner = User::factory()->owner()->create();

        foreach (range(1, 4) as $attempt) {
            $this->actingAs($owner)->postJson(route('api.settings.test-email'))->assertOk();
        }

        $this->actingAs($owner)->postJson(route('api.data-exports.store'))->assertAccepted();
    }

    public function test_export_zips_every_table_without_secrets_and_tells_the_owner(): void
    {
        Storage::fake('local');
        Notification::fake();
        $owner = User::factory()->owner()->create();
        Order::factory()->count(2)->create();
        app(PlatformSettings::class)->update(['stripe_secret_key' => 'sk_live_topsecret']);

        (new ExportPlatformData($owner))->handle();

        $file = Storage::disk('local')->files('exports')[0];
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($file));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $users = json_decode($zip->getFromName('users.json'), true);
        $settings = $zip->getFromName('settings.json');
        $orders = json_decode($zip->getFromName('orders.json'), true);
        $zip->close();

        $this->assertSame(2, $manifest['rows']['orders']);
        $this->assertCount(2, $orders);
        $this->assertArrayNotHasKey('password', $users[0]);
        $this->assertStringNotContainsString('topsecret', $settings);
        Notification::assertSentTo($owner, ExportReady::class);
        $this->assertSame('exported', Activity::sole()->action);
    }

    public function test_owner_lists_downloads_and_deletes_exports(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('exports/batta-export-2026-10-05-101500.zip', 'zip-bytes');
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->getJson(route('api.data-exports.index'))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'batta-export-2026-10-05-101500.zip')
            ->assertJsonPath('data.0.size', 9);
        $this->actingAs($owner)->get(route('api.data-exports.show', 'batta-export-2026-10-05-101500.zip'))
            ->assertOk()
            ->assertDownload('batta-export-2026-10-05-101500.zip');
        $this->actingAs($owner)->deleteJson(route('api.data-exports.destroy', 'batta-export-2026-10-05-101500.zip'))->assertNoContent();

        Storage::disk('local')->assertMissing('exports/batta-export-2026-10-05-101500.zip');
    }

    public function test_only_export_files_can_be_downloaded(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('conversations/1/private.pdf', 'pdf');

        $this->actingAs(User::factory()->owner()->create())->get('/dashboard/api/v1/data-exports/..%2Fconversations%2F1%2Fprivate.pdf')->assertNotFound();
    }

    public function test_admin_cannot_export_or_wipe(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('api.data-exports.store'))->assertForbidden();
        $this->actingAs($admin)->getJson(route('api.data-exports.index'))->assertForbidden();
        $this->actingAs($admin)->postJson(route('api.data-wipe'), ['password' => 'password', 'confirmation' => 'احذف كل البيانات'])->assertForbidden();
    }

    public function test_wipe_needs_the_password_and_the_typed_sentence(): void
    {
        $owner = User::factory()->owner()->create();
        Course::factory()->create();

        $this->actingAs($owner)->postJson(route('api.data-wipe'), ['password' => 'wrong', 'confirmation' => 'احذف'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password', 'confirmation' => 'اكتب الجملة "احذف كل البيانات" كما هي للتأكيد.']);

        $this->assertSame(1, Course::count());
    }

    public function test_wipe_deletes_business_data_but_keeps_team_settings_and_log(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('courses/cover.png', 'png');
        $owner = User::factory()->owner()->create();
        $editor = User::factory()->role(Role::Editor)->create();
        Order::factory()->count(3)->create();
        Review::factory()->create();
        Lead::factory()->create();
        app(PlatformSettings::class)->update(['site_name' => 'منصتي']);

        $response = $this->actingAs($owner)->postJson(route('api.data-wipe'), ['password' => 'password', 'confirmation' => 'احذف كل البيانات']);

        $response->assertOk()->assertJsonPath('deleted.orders', 3);
        $this->assertSame(0, Order::count() + Course::count() + Lead::count() + Review::count());
        $this->assertModelExists($editor);
        $this->assertSame('"منصتي"', Setting::find('site_name')->value);
        $this->assertSame('wiped', Activity::query()->latest('id')->first()->action);
        Storage::disk('public')->assertMissing('courses/cover.png');
    }
}
