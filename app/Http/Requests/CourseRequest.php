<?php

namespace App\Http\Requests;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use App\Support\Duration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CourseRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('courses')->ignore($this->route('course'))],
            'short_description' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:5000'],
            'outcomes' => ['array', 'max:20'],
            'outcomes.*' => ['string', 'max:200'],
            'tags' => ['array', 'max:15'],
            'tags.*' => ['string', 'max:40'],
            'level' => ['required', Rule::enum(CourseLevel::class)],
            'category' => ['required', Rule::enum(CourseCategory::class)],
            'status' => ['required', Rule::enum(CourseStatus::class)],
            'publish_at' => ['nullable', 'date_format:Y-m-d'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'old_price' => ['nullable', 'numeric', 'gt:price', 'max:100000'],
            'has_regional_pricing' => ['boolean'],
            'is_included_in_pro' => ['boolean'],
            'has_certificate' => ['boolean'],
            'allows_questions' => ['boolean'],
            'modules' => ['array', 'max:50'],
            'modules.*.id' => ['nullable', 'integer'],
            'modules.*.title' => ['nullable', 'string', 'max:255'],
            'modules.*.lessons' => ['array', 'max:200'],
            'modules.*.lessons.*.id' => ['nullable', 'integer'],
            'modules.*.lessons.*.title' => ['nullable', 'string', 'max:255'],
            'modules.*.lessons.*.duration' => ['nullable', 'string', 'regex:'.Duration::PATTERN],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->enum('status', CourseStatus::class) !== CourseStatus::Published) {
                    return;
                }

                $hasLesson = collect($this->input('modules', []))
                    ->flatMap(fn (array $module): array => $module['lessons'] ?? [])
                    ->contains(fn (array $lesson): bool => filled($lesson['title'] ?? null));

                if (! $hasLesson) {
                    $validator->errors()->add('modules', 'أضف درساً واحداً على الأقل قبل النشر.');
                }
            },
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'عنوان الدورة',
            'slug' => 'الرابط',
            'short_description' => 'الوصف المختصر',
            'description' => 'الوصف',
            'outcomes.*' => 'مخرج التعلم',
            'tags.*' => 'الوسم',
            'level' => 'المستوى',
            'category' => 'التصنيف',
            'status' => 'الحالة',
            'publish_at' => 'تاريخ النشر',
            'price' => 'السعر',
            'old_price' => 'السعر قبل الخصم',
            'modules.*.title' => 'عنوان الوحدة',
            'modules.*.lessons.*.title' => 'عنوان الدرس',
            'modules.*.lessons.*.duration' => 'مدة الدرس',
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
            'old_price.gt' => 'السعر قبل الخصم يجب أن يكون أعلى من السعر الحالي.',
            'modules.*.lessons.*.duration.regex' => 'مدة الدرس بصيغة دقائق:ثوانٍ، مثل 12:40.',
        ];
    }

    /**
     * The course's own columns (the curriculum is saved separately by SyncCurriculum).
     *
     * @return array<string, mixed>
     */
    public function courseAttributes(): array
    {
        return $this->safe()->except(['modules', 'slug']);
    }
}
