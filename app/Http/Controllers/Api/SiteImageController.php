<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteImageController extends Controller
{
    /**
     * An image for the site content (a project's cover or gallery). It is used once the section is saved; images
     * the content stops using are deleted when it is saved again.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']], [], ['image' => 'الصورة']);

        $path = $request->file('image')->store(SiteContent::IMAGES_DIR, 'public');

        return response()->json(['data' => ['path' => $path, 'url' => Storage::disk('public')->url($path)]], 201);
    }
}
