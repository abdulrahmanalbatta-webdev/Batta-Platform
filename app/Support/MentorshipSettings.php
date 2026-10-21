<?php

namespace App\Support;

use App\Enums\MentorshipStatus;
use App\Models\MentorshipRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The mentorship's own settings (dashboard → الإرشاد → الإعدادات), owner only: whether requests are open,
 * the seats, the payment window, the wallets students pay into, the packages and the cancellation policy.
 *
 * Kept as one JSON row in the settings table. The wallet numbers never reach the public site: only a student
 * whose request was accepted sees them (Site\MyMentorshipController).
 */
class MentorshipSettings
{
    public const KEY = 'mentorship';

    /**
     * The wallets a payment can go to; the owner fills in the number and the name it's under.
     */
    public const WALLETS = ['palpay' => 'بال باي', 'jawwalpay' => 'جوال باي'];

    public const MIN_PAYMENT_HOURS = 12;

    public const MAX_PAYMENT_HOURS = 24;

    public const MAX_PACKAGES = 6;

    /**
     * @return array{enabled: bool, capacity: int, payment_hours: int, wallets: array<string, array{number: ?string, holder: ?string}>, packages: list<array{id: string, name: string, price: int, period: string, description: string, features: list<string>, featured: bool}>, policy: string}
     */
    public static function defaults(): array
    {
        return [
            // off until the owner has filled in the wallets
            'enabled' => false,
            'capacity' => 10,
            'payment_hours' => 24,
            'wallets' => collect(self::WALLETS)->map(fn (): array => ['number' => null, 'holder' => null])->all(),
            'packages' => [
                ['id' => 'session', 'name' => 'جلسة استشارية', 'price' => 50, 'period' => '', 'description' => 'جلسة واحدة (60 دقيقة) لمراجعة مشروع أو سيرة ذاتية أو تحديد مسار.', 'features' => ['جلسة فردية مدتها 60 دقيقة عبر Google Meet', 'مراجعة مشروع أو كود أو سيرة ذاتية', 'تحديد مسار التعلّم المناسب لك'], 'featured' => false],
                ['id' => 'monthly', 'name' => 'إرشاد شهري', 'price' => 150, 'period' => 'شهرياً', 'description' => '4 جلسات أسبوعية، وخطة تعلّم، ومراجعة مهام ومشاريع، ومتابعة بين الجلسات.', 'features' => ['4 جلسات أسبوعية', 'خطة تعلّم شخصية', 'مراجعة المهام والمشاريع', 'متابعة بين الجلسات'], 'featured' => true],
                ['id' => 'intensive', 'name' => 'برنامج مكثّف', 'price' => 400, 'period' => '3 أشهر', 'description' => 'كل ما في الإرشاد الشهري، مع هدف نهائي واضح ومتابعة حتى تحقيقه.', 'features' => ['كل ما في الإرشاد الشهري', 'هدف نهائي واضح نعمل عليه معاً', 'متابعة حتى تحقيق الهدف'], 'featured' => false],
            ],
            'policy' => 'يمكن إعادة جدولة الجلسة إذا أبلغتني قبل 24 ساعة على الأقل، وإلا تُحتسب الجلسة. ويمكن استرجاع المبلغ كاملاً قبل موعد الجلسة الأولى فقط.',
        ];
    }

    /**
     * Validation rules for saving them.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'capacity' => ['required', 'integer', 'between:1,100'],
            'payment_hours' => ['required', 'integer', 'between:'.self::MIN_PAYMENT_HOURS.','.self::MAX_PAYMENT_HOURS],
            'wallets' => ['required', 'array:'.implode(',', array_keys(self::WALLETS))],
            'wallets.*' => ['array:number,holder'],
            'wallets.*.number' => ['nullable', 'string', 'regex:/^\+?[0-9 ]{6,20}$/'],
            'wallets.*.holder' => ['nullable', 'string', 'max:80'],
            'packages' => ['required', 'array', 'min:1', 'max:'.self::MAX_PACKAGES],
            'packages.*.id' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/', 'distinct'],
            'packages.*.name' => ['required', 'string', 'max:80'],
            'packages.*.price' => ['required', 'integer', 'between:0,100000'],
            'packages.*.period' => ['nullable', 'string', 'max:30'],
            'packages.*.description' => ['required', 'string', 'max:300'],
            'packages.*.features' => ['present', 'array', 'max:8'],
            'packages.*.features.*' => ['required', 'string', 'max:120'],
            'packages.*.featured' => ['boolean'],
            'policy' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * What's stored, else the defaults.
     *
     * @return array{enabled: bool, capacity: int, payment_hours: int, wallets: array<string, array{number: ?string, holder: ?string}>, packages: list<array{id: string, name: string, price: int, period: string, description: string, features: list<string>, featured: bool}>, policy: string}
     */
    public function all(): array
    {
        $stored = Cache::rememberForever('mentorship.settings', fn (): ?string => Setting::query()->find(self::KEY)?->value);

        return array_replace(self::defaults(), $stored === null ? [] : (array) json_decode($stored, true));
    }

    /**
     * Save them (already validated): packages without an id get one, so requests can keep pointing at them.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        $values['packages'] = collect($values['packages'])->map(fn (array $package): array => [
            'id' => $package['id'] ?? Str::lower(Str::random(8)),
            'name' => $package['name'],
            'price' => (int) $package['price'],
            'period' => (string) ($package['period'] ?? ''),
            'description' => $package['description'],
            'features' => array_values($package['features']),
            'featured' => (bool) ($package['featured'] ?? false),
        ])->values()->all();

        $values['wallets'] = collect(self::WALLETS)->map(fn (string $name, string $id): array => [
            'number' => $values['wallets'][$id]['number'] ?? null,
            'holder' => $values['wallets'][$id]['holder'] ?? null,
        ])->all();

        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => json_encode(array_intersect_key($values, self::defaults()), JSON_UNESCAPED_UNICODE)]);
        Cache::forget('mentorship.settings');
    }

    /**
     * @return array{id: string, name: string, price: int, period: string, description: string, features: list<string>, featured: bool}|null
     */
    public function package(string $id): ?array
    {
        return collect($this->all()['packages'])->firstWhere('id', $id);
    }

    /**
     * The wallets with a number, as the student sees them after being accepted.
     *
     * @return list<array{id: string, name: string, number: string, holder: ?string}>
     */
    public function wallets(): array
    {
        return collect($this->all()['wallets'])
            ->filter(fn (array $wallet, string $id): bool => isset(self::WALLETS[$id]) && filled($wallet['number']))
            ->map(fn (array $wallet, string $id): array => ['id' => $id, 'name' => self::WALLETS[$id], 'number' => $wallet['number'], 'holder' => $wallet['holder']])
            ->values()
            ->all();
    }

    public function seatsTaken(): int
    {
        return MentorshipRequest::query()->whereIn('status', MentorshipStatus::holdingSeat())->count();
    }

    public function seatsLeft(): int
    {
        return max(0, $this->all()['capacity'] - $this->seatsTaken());
    }

    /**
     * Open for requests: switched on, with a wallet to pay into and a free seat.
     */
    public function isAccepting(): bool
    {
        return $this->all()['enabled'] && $this->wallets() !== [] && $this->seatsLeft() > 0;
    }

    /**
     * What the public site shows: everything but the wallet numbers.
     *
     * @return array<string, mixed>
     */
    public function forPublic(): array
    {
        $settings = $this->all();

        return [
            'enabled' => $settings['enabled'],
            'accepting' => $this->isAccepting(),
            'capacity' => $settings['capacity'],
            'seats_left' => $this->seatsLeft(),
            'payment_hours' => $settings['payment_hours'],
            'payment_methods' => array_column($this->wallets(), 'name'),
            'packages' => $settings['packages'],
            'policy' => $settings['policy'],
        ];
    }
}
