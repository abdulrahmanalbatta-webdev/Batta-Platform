<?php

namespace App\Http\Controllers\Api;

use App\Actions\MoveInOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ToolCategoryRequest;
use App\Http\Resources\ToolCategoryResource;
use App\Models\ToolCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToolCategoryController extends Controller
{
    /**
     * Categories in filter order, with how many tools each holds.
     */
    public function index(): AnonymousResourceCollection
    {
        return ToolCategoryResource::collection(
            ToolCategory::query()->withCount('tools')->orderBy('position')->orderBy('id')->get(),
        );
    }

    public function store(ToolCategoryRequest $request, MoveInOrder $order): ToolCategoryResource
    {
        $category = new ToolCategory($request->validated());
        $category->position = $order->nextPosition(ToolCategory::class);
        $category->save();

        return new ToolCategoryResource($category->loadCount('tools'));
    }

    /**
     * Rename or recolour a category.
     */
    public function update(ToolCategoryRequest $request, ToolCategory $toolCategory): ToolCategoryResource
    {
        $toolCategory->update($request->validated());

        return new ToolCategoryResource($toolCategory->loadCount('tools'));
    }

    /**
     * Delete a category; a category that still has tools needs "move_to", the category that takes them.
     *
     * @throws ValidationException
     */
    public function destroy(Request $request, ToolCategory $toolCategory): Response
    {
        $request->validate([
            'move_to' => ['nullable', 'integer', Rule::exists('tool_categories', 'id'), Rule::notIn([$toolCategory->id])],
        ]);

        DB::transaction(function () use ($request, $toolCategory): void {
            if ($toolCategory->tools()->exists()) {
                if (! $request->filled('move_to')) {
                    throw ValidationException::withMessages(['move_to' => 'هذا التصنيف فيه أدوات، اختر تصنيفاً تنتقل إليه أولاً.']);
                }

                $toolCategory->tools()->update(['tool_category_id' => $request->integer('move_to')]);
            }

            $toolCategory->delete();
        });

        return response()->noContent();
    }

    /**
     * Move the category one place up or down.
     *
     * @throws ValidationException
     */
    public function move(Request $request, ToolCategory $toolCategory, MoveInOrder $order): AnonymousResourceCollection
    {
        $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        if (! $order->handle($toolCategory, $request->input('direction'))) {
            throw ValidationException::withMessages(['direction' => 'التصنيف في طرف القائمة بالفعل.']);
        }

        return $this->index();
    }
}
