<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LeadController extends Controller
{
    /**
     * Every project request, newest first (the page groups them into the board's columns).
     */
    public function index(): AnonymousResourceCollection
    {
        return LeadResource::collection(Lead::query()->latest()->latest('id')->get());
    }

    /**
     * Add a request; it starts in the "new" column unless a stage is given.
     */
    public function store(LeadRequest $request): LeadResource
    {
        return new LeadResource(Lead::create($request->validated()));
    }

    /**
     * Move a request to another stage or edit its details.
     */
    public function update(LeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->validated());

        return new LeadResource($lead);
    }

    /**
     * Delete a request; its conversations stay, without the link to it.
     */
    public function destroy(Lead $lead): Response
    {
        $lead->delete();

        return response()->noContent();
    }
}
