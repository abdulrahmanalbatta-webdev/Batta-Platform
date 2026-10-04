<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    /**
     * The activity log, newest first, 20 at a time (meta.next_cursor fetches the next page); ?limit= for a short list.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = $request->integer('limit', 20);

        return ActivityResource::collection(
            Activity::query()->with('user')->latest()->latest('id')->cursorPaginate(max(1, min($limit, 50))),
        );
    }
}
