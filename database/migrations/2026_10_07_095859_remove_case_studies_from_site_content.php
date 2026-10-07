<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * The portfolio ("أعمالي") is gone from the site: the saved projects go, with their covers and galleries.
     */
    public function up(): void
    {
        $saved = DB::table('site_blocks')->where('key', 'case_studies')->value('value');
        $projects = is_string($saved) ? json_decode($saved, true) : [];

        $images = collect(is_array($projects) ? $projects : [])
            ->flatMap(fn (mixed $project): array => is_array($project) ? [$project['cover'] ?? null, ...($project['gallery'] ?? [])] : [])
            ->filter(fn (mixed $path): bool => is_string($path) && str_starts_with($path, 'site/images/'))
            ->values()
            ->all();

        if ($images !== []) {
            Storage::disk('public')->delete($images);
        }

        DB::table('site_blocks')->where('key', 'case_studies')->delete();
        Cache::forget('site.content');
    }

    /**
     * Reverse the migrations: nothing to restore, the deleted projects and their images are gone.
     */
    public function down(): void
    {
        //
    }
};
