<?php

namespace App\Console\Commands;

use App\Actions\MoveInOrder;
use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Enums\Role;
use App\Models\Article;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Adds the articles and tools written in the repository's content/ folder to the dashboard, as drafts the
 * owner reviews and publishes there. Only new ones are added (an article by its slug, a tool by its name):
 * whatever was edited, published or deleted in the dashboard is never touched again. Runs on every deploy.
 *
 * content/articles/*.md: a "---" header of key: value lines (title, slug, category, excerpt, meta_title,
 * meta_description), then the Markdown body. content/tools.json: [{category, name, short, color, why, since, url}].
 */
#[Signature('content:import {--path= : The content folder (default: the repository\'s content/)}')]
#[Description('Add new articles and tools from the content/ folder as drafts')]
class ImportContent extends Command
{
    public function handle(MoveInOrder $order): int
    {
        $path = $this->option('path') ?: base_path('content');
        $author = User::query()->where('role', Role::Owner)->oldest('id')->first();
        $added = 0;

        foreach (glob($path.'/articles/*.md') ?: [] as $file) {
            $added += $this->importArticle($file, $author) ? 1 : 0;
        }

        $tools = is_file($path.'/tools.json') ? json_decode((string) file_get_contents($path.'/tools.json'), true) : [];
        foreach (is_array($tools) ? $tools : [] as $tool) {
            $added += $this->importTool((array) $tool, $order) ? 1 : 0;
        }

        $this->info("Added {$added} draft(s).");

        return self::SUCCESS;
    }

    private function importArticle(string $file, ?User $author): bool
    {
        [$meta, $body] = $this->parse((string) file_get_contents($file));
        $meta['slug'] ??= pathinfo($file, PATHINFO_FILENAME);

        if (Article::query()->where('slug', $meta['slug'])->exists()) {
            return false;
        }

        $validator = Validator::make([...$meta, 'body' => $body], [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['required', Rule::enum(ArticleCategory::class)],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            $this->warn(basename($file).': '.$validator->errors()->first());

            return false;
        }

        $article = new Article([...$validator->validated(), 'status' => ArticleStatus::Draft]);
        $article->author_id = $author?->id;
        $article->save();
        $this->line("  + article: {$article->title}");

        return true;
    }

    private function importTool(array $tool, MoveInOrder $order): bool
    {
        if (blank($tool['name'] ?? null) || Tool::query()->where('name', $tool['name'])->exists()) {
            return false;
        }

        $validator = Validator::make($tool, [
            'category' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:40'],
            'short' => ['required', 'string', 'max:3'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'why' => ['required', 'string', 'max:120'],
            'since' => ['required', 'integer', 'between:1990,2100'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
        ]);
        if ($validator->fails()) {
            $this->warn("{$tool['name']}: ".$validator->errors()->first());

            return false;
        }

        $data = $validator->validated();
        $category = ToolCategory::query()->where('name', $data['category'])->first();
        if ($category === null) {
            $category = new ToolCategory(['name' => $data['category'], 'color' => $data['color']]);
            $category->position = $order->nextPosition(ToolCategory::class);
            $category->save();
        }
        $model = new Tool([...Arr::except($data, 'category'), 'tool_category_id' => $category->id, 'is_published' => false]);
        $model->position = $order->nextPosition(Tool::class);
        $model->save();
        $this->line("  + tool: {$model->name}");

        return true;
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function parse(string $source): array
    {
        $source = str_replace("\r\n", "\n", $source);
        if (! preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $source, $parts)) {
            return [[], trim($source)];
        }

        $meta = collect(explode("\n", $parts[1]))
            ->filter(fn (string $line): bool => str_contains($line, ':'))
            ->mapWithKeys(fn (string $line): array => [trim(Str::before($line, ':')) => trim(trim(Str::after($line, ':')), '"\'')])
            ->filter(fn (string $value): bool => $value !== '')
            ->all();

        return [$meta, trim($parts[2])];
    }
}
