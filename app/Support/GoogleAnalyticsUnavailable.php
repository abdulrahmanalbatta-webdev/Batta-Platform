<?php

namespace App\Support;

use RuntimeException;

/**
 * Google refused or couldn't be reached; the message is safe to show the team.
 */
class GoogleAnalyticsUnavailable extends RuntimeException {}
