<?php

namespace App\Providers;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PlatformSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // writing courses, workshops, articles and tools; every member may read them
        Gate::define('manage-content', fn (User $user): bool => $user->role->canManageContent());
        Gate::define('manage-students', fn (User $user): bool => $user->role->canManageStudents());
        Gate::define('manage-sales', fn (User $user): bool => $user->role->canManageSales());
        Gate::define('answer-messages', fn (User $user): bool => $user->role->canAnswerMessages());
        Gate::define('moderate-reviews', fn (User $user): bool => $user->role->canModerateReviews());
        Gate::define('manage-leads', fn (User $user): bool => $user->role->canManageLeads());
        Gate::define('manage-settings', fn (User $user): bool => $user->role->canManageSettings());
        Gate::define('manage-platform-data', fn (User $user): bool => $user->role->canManagePlatformData());

        // 300 calls a minute per member (per address before sign-in): far above normal use, low enough to stop scripts
        RateLimiter::for('dashboard-api', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        $this->applyPlatformSettings();

        // matches the hint on the profile and reset pages: 8+ characters with a number, an upper-case and a lower-case letter
        Password::defaults(function (): Password {
            $rule = Password::min(8)->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Feed the stored platform settings into config, and again before every queued job so a running
     * worker sends mail with the latest settings. Never stops the app from booting.
     */
    private function applyPlatformSettings(): void
    {
        $apply = function (bool $refresh): void {
            try {
                $settings = $this->app->make(PlatformSettings::class);
                if ($refresh) {
                    $settings->refresh();
                }
                $settings->apply();
            } catch (QueryException) {
                // no settings table yet (fresh install, first migration)
            } catch (Throwable $exception) {
                // e.g. the cache store is down: run on the defaults from config
                report($exception);
            }
        };

        $apply(false);
        Queue::before(fn () => $apply(true));

        View::composer('*', fn ($view) => $view->with([
            'currencySymbol' => rescue(fn (): string => $this->app->make(PlatformSettings::class)->currencySymbol(), '$', false),
            'siteUrl' => rescue(fn (): string => (string) $this->app->make(PlatformSettings::class)->get('site_url'), 'https://batta.dev', false),
        ]));
    }
}
