<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SitePhotoController extends Controller
{
    /**
     * Your photo on the public site (home and about pages), replacing the one bundled with the site.
     */
    public function store(Request $request, SiteContent $content): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=300,min_height=300']]);

        $content->setPhoto($request->file('photo')->store(dirname(SiteContent::PHOTO_PATH), 'public'));

        return response()->json(['data' => ['photo' => $content->all()['photo']]]);
    }

    /**
     * Back to the photo bundled with the site.
     */
    public function destroy(SiteContent $content): JsonResponse
    {
        $content->setPhoto(null);

        return response()->json(['data' => ['photo' => null]]);
    }
}
