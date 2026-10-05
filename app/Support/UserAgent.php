<?php

namespace App\Support;

/**
 * Turns a browser user-agent string into a short label such as "Windows · Chrome" for the sessions list.
 */
class UserAgent
{
    /**
     * @var array<string, string> pattern => name, checked in order (Edge and Opera also contain "Chrome")
     */
    private const BROWSERS = [
        '/Edg(e|A|iOS)?\//' => 'Edge',
        '/OPR\/|Opera/' => 'Opera',
        '/SamsungBrowser/' => 'Samsung Internet',
        '/Firefox|FxiOS/' => 'Firefox',
        '/Chrome|CriOS/' => 'Chrome',
        '/Safari/' => 'Safari',
    ];

    /**
     * @var array<string, string> pattern => name, checked in order (iOS user agents also contain "Mac OS X")
     */
    private const PLATFORMS = [
        '/iPhone/' => 'iPhone',
        '/iPad/' => 'iPad',
        '/Android/' => 'Android',
        '/Windows/' => 'Windows',
        '/Macintosh|Mac OS X/' => 'macOS',
        '/CrOS/' => 'ChromeOS',
        '/Linux/' => 'Linux',
    ];

    public function __construct(private readonly string $userAgent) {}

    public function browser(): ?string
    {
        return $this->match(self::BROWSERS);
    }

    public function platform(): ?string
    {
        return $this->match(self::PLATFORMS);
    }

    public function isMobile(): bool
    {
        return in_array($this->platform(), ['iPhone', 'Android'], true);
    }

    public function label(): string
    {
        $parts = array_filter([$this->platform(), $this->browser()]);

        return $parts ? implode(' · ', $parts) : 'جهاز غير معروف';
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private function match(array $patterns): ?string
    {
        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $this->userAgent)) {
                return $name;
            }
        }

        return null;
    }
}
