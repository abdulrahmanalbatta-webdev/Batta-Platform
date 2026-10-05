<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetFailedResponse;
use App\Http\Responses\PasswordResetLinkResponse;
use App\Models\User;
use App\Support\AppUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);

        // a reset-link request gets the same reply whether or not the address has an account,
        // so it can't be used to find out who is on the team
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        // and a reset with an unknown address fails like one with a wrong token
        $this->app->bind(FailedPasswordResetResponse::class, PasswordResetFailedResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // reset links are built on APP_URL, not on the Host of the request that asked for them
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => AppUrl::route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]));

        // failed logins have their own tighter limit inside Fortify; this caps everything else, such as reset-link emails
        RateLimiter::for('fortify', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
