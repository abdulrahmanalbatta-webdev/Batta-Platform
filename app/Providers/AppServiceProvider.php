<?php

namespace App\Providers;

use App\Enums\ArticleStatus;
use App\Jobs\RebuildSite;
use App\Models\Article;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\SiteBlock;
use App\Models\Student;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Models\Workshop;
use App\Support\PlatformSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
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
        Gate::define('answer-messages', fn (User $user): bool => $user->role->canAnswerMessages());
        Gate::define('moderate-reviews', fn (User $user): bool => $user->role->canModerateReviews());
        Gate::define('manage-leads', fn (User $user): bool => $user->role->canManageLeads());
        Gate::define('manage-settings', fn (User $user): bool => $user->role->canManageSettings());
        Gate::define('manage-platform-data', fn (User $user): bool => $user->role->canManagePlatformData());

        // 300 calls a minute per member (per address before sign-in): far above normal use, low enough to stop scripts
        RateLimiter::for('dashboard-api', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        // the public site's API: overall per address, and tighter on sign-in and on the forms that write or send mail
        RateLimiter::for('site-api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('student-login', fn (Request $request) => Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('site-forms', fn (Request $request) => Limit::perMinute(5)->by($request->path().'|'.$request->ip()));

        // a suspended student's token stops working at once, even before it is deleted
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid && ! ($token->tokenable instanceof Student && $token->tokenable->isSuspended()),
        );

        // behind a load balancer or the host's proxy (TRUSTED_PROXIES), so HTTPS and the visitor's IP come from its
        // headers; set here because the middleware callback in bootstrap/app.php runs before .env is loaded
        if (filled(config('app.trusted_proxies'))) {
            TrustProxies::at(config('app.trusted_proxies'));
        }

        $this->applyPlatformSettings();
        $this->rebuildSiteOnContentChanges();

        // matches the hint on the profile and reset pages: 8+ characters with a number, an upper-case and a lower-case letter
        Password::defaults(function (): Password {
            $rule = Password::min(8)->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        $this->explainFailedUploads();
    }

    /**
     * "تعذّر رفع الصورة" says why PHP dropped the file: too big for php.ini, or a temp folder it cannot write to.
     */
    private function explainFailedUploads(): void
    {
        Validator::replacer('uploaded', function (string $message, string $attribute, string $rule, array $parameters, ValidatorContract $validator): string {
            $file = Arr::get($validator->getData(), $attribute);
            $name = trans()->has("validation.attributes.{$attribute}") ? __("validation.attributes.{$attribute}") : $attribute;

            return match ($file instanceof UploadedFile ? $file->getError() : null) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('تعذّر رفع :name لأن حجمها أكبر مما يسمح به الخادم (upload_max_filesize في php.ini).', ['name' => $name]),
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => __('تعذّر رفع :name لأن الخادم لم يستطع حفظها مؤقتاً: تأكد أن مجلد upload_tmp_dir في php.ini موجود وقابل للكتابة.', ['name' => $name]),
                UPLOAD_ERR_PARTIAL => __('انقطع رفع :name قبل اكتماله، حاول مرة أخرى.', ['name' => $name]),
                default => $message,
            };
        });
    }

    /**
     * What the public site shows changed: rebuild its ready-made pages (App\Jobs\RebuildSite). Drafts don't count.
     */
    private function rebuildSiteOnContentChanges(): void
    {
        // live now, or its status just changed (published → draft takes it off the site)
        Article::saved(fn (Article $article) => ($article->status === ArticleStatus::Published || $article->wasChanged('status')) && RebuildSite::soon());
        Article::deleted(fn (Article $article) => $article->status === ArticleStatus::Published && RebuildSite::soon());

        foreach ([Course::class, Workshop::class, LearningPath::class, Tool::class, ToolCategory::class, SiteBlock::class] as $model) {
            $model::saved(fn () => RebuildSite::soon());
            $model::deleted(fn () => RebuildSite::soon());
        }
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

        // config:cache (and optimize) would write the settings, the SMTP password included, into bootstrap/cache
        if ($this->app->runningInConsole() && in_array($_SERVER['argv'][1] ?? null, ['config:cache', 'optimize'], true)) {
            return;
        }

        $apply(false);
        Queue::before(fn () => $apply(true));

        View::composer('*', fn ($view) => $view->with([
            'currencySymbol' => rescue(fn (): string => $this->app->make(PlatformSettings::class)->currencySymbol(), '$', false),
            'siteUrl' => rescue(fn (): string => (string) $this->app->make(PlatformSettings::class)->get('site_url'), 'https://batta.dev', false),
        ]));
    }
}
