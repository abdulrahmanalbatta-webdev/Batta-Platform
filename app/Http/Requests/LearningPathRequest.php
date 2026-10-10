<?php

namespace App\Http\Requests;

use App\Models\Article;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Workshop;
use App\Support\SiteContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The path editor saves the whole path (POST, PUT); the list's show/hide switch sends only is_published (PATCH).
 */
class LearningPathRequest extends FormRequest
{
    public const MAX_STAGES = 15;

    public const MAX_RESOURCES = 12;

    public const MAX_ITEMS = 10;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'title' => [$required, 'string', 'max:80'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('learning_paths')->ignore($this->route('learning_path'))],
            'icon' => [$required, Rule::in(array_keys(SiteContent::ICONS))],
            'summary' => ['sometimes', 'nullable', 'string', 'max:300'],
            'audience' => ['sometimes', 'nullable', 'string', 'max:120'],
            'duration' => ['sometimes', 'nullable', 'string', 'max:30'],
            'outcomes' => ['sometimes', 'array', 'max:8'],
            'outcomes.*' => ['string', 'max:120'],
            'is_published' => ['sometimes', 'boolean'],

            'stages' => ['sometimes', 'array', 'max:'.self::MAX_STAGES],
            'stages.*' => ['array'],
            'stages.*.title' => ['required', 'string', 'max:80'],
            'stages.*.text' => ['nullable', 'string', 'max:600'],
            'stages.*.topics' => ['array', 'max:15'],
            'stages.*.topics.*' => ['string', 'max:40'],
            'stages.*.resources' => ['array', 'max:'.self::MAX_RESOURCES],
            'stages.*.resources.*.title' => ['required', 'string', 'max:100'],
            'stages.*.resources.*.url' => ['required', 'url:http,https', 'max:255'],
            'stages.*.resources.*.type' => ['required', Rule::in(array_keys(LearningPath::RESOURCE_TYPES))],
            'stages.*.resources.*.lang' => ['required', Rule::in(array_keys(LearningPath::LANGUAGES))],
            'stages.*.items' => ['array', 'max:'.self::MAX_ITEMS],
            'stages.*.items.*.type' => ['required', Rule::in(array_keys(LearningPath::ITEM_TYPES))],
            'stages.*.items.*.id' => ['required', 'integer'],
        ];
    }

    /**
     * Every linked course, workshop and article must exist.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $models = ['course' => Course::class, 'workshop' => Workshop::class, 'article' => Article::class];

            foreach ($this->input('stages', []) as $s => $stage) {
                foreach ($stage['items'] ?? [] as $i => $item) {
                    if (! $models[$item['type']]::query()->whereKey($item['id'])->exists()) {
                        $validator->errors()->add("stages.$s.items.$i.id", 'هذا المحتوى لم يعد موجوداً، احذفه من المرحلة.');
                    }
                }
            }
        }];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'اسم المسار',
            'slug' => 'الرابط',
            'icon' => 'الأيقونة',
            'summary' => 'الوصف المختصر',
            'audience' => 'لمن هذا المسار',
            'duration' => 'المدة',
            'outcomes' => 'المخرجات',
            'outcomes.*' => 'المخرج',
            'stages' => 'المراحل',
            'stages.*.title' => 'عنوان المرحلة',
            'stages.*.text' => 'وصف المرحلة',
            'stages.*.topics' => 'المواضيع',
            'stages.*.topics.*' => 'الموضوع',
            'stages.*.resources' => 'المصادر',
            'stages.*.resources.*.title' => 'اسم المصدر',
            'stages.*.resources.*.url' => 'رابط المصدر',
            'stages.*.resources.*.type' => 'نوع المصدر',
            'stages.*.resources.*.lang' => 'لغة المصدر',
            'stages.*.items' => 'المحتوى المرتبط',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'الرابط: حروف إنجليزية صغيرة وأرقام وشرطات فقط.',
        ];
    }

    /**
     * The path's columns, with the stages kept to their known fields (the slug is set by the controller).
     *
     * @return array<string, mixed>
     */
    public function pathAttributes(): array
    {
        $attributes = $this->safe()->except(['slug', 'stages']);

        if ($this->has('stages')) {
            $attributes['stages'] = collect($this->validated('stages'))->map(fn (array $stage): array => [
                'title' => $stage['title'],
                'text' => $stage['text'] ?? null,
                'topics' => array_values($stage['topics'] ?? []),
                'resources' => collect($stage['resources'] ?? [])->map(fn (array $resource): array => [
                    'title' => $resource['title'],
                    'url' => $resource['url'],
                    'type' => $resource['type'],
                    'lang' => $resource['lang'],
                ])->values()->all(),
                'items' => collect($stage['items'] ?? [])->map(fn (array $item): array => ['type' => $item['type'], 'id' => (int) $item['id']])
                    ->unique(fn (array $item): string => $item['type'].$item['id'])->values()->all(),
            ])->values()->all();
        }

        return $attributes;
    }
}
