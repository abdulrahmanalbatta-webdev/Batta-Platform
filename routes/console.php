<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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
