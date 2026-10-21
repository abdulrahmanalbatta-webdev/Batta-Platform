<?php

namespace App\Console\Commands;

use App\Enums\MentorshipStatus;
use App\Models\MentorshipRequest;
use App\Notifications\MentorshipUpdate;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mentorship:expire-payments')]
#[Description('Close accepted mentorship requests whose payment window passed without a receipt, freeing their seats')]
class ExpireMentorshipPayments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expired = 0;

        MentorshipRequest::query()
            ->with('student')
            ->where('status', MentorshipStatus::Accepted)
            ->where('payment_due_at', '<=', now())
            ->each(function (MentorshipRequest $request) use (&$expired): void {
                $request->forceFill(['status' => MentorshipStatus::Expired])->save();
                $request->student->notify(new MentorshipUpdate($request, 'expired'));
                $expired++;
            });

        $this->info("Expired {$expired} mentorship request(s).");

        return self::SUCCESS;
    }
}
