<?php

namespace Tests\Unit\Support;

use App\Support\UserAgent;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class UserAgentTest extends TestCase
{
    #[TestWith(['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0', 'Windows · Edge', false])]
    #[TestWith(['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'Windows · Chrome', false])]
    #[TestWith(['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15', 'macOS · Safari', false])]
    #[TestWith(['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0 Mobile/15E148 Safari/604.1', 'iPhone · Chrome', true])]
    #[TestWith(['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36', 'Android · Chrome', true])]
    #[TestWith(['Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Linux · Firefox', false])]
    #[TestWith(['curl/8.5.0', 'جهاز غير معروف', false])]
    public function test_describes_device(string $userAgent, string $label, bool $isMobile): void
    {
        $agent = new UserAgent($userAgent);

        $this->assertSame($label, $agent->label());
        $this->assertSame($isMobile, $agent->isMobile());
    }
}
