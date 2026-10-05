<?php

namespace App\Providers;

use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        $this->applyPlatformSettings();

        // matches the hint on the profile and reset pages: 8+ characters with a number, an upper-case and a lower-case letter
        Password::defaults(function (): Password {
            $rule = Password::min(8)->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    /**
     * Feed the stored platform settings into config, and again before every queued job so a running
     * worker sends mail with the latest settings. Skipped while the database isn't migrated yet.
     */
    private function applyPlatformSettings(): void
    {
        $apply = function (): void {
            try {
                $settings = $this->app->make(PlatformSettings::class);
                $settings->forget();
                $settings->apply();
            } catch (QueryException) {
                // no settings table yet (fresh install, first migration)
            }
        };

        $apply();
        Queue::before(fn () => $apply());

        View::composer('*', fn ($view) => $view->with('currencySymbol', rescue(fn (): string => $this->app->make(PlatformSettings::class)->currencySymbol(), '$', false)));
    }
}
