<?php

namespace App\Http\Controllers\Api;

use App\Actions\MoveInOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ToolRequest;
use App\Http\Resources\ToolResource;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ToolController extends Controller
{
    /**
     * Every tool in its public order.
     */
    public function index(): AnonymousResourceCollection
    {
        return ToolResource::collection(Tool::query()->orderBy('position')->orderBy('id')->get());
    }

    /**
     * Add a tool at the end of the list.
     */
    public function store(ToolRequest $request, MoveInOrder $order): ToolResource
    {
        $tool = new Tool($request->validated());
        $tool->position = $order->nextPosition(Tool::class);
        $tool->save();

        return new ToolResource($tool);
    }

    /**
     * Update any of a tool's fields (the card's publish switch sends only is_published).
     */
    public function update(ToolRequest $request, Tool $tool): ToolResource
    {
        $tool->update($request->validated());

        return new ToolResource($tool);
    }

    public function destroy(Tool $tool): Response
    {
        $tool->delete();

        return response()->noContent();
    }

    /**
     * Move the tool one place up or down.
     *
     * @throws ValidationException
     */
    public function move(Request $request, Tool $tool, MoveInOrder $order): AnonymousResourceCollection
    {
        $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        if (! $order->handle($tool, $request->input('direction'))) {
            throw ValidationException::withMessages(['direction' => 'الأداة في طرف القائمة بالفعل.']);
        }

        return $this->index();
    }
}
