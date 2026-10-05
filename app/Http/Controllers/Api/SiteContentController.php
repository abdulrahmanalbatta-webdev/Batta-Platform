<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteContentController extends Controller
{
    /**
     * Every section of the public site's content, with the schema the editor is drawn from.
     */
    public function show(SiteContent $content): JsonResponse
    {
        return response()->json([
            'data' => $content->all(),
            'meta' => [
                'definitions' => SiteContent::definitions(),
                'groups' => SiteContent::groups(),
                'icons' => SiteContent::ICONS,
            ],
        ]);
    }

    /**
     * Save one section; the site shows it on its next visit.
     */
    public function update(Request $request, string $key, SiteContent $content): JsonResponse
    {
        $definition = SiteContent::definitions()[$key] ?? abort(404);

        $request->validate(SiteContent::rules($definition), [], SiteContent::attributes($definition));

        $value = $content->update($key, $request->input('value'));
        $this->log($request, $definition['label']);

        return response()->json(['data' => $value]);
    }

    /**
     * Back to the site's original text for this section.
     */
    public function destroy(Request $request, string $key, SiteContent $content): JsonResponse
    {
        $definition = SiteContent::definitions()[$key] ?? abort(404);

        $content->reset($key);
        $this->log($request, $definition['label']);

        return response()->json(['data' => $content->all()[$key]]);
    }

    private function log(Request $request, string $section): void
    {
        Activity::create(['user_id' => $request->user()->id, 'action' => 'updated', 'subject_name' => 'محتوى الموقع: '.$section]);
    }
}
