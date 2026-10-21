<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\MentorshipSettings;
use Illuminate\Http\JsonResponse;

class MentorshipController extends Controller
{
    /**
     * The mentorship page: packages, policy, seats left and whether requests are open (no wallet numbers).
     */
    public function __invoke(MentorshipSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->forPublic()]);
    }
}
