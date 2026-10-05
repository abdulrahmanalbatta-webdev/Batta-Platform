<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Http\Responses\FailedPasswordResetResponse;

/**
 * A failed reset: an address with no account reads like a wrong or expired link, so the form
 * can't be used to find out who is on the team.
 */
class PasswordResetFailedResponse extends FailedPasswordResetResponse
{
    public function __construct(string $status)
    {
        parent::__construct($status === Password::INVALID_USER ? Password::INVALID_TOKEN : $status);
    }
}
