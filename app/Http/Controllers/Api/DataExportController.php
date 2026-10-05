<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ExportPlatformData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    /**
     * The exports waiting to be downloaded, newest first.
     */
    public function index(): JsonResponse
    {
        $disk = Storage::disk(ExportPlatformData::DISK);

        return response()->json([
            'data' => collect($disk->files(ExportPlatformData::DIRECTORY))
                ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
                ->map(fn (string $path): array => [
                    'name' => basename($path),
                    'size' => $disk->size($path),
                    'created_at' => date(DATE_ATOM, $disk->lastModified($path)),
                    'url' => route('api.data-exports.show', basename($path)),
                ])
                ->sortByDesc('created_at')
                ->values(),
            'meta' => ['keep_days' => ExportPlatformData::KEEP_DAYS],
        ]);
    }

    /**
     * Start an export in the background; the bell says when it's ready.
     */
    public function store(Request $request): JsonResponse
    {
        ExportPlatformData::dispatch($request->user());

        return response()->json(['message' => 'بدأ تجهيز النسخة، وسيصلك إشعار عند جاهزيتها.'], 202);
    }

    public function show(string $file): StreamedResponse
    {
        return Storage::disk(ExportPlatformData::DISK)->download($this->path($file));
    }

    public function destroy(string $file): Response
    {
        Storage::disk(ExportPlatformData::DISK)->delete($this->path($file));

        return response()->noContent();
    }

    /**
     * Only files the export job names, inside the exports folder.
     */
    private function path(string $file): string
    {
        abort_unless(preg_match('/^batta-export-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/', $file) === 1, 404);

        $path = ExportPlatformData::DIRECTORY.'/'.$file;
        abort_unless(Storage::disk(ExportPlatformData::DISK)->exists($path), 404);

        return $path;
    }
}
