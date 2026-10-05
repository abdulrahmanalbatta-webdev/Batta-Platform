<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\WorkshopResource;
use App\Models\Workshop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkshopController extends Controller
{
    /**
     * Workshops from today on, soonest first, with the seats left.
     */
    public function index(): AnonymousResourceCollection
    {
        return WorkshopResource::collection(
            Workshop::query()
                ->whereDate('date', '>=', today())
                ->withCount('registrations')
                ->orderBy('date')->orderBy('start_time')
                ->get(),
        );
    }
}
