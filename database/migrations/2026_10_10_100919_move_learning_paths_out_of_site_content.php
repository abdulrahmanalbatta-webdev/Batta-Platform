<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Learning paths get their own page on the dashboard: the paths saved on "محتوى الموقع" (or, never edited, the
     * three the site started with) become rows of learning_paths, published as they were.
     */
    public function up(): void
    {
        $saved = DB::table('site_blocks')->where('key', 'paths')->value('value');
        $paths = is_string($saved)
            ? collect(json_decode($saved, true) ?: [])->map(fn (array $path): array => ['slug' => $path['id'] ?? '', ...$path])->all()
            : json_decode((string) file_get_contents(resource_path('data/learning-paths.json')), true);

        foreach (array_values($paths) as $position => $path) {
            DB::table('learning_paths')->insert([
                'slug' => $path['slug'],
                'title' => $path['title'],
                'icon' => $path['icon'],
                'summary' => $path['summary'] ?? null,
                'audience' => $path['audience'] ?? null,
                'duration' => $path['duration'] ?? null,
                'outcomes' => json_encode($path['outcomes'] ?? [], JSON_UNESCAPED_UNICODE),
                'stages' => json_encode(collect($path['stages'] ?? [])->map(fn (array $stage): array => [
                    'title' => $stage['title'],
                    'text' => $stage['text'] ?? null,
                    'topics' => $stage['topics'] ?? [],
                    'resources' => $stage['resources'] ?? [],
                    'items' => $stage['items'] ?? [],
                ])->all(), JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'position' => $position + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('site_blocks')->where('key', 'paths')->delete();
        Cache::forget('site.content');
    }

    /**
     * Reverse the migrations: the paths go back to the site content (without their linked courses, workshops and articles).
     */
    public function down(): void
    {
        $paths = DB::table('learning_paths')->orderBy('position')->orderBy('id')->get()->map(fn (object $path): array => [
            'id' => $path->slug,
            'title' => $path->title,
            'icon' => $path->icon,
            'summary' => (string) $path->summary,
            'audience' => (string) $path->audience,
            'duration' => (string) $path->duration,
            'outcomes' => json_decode((string) $path->outcomes, true) ?: [],
            'stages' => collect(json_decode((string) $path->stages, true) ?: [])->map(fn (array $stage): array => [
                'title' => $stage['title'],
                'text' => (string) ($stage['text'] ?? ''),
                'topics' => $stage['topics'] ?? [],
                'resources' => $stage['resources'] ?? [],
            ])->all(),
        ]);

        DB::table('learning_paths')->delete();
        DB::table('site_blocks')->updateOrInsert(['key' => 'paths'], ['value' => json_encode($paths, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        Cache::forget('site.content');
    }
};
