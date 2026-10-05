<?php

use App\Enums\Role;
use App\Jobs\ExportPlatformData;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-owner {email} {name}', function (string $email, string $name) {
    if (User::where('role', Role::Owner)->exists()) {
        $this->error('The dashboard already has an owner.');

        return 1;
    }

    $validator = Validator::make(
        ['email' => $email, 'password' => $password = $this->secret('Password')],
        ['email' => ['required', 'email', 'unique:users'], 'password' => ['required', Password::defaults()]],
    );

    if ($validator->fails()) {
        collect($validator->errors()->all())->each(fn (string $message) => $this->error($message));

        return 1;
    }

    $owner = new User(['name' => $name, 'email' => $email, 'password' => $password]);
    $owner->role = Role::Owner;
    $owner->email_verified_at = now();
    $owner->save();

    $this->info("Owner {$email} created. Sign in at ".route('login'));

    return 0;
})->purpose('Create the first dashboard owner account');

// needs the scheduler running: "php artisan schedule:work" locally, a cron entry for "schedule:run" in production
Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping();
// Sunday morning summary for the owner and admins (does nothing while switched off in the settings)
Schedule::command('reports:weekly')->weeklyOn(0, '8:00');
// activity older than a year (App\Models\Activity::KEEP_DAYS) and read notifications older than 90 days
Schedule::command('model:prune', ['--model' => [Activity::class]])->daily();
Schedule::call(fn () => DatabaseNotification::query()->whereNotNull('read_at')->where('created_at', '<', now()->subDays(90))->delete())
    ->daily()
    ->name('notifications:prune-read');
// data exports are kept for a week (App\Jobs\ExportPlatformData::KEEP_DAYS)
Schedule::call(function () {
    $disk = Storage::disk(ExportPlatformData::DISK);
    collect($disk->files(ExportPlatformData::DIRECTORY))
        ->filter(fn (string $path): bool => $disk->lastModified($path) < now()->subDays(ExportPlatformData::KEEP_DAYS)->getTimestamp())
        ->each(fn (string $path) => $disk->delete($path));
})->daily()->name('exports:prune');
