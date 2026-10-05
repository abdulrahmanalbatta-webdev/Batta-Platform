<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Site traffic from Google Analytics 4 (the Data API), read with the service account stored in the settings.
 *
 * No Google SDK: the service account signs a JWT (RS256) that is exchanged for an access token, then one
 * batchRunReports call returns the totals, the daily (or monthly) series, sources, devices and top pages.
 * Reports are cached for an hour, tokens until shortly before they expire.
 */
class GoogleAnalytics
{
    public const REPORT_TTL = 3600;

    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private const API = 'https://analyticsdata.googleapis.com/v1beta';

    private const CHANNELS = [
        'Direct' => 'مباشر',
        'Organic Search' => 'بحث',
        'Paid Search' => 'إعلانات البحث',
        'Organic Social' => 'تواصل اجتماعي',
        'Paid Social' => 'إعلانات التواصل',
        'Referral' => 'مواقع أخرى',
        'Email' => 'البريد',
        'Organic Video' => 'فيديو',
        'Display' => 'إعلانات مصوّرة',
        'Unassigned' => 'غير محدد',
    ];

    private const DEVICES = ['desktop' => 'كمبيوتر', 'mobile' => 'جوال', 'tablet' => 'تابلت', 'smart tv' => 'تلفاز'];

    public function __construct(private PlatformSettings $settings) {}

    /**
     * A property ID and a service account key are both saved.
     */
    public function configured(): bool
    {
        return filled($this->settings->get('ga_property_id')) && $this->credentials() !== null;
    }

    /**
     * Traffic for the last 7, 30 or 90 days (daily) or 12 months (monthly), compared with the stretch before,
     * the same windows as the sales report.
     *
     * @return array<string, mixed>
     *
     * @throws GoogleAnalyticsUnavailable
     */
    public function report(int $days): array
    {
        $credentials = $this->credentials();
        $key = 'analytics.traffic.'.$days.'.'.md5($this->settings->get('ga_property_id').'|'.($credentials['client_email'] ?? ''));

        return Cache::remember($key, self::REPORT_TTL, fn (): array => $this->build($days));
    }

    /**
     * A one-day request, to tell the team whether the property and the key work together.
     *
     * @throws GoogleAnalyticsUnavailable
     */
    public function check(): void
    {
        $this->runBatch([[
            'dateRanges' => [['startDate' => 'yesterday', 'endDate' => 'today']],
            'metrics' => [['name' => 'activeUsers']],
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $days): array
    {
        $monthly = $days > 90;
        $from = $monthly ? now()->startOfMonth()->subMonths(11) : today()->subDays($days - 1);
        $previousFrom = $monthly ? $from->copy()->subYear() : $from->copy()->subDays($days);
        $previousTo = $monthly ? now()->subYear() : $from->copy()->subDay();
        $current = ['startDate' => $from->toDateString(), 'endDate' => 'today'];

        [$totals, $series, $sources, $devices, $pages] = $this->runBatch([
            [
                'dateRanges' => [$current, ['startDate' => $previousFrom->toDateString(), 'endDate' => $previousTo->toDateString()]],
                'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews'], ['name' => 'engagementRate']],
            ],
            [
                'dateRanges' => [$current],
                'dimensions' => [['name' => $monthly ? 'yearMonth' : 'date']],
                'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions']],
            ],
            [
                'dateRanges' => [$current],
                'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
                'metrics' => [['name' => 'sessions']],
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
                'limit' => 8,
            ],
            [
                'dateRanges' => [$current],
                'dimensions' => [['name' => 'deviceCategory']],
                'metrics' => [['name' => 'sessions']],
                'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]],
            ],
            [
                'dateRanges' => [$current],
                'dimensions' => [['name' => 'pagePath'], ['name' => 'pageTitle']],
                'metrics' => [['name' => 'screenPageViews']],
                'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]],
                'limit' => 10,
            ],
        ]);

        return [
            'days' => $days,
            'kpis' => $this->kpis($totals),
            'series' => $this->series($series, $from, $monthly),
            'sources' => $this->shares($sources, self::CHANNELS),
            'devices' => $this->shares($devices, self::DEVICES),
            'pages' => $this->rows($pages)->map(fn (array $row): array => [
                'path' => $row['dimensions'][0],
                'title' => $row['dimensions'][1] === '(not set)' ? null : $row['dimensions'][1],
                'views' => (int) $row['metrics'][0],
            ])->all(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, array{value: float, previous: float, change: ?int}>
     */
    private function kpis(array $report): array
    {
        // with two date ranges, each row carries "date_range_0" (current) or "date_range_1" (previous)
        $byRange = $this->rows($report)->keyBy(fn (array $row): string => $row['dimensions'][0] ?? 'date_range_0');
        $metric = fn (string $range, int $index): float => (float) ($byRange[$range]['metrics'][$index] ?? 0);
        $kpi = function (int $index, int $scale = 1) use ($metric): array {
            $value = $metric('date_range_0', $index) * $scale;
            $previous = $metric('date_range_1', $index) * $scale;

            return [
                'value' => round($value, 1),
                'previous' => round($previous, 1),
                'change' => $previous > 0 ? (int) round(($value - $previous) / $previous * 100) : null,
            ];
        };

        return [
            'users' => $kpi(0),
            'sessions' => $kpi(1),
            'views' => $kpi(2),
            'engagement' => $kpi(3, 100),
        ];
    }

    /**
     * One point per day (or month) of the window, zero where Google has no row.
     *
     * @param  array<string, mixed>  $report
     * @return array{labels: list<string>, users: list<int>, sessions: list<int>}
     */
    private function series(array $report, CarbonInterface $from, bool $monthly): array
    {
        $rows = $this->rows($report)->keyBy(fn (array $row): string => $row['dimensions'][0]);
        $series = ['labels' => [], 'users' => [], 'sessions' => []];

        for ($bucket = Carbon::instance($from)->copy(); $bucket->lte(now()); $monthly ? $bucket->addMonth() : $bucket->addDay()) {
            $row = $rows[$bucket->format($monthly ? 'Ym' : 'Ymd')] ?? null;
            $series['labels'][] = $bucket->locale('ar')->translatedFormat($monthly ? 'F' : 'j F');
            $series['users'][] = (int) ($row['metrics'][0] ?? 0);
            $series['sessions'][] = (int) ($row['metrics'][1] ?? 0);
        }

        return $series;
    }

    /**
     * Rows of one dimension as percentages of the sessions, with Arabic labels where known.
     *
     * @param  array<string, mixed>  $report
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string, sessions: int, value: int}>
     */
    private function shares(array $report, array $labels): array
    {
        $rows = $this->rows($report);
        $total = max(1, $rows->sum(fn (array $row): int => (int) $row['metrics'][0]));

        return $rows->map(fn (array $row): array => [
            'key' => $row['dimensions'][0],
            'label' => $labels[$row['dimensions'][0]] ?? $row['dimensions'][0],
            'sessions' => (int) $row['metrics'][0],
            'value' => (int) round((int) $row['metrics'][0] / $total * 100),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $report
     * @return Collection<int, array{dimensions: list<string>, metrics: list<string>}>
     */
    private function rows(array $report): Collection
    {
        return collect($report['rows'] ?? [])->map(fn (array $row): array => [
            'dimensions' => array_column($row['dimensionValues'] ?? [], 'value'),
            'metrics' => array_column($row['metricValues'] ?? [], 'value'),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $requests
     * @return list<array<string, mixed>>
     */
    private function runBatch(array $requests): array
    {
        if (! $this->configured()) {
            throw new GoogleAnalyticsUnavailable('أضف رقم الموقع (Property ID) ومفتاح حساب الخدمة من الإعدادات.');
        }

        $response = $this->send(fn () => Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(20)
            ->post(self::API.'/properties/'.$this->settings->get('ga_property_id').':batchRunReports', ['requests' => $requests]));

        if ($response->failed()) {
            throw new GoogleAnalyticsUnavailable(match ($response->status()) {
                403 => 'حساب الخدمة لا يملك صلاحية على هذا الموقع في Google Analytics: أضف بريده كمستخدم (Viewer) في إدارة الموقع، وتأكد من تفعيل Google Analytics Data API في مشروعه.',
                404, 400 => 'لم يُعثر على الموقع: تأكد من رقم الموقع (Property ID) في الإعدادات، وهو رقم وليس معرّف القياس G-….',
                429 => 'تجاوزت المنصة حصة الطلبات اليومية من Google Analytics، حاول لاحقاً.',
                default => 'تعذّر جلب البيانات من Google Analytics ('.$response->status().').',
            });
        }

        return $response->json('reports', []);
    }

    /**
     * An OAuth access token for the service account, from a JWT it signs with its private key.
     */
    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $cacheKey = 'analytics.google-token.'.md5($credentials['client_email'].'|'.($credentials['private_key_id'] ?? ''));

        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $now = time();
        $segments = [
            self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url(json_encode(['iss' => $credentials['client_email'], 'scope' => self::SCOPE, 'aud' => $tokenUri, 'iat' => $now, 'exp' => $now + 3600])),
        ];

        if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new GoogleAnalyticsUnavailable('مفتاح حساب الخدمة غير صالح للتوقيع. نزّل مفتاحاً جديداً من Google Cloud.');
        }
        $segments[] = self::base64Url($signature);

        $response = $this->send(fn () => Http::asForm()->acceptJson()->timeout(15)->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => implode('.', $segments),
        ]));

        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new GoogleAnalyticsUnavailable('رفضت Google مفتاح حساب الخدمة (ربما حُذف أو عُطّل). أنشئ مفتاحاً جديداً واحفظه في الإعدادات.');
        }

        Cache::put($cacheKey, $response->json('access_token'), max(60, (int) $response->json('expires_in', 3600) - 300));

        return $response->json('access_token');
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw new GoogleAnalyticsUnavailable('تعذّر الاتصال بـ Google. تحقق من اتصال الخادم بالإنترنت.');
        }
    }

    /**
     * @return array{client_email: string, private_key: string, private_key_id?: string, token_uri?: string}|null
     */
    private function credentials(): ?array
    {
        $key = json_decode((string) $this->settings->get('ga_credentials'), true);

        return is_array($key) && is_string($key['client_email'] ?? null) && is_string($key['private_key'] ?? null) ? $key : null;
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
