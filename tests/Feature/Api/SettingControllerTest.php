<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Activity;
use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_member_reads_the_settings_with_defaults(): void
    {
        $response = $this->actingAs(User::factory()->role(Role::Accountant)->create())->getJson(route('api.settings.show'));

        $response->assertOk()
            ->assertJsonPath('data.site_name', 'Batta')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.session_lifetime', 120)
            ->assertJsonPath('data.stripe_secret_key', ['set' => false, 'hint' => null])
            ->assertJsonPath('meta.gateways.stripe', false)
            ->assertJsonPath('meta.currencies.USD', '$');
    }

    public function test_admin_saves_settings_and_they_apply_to_config(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->putJson(route('api.settings.update'), [
            'site_name' => 'منصة البطة',
            'session_lifetime' => 30,
            'pro_month_price' => 12,
            'maintenance_mode' => true,
        ]);

        $response->assertOk()->assertJsonPath('data.site_name', 'منصة البطة')->assertJsonPath('data.maintenance_mode', true);
        $this->assertSame('منصة البطة', config('app.name'));
        $this->assertSame(30, config('session.lifetime'));
        $this->assertSame(12.0, config('sales.pro_month_price'));
        $this->assertEqualsCanonicalizing(['site_name', 'session_lifetime', 'pro_month_price', 'maintenance_mode'], Activity::sole()->properties['changed']);
    }

    public function test_secrets_are_encrypted_masked_and_kept_when_left_empty(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->putJson(route('api.settings.update'), [
            'stripe_enabled' => true,
            'stripe_publishable_key' => 'pk_test_123',
            'stripe_secret_key' => 'sk_test_abcd9876',
        ])->assertOk()
            ->assertJsonPath('data.stripe_secret_key', ['set' => true, 'hint' => '••••9876'])
            ->assertJsonPath('meta.gateways.stripe', true)
            ->assertDontSee('sk_test_abcd9876');

        $stored = Setting::find('stripe_secret_key')->value;
        $this->assertStringNotContainsString('sk_test', $stored);
        $this->assertSame('"sk_test_abcd9876"', Crypt::decryptString($stored));

        // an empty secret field keeps what's stored
        $this->actingAs($owner)->putJson(route('api.settings.update'), ['stripe_secret_key' => ''])->assertJsonPath('data.stripe_secret_key.set', true);

        $this->actingAs($owner)->deleteJson(route('api.settings.secrets.destroy', 'stripe_secret_key'))->assertNoContent();
        $this->assertFalse(app(PlatformSettings::class)->gatewayReady('stripe'));
    }

    public function test_only_secrets_can_be_cleared(): void
    {
        $this->actingAs(User::factory()->owner()->create())->deleteJson(route('api.settings.secrets.destroy', 'site_name'))->assertNotFound();
    }

    public function test_invalid_values_return_422_with_arabic_names(): void
    {
        $response = $this->actingAs(User::factory()->owner()->create())->putJson(route('api.settings.update'), [
            'site_url' => 'batta',
            'currency' => 'EUR',
            'vat_percent' => 50,
            'stripe_secret_key' => 'pk_wrong',
            'session_lifetime' => 45,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['site_url', 'currency', 'vat_percent', 'stripe_secret_key', 'session_lifetime'])
            ->assertJsonPath('errors.vat_percent.0', 'قيمة ضريبة القيمة المضافة يجب ألا تتجاوز 30.');
    }

    public function test_editor_cannot_change_settings(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();

        $this->actingAs($editor)->putJson(route('api.settings.update'), ['site_name' => 'x'])->assertForbidden();
        $this->actingAs($editor)->postJson(route('api.settings.test-email'))->assertForbidden();
    }

    public function test_smtp_settings_switch_the_mailer(): void
    {
        $this->actingAs(User::factory()->owner()->create())->putJson(route('api.settings.update'), [
            'mail_host' => 'smtp.example.com',
            'mail_port' => 465,
            'mail_username' => 'apikey',
            'mail_password' => 'secret-pass',
            'mail_encryption' => 'ssl',
            'mail_from_address' => 'no-reply@batta.dev',
        ])->assertOk();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('secret-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('no-reply@batta.dev', config('mail.from.address'));
    }

    public function test_sends_a_test_email_to_the_member(): void
    {
        Mail::fake();
        $owner = User::factory()->owner()->create(['email' => 'owner@batta.dev']);

        $response = $this->actingAs($owner)->postJson(route('api.settings.test-email'));

        $response->assertOk()->assertJsonPath('sent_to', 'owner@batta.dev');
    }

    public function test_public_site_settings_never_include_secrets(): void
    {
        app(PlatformSettings::class)->update([
            'bank_transfer_enabled' => true,
            'bank_transfer_instructions' => 'IBAN PS00 0000',
            'paypal_enabled' => true,
            'paypal_client_id' => 'client-1',
            'paypal_secret' => 'very-secret',
        ]);

        $response = $this->getJson(route('api.site-settings'));

        $response->assertOk()
            ->assertJsonPath('data.site_name', 'Batta')
            ->assertJsonPath('data.currency_symbol', '$')
            ->assertJsonPath('data.payment_methods.bank_transfer.instructions', 'IBAN PS00 0000')
            ->assertJsonPath('data.payment_methods.paypal', ['client_id' => 'client-1', 'mode' => 'sandbox'])
            ->assertJsonPath('data.payment_methods.stripe', null)
            ->assertJsonMissingPath('data.invoice_note')
            ->assertDontSee('very-secret');
    }
}
