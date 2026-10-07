<?php

namespace App\Http\Requests;

use App\Enums\CourseCategory;
use App\Enums\CourseLevel;
use App\Enums\CourseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'has_certificate' => ['boolean'],
            'allows_questions' => ['boolean'],
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
     * The course's own columns (the slug is set by the controller).
     *
     * @return array<string, mixed>
     */
    public function courseAttributes(): array
    {
        return $this->safe()->except(['slug']);
    }
}
