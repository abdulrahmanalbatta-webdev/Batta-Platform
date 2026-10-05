<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\WeeklyReport;
use App\Support\NavCounts;
use App\Support\PlatformSettings;
use App\Support\SalesReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('reports:weekly')]
#[Description('Email the last 7 days of sales to the owner and admins (settings → الإشعارات → التقرير الأسبوعي)')]
class SendWeeklyReport extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PlatformSettings $settings, SalesReport $report): int
    {
        if (! $settings->get('weekly_report')) {
            $this->info('The weekly report is switched off.');

            return self::SUCCESS;
        }

        $members = User::query()->whereIn('role', [Role::Owner, Role::Admin])->whereNotNull('email_verified_at')->get();

        Notification::send($members, new WeeklyReport($report->build(7), NavCounts::all()));

        $this->info("Sent the weekly report to {$members->count()} member(s).");

        return self::SUCCESS;
    }
}
