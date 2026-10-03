<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkshopRequest;
use App\Http\Resources\WorkshopResource;
use App\Models\Workshop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class WorkshopController extends Controller
{
    /**
     * Every workshop, soonest first (the page splits upcoming and past).
     */
    public function index(): AnonymousResourceCollection
    {
        return WorkshopResource::collection(Workshop::query()->orderBy('date')->orderBy('start_time')->get());
    }

    public function store(WorkshopRequest $request): WorkshopResource
    {
        return new WorkshopResource(Workshop::create($request->workshopAttributes()));
    }

    public function update(WorkshopRequest $request, Workshop $workshop): WorkshopResource
    {
        $workshop->update($request->workshopAttributes());

        return new WorkshopResource($workshop);
    }

    public function destroy(Workshop $workshop): Response
    {
        $workshop->delete();

        return response()->noContent();
    }
}
