<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\User;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_gets_the_original_texts_until_something_is_edited(): void
    {
        $defaults = SiteContent::defaults();

        $this->getJson(route('site.content'))
            ->assertOk()
            ->assertJsonPath('data.announcement', $defaults['announcement'])
            ->assertJsonPath('data.services.0.id', 'websites')
            ->assertJsonPath('data.case_studies.0.kpis.0', ['value' => '-62%', 'label' => 'مواعيد ضائعة'])
            ->assertJsonPath('data.photo', null);
    }

    public function test_an_editor_changes_a_section_and_the_site_sees_it_at_once(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $this->getJson(route('site.content')); // cached

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'announcement'), ['value' => [
            'enabled' => true,
            'text' => 'دورة Laravel الجديدة متاحة الآن',
            'link' => '/courses',
            'link_label' => 'سجّل',
            'stray' => '<script>',
        ]])->assertOk()->assertJsonMissingPath('data.stray');

        $this->getJson(route('site.content'))
            ->assertJsonPath('data.announcement.text', 'دورة Laravel الجديدة متاحة الآن')
            ->assertJsonMissingPath('data.announcement.stray');
        $this->assertSame('محتوى الموقع: شريط الإعلان', Activity::query()->latest('id')->first()->subject_name);
    }

    public function test_lists_with_nested_items_are_validated_and_kept_clean(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $study = ['id' => 'clinic', 'title' => 'نظام عيادة', 'tag' => 'تطبيق', 'sector' => 'صحة', 'problem' => 'حجوزات ضائعة', 'solution' => 'حجز أونلاين', 'tech' => ['Laravel'], 'kpis' => [['value' => '×2', 'label' => 'الحجوزات', 'x' => 1]]];

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'case_studies'), ['value' => [$study]])
            ->assertOk()
            ->assertJsonPath('data.0.kpis.0', ['value' => '×2', 'label' => 'الحجوزات']);

        $invalid = $this->actingAs($editor)->putJson(route('api.site-content.update', 'case_studies'), ['value' => [[...$study, 'id' => 'Not Valid', 'kpis' => [['value' => '', 'label' => 'x']]]]])
            ->assertJsonValidationErrors(['value.0.id', 'value.0.kpis.0.value']);
        $this->assertSame('حقل الرقم مطلوب.', $invalid->json('errors')['value.0.kpis.0.value'][0]);

        $this->actingAs($editor)->putJson(route('api.site-content.update', 'highlights'), ['value' => [['icon' => 'rocket', 'title' => 'أ', 'text' => 'ب']]])
            ->assertJsonValidationErrors('value.0.icon');
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'socials'), ['value' => [['network' => 'github', 'url' => 'javascript:alert(1)']]])
            ->assertJsonValidationErrors('value.0.url');
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'technologies'), ['value' => ['laravel', 'cobol']])
            ->assertJsonValidationErrors('value.1');
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'faqs'), ['value' => array_fill(0, 21, ['q' => 'س', 'a' => 'ج'])])
            ->assertJsonValidationErrors('value');
    }

    public function test_a_section_goes_back_to_the_original_text(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();
        $this->actingAs($editor)->putJson(route('api.site-content.update', 'reasons'), ['value' => ['سبب واحد']])->assertOk();

        $this->actingAs($editor)->deleteJson(route('api.site-content.destroy', 'reasons'))
            ->assertOk()
            ->assertJsonPath('data', SiteContent::defaults()['reasons']);
    }

    public function test_only_content_editors_change_it_and_unknown_sections_are_404(): void
    {
        $this->actingAs(User::factory()->role(Role::Support)->create())->getJson(route('api.site-content.show'))
            ->assertOk()
            ->assertJsonPath('meta.groups.home', 'الرئيسية')
            ->assertJsonPath('meta.definitions.services.item.icon.type', 'icon');
        $this->actingAs(User::factory()->role(Role::Support)->create())->putJson(route('api.site-content.update', 'reasons'), ['value' => []])->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Editor)->create())->putJson(route('api.site-content.update', 'nope'), ['value' => []])->assertNotFound();
    }

    /**
     * A real PNG of the given size, built without the GD extension (as in AvatarControllerTest).
     */
    private function png(string $name, int $size): UploadedFile
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\x33\x66\x99", $size), $size);

        return UploadedFile::fake()->createWithContent($name, "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($rows))
            .$chunk('IEND', ''));
    }

    public function test_the_photo_is_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $editor = User::factory()->role(Role::Editor)->create();

        $first = $this->actingAs($editor)->postJson(route('api.site-photo.store'), ['photo' => $this->png('me.png', 400)])->assertOk()->json('data.photo');
        $this->assertNotNull($first);
        $this->getJson(route('site.content'))->assertJsonPath('data.photo', $first);

        $this->actingAs($editor)->postJson(route('api.site-photo.store'), ['photo' => $this->png('me2.png', 400)])->assertOk();
        $this->assertCount(1, Storage::disk('public')->files('site'));

        $this->actingAs($editor)->postJson(route('api.site-photo.store'), ['photo' => $this->png('tiny.png', 50)])->assertJsonValidationErrors('photo');

        $this->actingAs($editor)->deleteJson(route('api.site-photo.destroy'))->assertOk();
        $this->assertCount(0, Storage::disk('public')->files('site'));
        $this->getJson(route('site.content'))->assertJsonPath('data.photo', null);
    }
}
