<?php

namespace App\Notifications\Concerns;

/**
 * A queued email that fails (the mail server busy or briefly refusing) is tried again a minute later, then five
 * minutes later, instead of being dropped on the first error: a password reset must not get lost to a hiccup.
 */
trait RetriesDelivery
{
    public int $tries = 3;

    /**
     * Seconds to wait before each retry.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }
}
