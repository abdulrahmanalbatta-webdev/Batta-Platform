<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Resources\Site\ToolCategoryResource;
use App\Models\Tool;
use App\Models\ToolCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ToolController extends Controller
{
    /**
     * The published tools grouped by category, in the dashboard's order; empty categories are left out.
     */
    public function index(): AnonymousResourceCollection
    {
        return ToolCategoryResource::collection(
            ToolCategory::query()
                ->whereHas('tools', fn ($query) => $query->published())
                ->with(['tools' => fn ($query) => $query->published()->orderBy('position')->orderBy('id')])
                ->orderBy('position')->orderBy('id')
                ->get(),
        );
    }

    /**
     * Counts a visitor following a published tool's link (the "clicks" column on the dashboard).
     */
    public function click(int $tool): Response
    {
        $tool = Tool::query()->published()->findOrFail($tool);

        DB::table('tools')->where('id', $tool->id)->increment('clicks');

        return response()->noContent();
    }
}
