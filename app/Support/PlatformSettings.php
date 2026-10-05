<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Setting;
use App\Rules\GoogleServiceAccountKey;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

/**
 * The platform settings (settings page → عام، الدفع، البريد، الإشعارات، الأمان): every key with its default and rules.
 *
 * Secrets are encrypted in the database and never leave the server: the API only says whether one is set.
 * apply() feeds the settings that change Laravel's behaviour into config (app name, mail, session, Pro price).
 */
class PlatformSettings
{
    public const CACHE_KEY = 'platform.settings';

    /**
     * Currency code => the symbol written after amounts.
     */
    public const CURRENCIES = ['USD' => '$', 'SAR' => ' ر.س', 'JOD' => ' د.أ'];

    /**
     * Minutes of inactivity before a member is signed out.
     */
    public const SESSION_LIFETIMES = [30, 120, 1440];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * The mail config from .env, put back when the SMTP settings are cleared.
     *
     * @var array{default: mixed, smtp: mixed, from: mixed}
     */
    private array $envMail;

    public function __construct()
    {
        $this->envMail = ['default' => config('mail.default'), 'smtp' => config('mail.mailers.smtp'), 'from' => config('mail.from')];
    }

    /**
     * owner: only the owner may change it — where the money and the mail go (an admin could otherwise route
     * payments to their own account, or read reset links through their own mail server).
     *
     * @return array<string, array{label: string, default: mixed, rules: list<mixed>, secret?: bool, public?: bool, owner?: bool}>
     */
    public static function definitions(): array
    {
        $bool = ['boolean'];
        $text = fn (int $max, bool $required = false): array => [$required ? 'required' : 'nullable', 'string', 'max:'.$max];
        $secret = ['nullable', 'string', 'max:255'];

        return [
            // general (shown on the public site)
            'site_name' => ['label' => 'اسم المنصة', 'default' => 'Batta', 'rules' => $text(60, true), 'public' => true],
            'site_url' => ['label' => 'الرابط', 'default' => 'https://batta.dev', 'rules' => ['required', 'url:http,https', 'max:255'], 'public' => true],
            'tagline' => ['label' => 'الوصف المختصر', 'default' => 'أبني مواقع وأنظمة ويب، وأعلّم كيف تُبنى.', 'rules' => $text(160), 'public' => true],
            'contact_email' => ['label' => 'بريد التواصل', 'default' => 'hello@batta.dev', 'rules' => ['required', 'email', 'max:255'], 'public' => true],
            'whatsapp' => ['label' => 'رقم واتساب', 'default' => null, 'rules' => ['nullable', 'regex:/^\+?[0-9 ]{7,20}$/'], 'public' => true],
            'maintenance_mode' => ['label' => 'وضع الصيانة', 'default' => false, 'rules' => $bool, 'public' => true],
            'registration_open' => ['label' => 'السماح بالتسجيل', 'default' => true, 'rules' => $bool, 'public' => true],
            'article_comments' => ['label' => 'التعليقات على المقالات', 'default' => true, 'rules' => $bool, 'public' => true],

            // payments
            'currency' => ['label' => 'العملة', 'default' => 'USD', 'rules' => ['required', Rule::in(array_keys(self::CURRENCIES))], 'public' => true],
            'vat_percent' => ['label' => 'ضريبة القيمة المضافة', 'default' => 0, 'rules' => ['required', 'numeric', 'min:0', 'max:30'], 'public' => true],
            'pro_month_price' => ['label' => 'سعر شهر Pro', 'default' => (float) config('sales.pro_month_price'), 'rules' => ['required', 'numeric', 'min:1', 'max:1000'], 'public' => true],
            'invoice_note' => ['label' => 'ملاحظة الفاتورة', 'default' => 'شكراً لثقتك بـ Batta. للاستفسار: hello@batta.dev', 'rules' => $text(500)],
            'refund_guarantee' => ['label' => 'ضمان الاسترداد', 'default' => true, 'rules' => $bool, 'public' => true],
            // payments are taken by hand (bank transfer, wallet, cash); the site shows the student how to pay
            'payment_instructions' => ['label' => 'تعليمات الدفع', 'default' => null, 'rules' => $text(1000), 'public' => true, 'owner' => true],

            // outgoing email: empty host = the MAIL_* values in .env
            'mail_host' => ['label' => 'خادم SMTP', 'default' => null, 'rules' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'], 'owner' => true],
            'mail_port' => ['label' => 'المنفذ', 'default' => 587, 'rules' => ['required', 'integer', 'between:1,65535'], 'owner' => true],
            'mail_username' => ['label' => 'اسم المستخدم', 'default' => null, 'rules' => $text(255), 'owner' => true],
            'mail_password' => ['label' => 'كلمة مرور SMTP', 'default' => null, 'rules' => $secret, 'secret' => true, 'owner' => true],
            'mail_encryption' => ['label' => 'التشفير', 'default' => 'tls', 'rules' => ['required', Rule::in(['tls', 'ssl'])], 'owner' => true],
            'mail_from_address' => ['label' => 'بريد المرسل', 'default' => null, 'rules' => ['nullable', 'email', 'max:255'], 'owner' => true],
            'mail_from_name' => ['label' => 'اسم المرسل', 'default' => null, 'rules' => $text(60), 'owner' => true],

            // site traffic (Google Analytics 4): the site loads the measurement ID, the dashboard reads reports
            // with a service account given read access ("Viewer") to the property
            'ga_measurement_id' => ['label' => 'معرّف القياس (Measurement ID)', 'default' => null, 'rules' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'], 'public' => true],
            'ga_property_id' => ['label' => 'رقم الموقع (Property ID)', 'default' => null, 'rules' => ['nullable', 'string', 'regex:/^[0-9]{5,20}$/']],
            'ga_credentials' => ['label' => 'مفتاح حساب الخدمة', 'default' => null, 'rules' => ['nullable', 'string', 'max:10000', new GoogleServiceAccountKey], 'secret' => true, 'owner' => true],

            // team notifications
            'weekly_report' => ['label' => 'التقرير الأسبوعي', 'default' => true, 'rules' => $bool],
            'newsletter_new_articles' => ['label' => 'إرسال المقالات الجديدة للطلاب والمشتركين', 'default' => false, 'rules' => $bool],

            // security
            'new_device_alert' => ['label' => 'تنبيه الدخول من جهاز جديد', 'default' => true, 'rules' => $bool],
            'session_lifetime' => ['label' => 'انتهاء الجلسة', 'default' => 120, 'rules' => ['required', 'integer', Rule::in(self::SESSION_LIFETIMES)]],
        ];
    }

    /**
     * The keys only the owner may change.
     *
     * @return list<string>
     */
    public static function ownerOnlyKeys(): array
    {
        return array_keys(array_filter(self::definitions(), fn (array $definition): bool => $definition['owner'] ?? false));
    }

    /**
     * Every setting: what's stored, else the default.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        // the cache holds the stored (still encrypted) values only
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
        $values = [];

        foreach (self::definitions() as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored) ? $this->decode($stored[$key], $definition['secret'] ?? false) : $definition['default'];
        }

        return $this->values = $values;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    /**
     * Save some settings (already validated). An empty secret keeps the stored one.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        $definitions = self::definitions();
        $current = $this->all();

        // the stored SMTP password only ever goes to the server it was given for
        $mailServerChanges = collect(['mail_host', 'mail_username'])->contains(fn (string $key): bool => array_key_exists($key, $values) && $values[$key] !== $current[$key]);
        if ($mailServerChanges && blank($values['mail_password'] ?? null)) {
            Setting::query()->whereKey('mail_password')->delete();
        }

        foreach ($values as $key => $value) {
            $secret = $definitions[$key]['secret'] ?? false;

            if ($secret && blank($value)) {
                continue;
            }

            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $secret ? Crypt::encryptString($encoded) : $encoded]);
        }

        $this->forget();
        $this->apply();
    }

    /**
     * Remove a stored secret (e.g. a revoked key).
     */
    public function clear(string $key): void
    {
        Setting::query()->whereKey($key)->delete();
        $this->forget();
        $this->apply();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->refresh();
    }

    /**
     * Read the settings again from the cache (a long-running queue worker, before each job).
     */
    public function refresh(): void
    {
        $this->values = null;
    }

    /**
     * For the settings page: secrets become {set, hint} and never their value; the hint (last 4 characters) only for the owner.
     *
     * @return array<string, mixed>
     */
    public function forClient(bool $withHints = false): array
    {
        $values = $this->all();

        foreach (self::definitions() as $key => $definition) {
            if ($definition['secret'] ?? false) {
                $values[$key] = ['set' => filled($values[$key]), 'hint' => $withHints && filled($values[$key]) ? $this->hint($key, (string) $values[$key]) : null];
            }
        }

        return $values;
    }

    /**
     * What tells the owner which secret is stored: its last 4 characters, or the service account's address.
     */
    private function hint(string $key, string $secret): string
    {
        if ($key === 'ga_credentials') {
            return (string) (json_decode($secret, true)['client_email'] ?? '');
        }

        return '••••'.mb_substr($secret, -4);
    }

    /**
     * What the public site needs: the public settings and the ways customers can pay.
     *
     * @return array<string, mixed>
     */
    public function forPublic(): array
    {
        $values = $this->all();

        return [
            ...collect(self::definitions())->filter(fn (array $definition): bool => $definition['public'] ?? false)->map(fn (array $definition, string $key): mixed => $values[$key])->all(),
            'currency_symbol' => $this->currencySymbol(),
            'payment_methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()])->all(),
        ];
    }

    public function currencySymbol(): string
    {
        return self::CURRENCIES[$this->get('currency')];
    }

    /**
     * An amount with the platform currency, e.g. "1,200$" or "49.50 ر.س".
     */
    public function money(float $amount): string
    {
        return number_format($amount, fmod($amount, 1.0) == 0.0 ? 0 : 2).$this->currencySymbol();
    }

    /**
     * Put the settings that change how Laravel behaves into config.
     */
    public function apply(): void
    {
        $values = $this->all();

        // start from .env every time, so clearing a setting really goes back to it
        config(['mail.default' => $this->envMail['default'], 'mail.mailers.smtp' => $this->envMail['smtp'], 'mail.from' => $this->envMail['from']]);

        config([
            'app.name' => $values['site_name'],
            'session.lifetime' => (int) $values['session_lifetime'],
            'sales.pro_month_price' => (float) $values['pro_month_price'],
            'mail.from.name' => $values['mail_from_name'] ?: $values['site_name'],
        ]);

        if (filled($values['mail_from_address'])) {
            config(['mail.from.address' => $values['mail_from_address']]);
        }

        if (filled($values['mail_host'])) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $values['mail_host'],
                'mail.mailers.smtp.port' => (int) $values['mail_port'],
                'mail.mailers.smtp.username' => $values['mail_username'],
                'mail.mailers.smtp.password' => $values['mail_password'],
                // "ssl" is SMTPS on its own port (465); "tls" upgrades a plain connection with STARTTLS (587)
                'mail.mailers.smtp.scheme' => $values['mail_encryption'] === 'ssl' ? 'smtps' : 'smtp',
            ]);
        }

        // mailers already built keep the old settings
        if (app()->resolved('mail.manager')) {
            app('mail.manager')->forgetMailers();
        }
    }

    private function decode(?string $stored, bool $secret): mixed
    {
        if ($stored === null) {
            return null;
        }

        if (! $secret) {
            return json_decode($stored, true);
        }

        try {
            return json_decode(Crypt::decryptString($stored), true);
        } catch (DecryptException $exception) {
            // stored under an older APP_KEY: treat it as unset rather than take the app down; enter it again in the settings
            report($exception);

            return null;
        }
    }
}
