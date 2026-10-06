<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ToolResource;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A tool's logo: without one the site shows the tool's short letters on its color.
 */
class ToolLogoController extends Controller
{
    /**
     * Replace the tool's logo.
     */
    public function store(Request $request, Tool $tool): ToolResource
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [], ['logo' => 'الشعار']);

        $previous = $tool->logo_path;
        $tool->forceFill(['logo_path' => $request->file('logo')->store('tools', 'public')])->save();

        if ($previous) {
            Storage::disk('public')->delete($previous);
        }

        return new ToolResource($tool);
    }

    /**
     * Remove the logo, back to the letters.
     */
    public function destroy(Tool $tool): ToolResource
    {
        if ($tool->logo_path) {
            Storage::disk('public')->delete($tool->logo_path);
            $tool->forceFill(['logo_path' => null])->save();
        }

        return new ToolResource($tool);
    }
}
