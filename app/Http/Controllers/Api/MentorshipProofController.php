<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MentorshipRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MentorshipProofController extends Controller
{
    /**
     * Open the payment receipt the student sent, in the browser.
     */
    public function show(MentorshipRequest $mentorshipRequest): StreamedResponse
    {
        abort_if($mentorshipRequest->proof_path === null, 404);

        return Storage::disk(MentorshipRequest::proofDisk())->response($mentorshipRequest->proof_path, $mentorshipRequest->proof_name, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
