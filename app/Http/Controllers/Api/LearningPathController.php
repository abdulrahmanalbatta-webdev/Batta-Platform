<?php

namespace App\Http\Controllers\Api;

use App\Actions\MoveInOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\LearningPathRequest;
use App\Http\Resources\LearningPathResource;
use App\Models\LearningPath;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LearningPathController extends Controller
{
    /**
     * Every path in the site's order, with how many stages, resources and linked items each holds.
     */
    public function index(): AnonymousResourceCollection
    {
        return LearningPathResource::collection(LearningPath::query()->ordered()->get());
    }

    public function show(LearningPath $learningPath): LearningPathResource
    {
        return (new LearningPathResource($learningPath))->withContent();
    }

    /**
     * Add a path at the end of the list; without a slug one is made from the title.
     */
    public function store(LearningPathRequest $request, MoveInOrder $order): LearningPathResource
    {
        $path = new LearningPath($request->pathAttributes());
        $path->slug = $request->filled('slug') ? $request->validated('slug') : Slug::unique(LearningPath::class, $path->title, 'path');
        $path->position = $order->nextPosition(LearningPath::class);
        $path->save();

        return (new LearningPathResource($path))->withContent();
    }

    /**
     * Save the path editor, or (PATCH) show or hide the path from the list.
     */
    public function update(LearningPathRequest $request, LearningPath $learningPath): LearningPathResource
    {
        $learningPath->fill($request->pathAttributes());

        if ($request->filled('slug')) {
            $learningPath->slug = $request->validated('slug');
        }

        $learningPath->save();

        return (new LearningPathResource($learningPath))->withContent();
    }

    public function destroy(LearningPath $learningPath): Response
    {
        $learningPath->delete();

        return response()->noContent();
    }

    /**
     * Move the path one place up or down the site's list.
     *
     * @throws ValidationException
     */
    public function move(Request $request, LearningPath $learningPath, MoveInOrder $order): AnonymousResourceCollection
    {
        $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        if (! $order->handle($learningPath, $request->input('direction'))) {
            throw ValidationException::withMessages(['direction' => 'المسار في طرف القائمة بالفعل.']);
        }

        return $this->index();
    }
}
