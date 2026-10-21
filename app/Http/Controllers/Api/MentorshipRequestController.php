<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MentorshipRequestResource;
use App\Models\MentorshipRequest;
use App\Support\MentorshipSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MentorshipRequestController extends Controller
{
    /**
     * Every request, newest first, with the seats taken out of the capacity.
     */
    public function index(Request $request, MentorshipSettings $settings): JsonResponse
    {
        $requests = MentorshipRequest::query()->with('student')->latest()->latest('id')->get();

        return response()->json([
            'data' => MentorshipRequestResource::collection($requests)->resolve($request),
            'meta' => ['capacity' => $settings->all()['capacity'], 'seats_left' => $settings->seatsLeft()],
        ]);
    }

    /**
     * Save the owner's private notes on a request.
     */
    public function update(Request $request, MentorshipRequest $mentorshipRequest): MentorshipRequestResource
    {
        $mentorshipRequest->update($request->validate(['notes' => ['nullable', 'string', 'max:5000']]));

        return new MentorshipRequestResource($mentorshipRequest->load('student'));
    }

    /**
     * Delete a request with its payment receipt.
     */
    public function destroy(MentorshipRequest $mentorshipRequest): Response
    {
        $mentorshipRequest->delete();

        return response()->noContent();
    }
}
