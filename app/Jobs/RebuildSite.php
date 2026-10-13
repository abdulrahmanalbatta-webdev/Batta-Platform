<?php

namespace App\Jobs;

use App\Support\PlatformSettings;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Runs the public site's deploy workflow on GitHub, so the pages it writes at build time (the text search engines
 * and AI crawlers read, the sitemap, llms.txt) include what was just published. Queued a couple of minutes ahead and
 * unique until it runs: a burst of edits makes one rebuild. Does nothing until the owner saves the GitHub token.
 */
class RebuildSite implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Queue a rebuild for a little later, gathering the edits made meanwhile.
     */
    public static function soon(): void
    {
        if (filled(app(PlatformSettings::class)->get('site_rebuild_token'))) {
            static::dispatch()->delay(now()->addMinutes(2));
        }
    }

    public function handle(PlatformSettings $settings): void
    {
        $token = $settings->get('site_rebuild_token');
        if (blank($token)) {
            return;
        }

        $deploy = config('services.site_deploy');

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post("https://api.github.com/repos/{$deploy['repository']}/actions/workflows/{$deploy['workflow']}/dispatches", ['ref' => $deploy['ref']]);
        } catch (ConnectionException $exception) {
            $this->release($this->backoff);

            return;
        }

        // a wrong or expired token is the owner's to fix in the settings; retrying won't help
        if ($response->clientError()) {
            Log::warning('Site rebuild refused by GitHub', ['status' => $response->status()]);

            return;
        }

        $response->throw();
    }
}
