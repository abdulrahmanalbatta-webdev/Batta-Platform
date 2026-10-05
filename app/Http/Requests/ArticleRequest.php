<?php

namespace App\Http\Requests;

use App\Enums\ArticleCategory;
use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ArticleRequest extends FormRequest
{
    /**
     * Articles need at least this many words to go live.
     */
    public const MIN_PUBLISHED_WORDS = 30;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:200000'],
            'category' => ['required', Rule::enum(ArticleCategory::class)],
            'status' => ['required', Rule::enum(ArticleStatus::class)],
            'publish_at' => ['nullable', 'required_if:status,'.ArticleStatus::Scheduled->value, 'date', 'after:now'],
            'is_featured' => ['boolean'],
            'send_newsletter' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->enum('status', ArticleStatus::class) !== ArticleStatus::Published) {
                    return;
                }

                $words = (new Article(['body' => $this->input('body')]))->wordCount();

                if ($words < self::MIN_PUBLISHED_WORDS) {
                    $validator->errors()->add('body', 'المقال قصير جداً للنشر (أقل من '.self::MIN_PUBLISHED_WORDS.' كلمة).');
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
            'title' => 'عنوان المقال',
            'excerpt' => 'المقدمة',
            'body' => 'المحتوى',
            'category' => 'التصنيف',
            'status' => 'الحالة',
            'publish_at' => 'موعد النشر',
            'meta_title' => 'عنوان SEO',
            'meta_description' => 'وصف SEO',
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
            'publish_at.required_if' => 'اختر موعد نشر للمقال المجدول.',
            'publish_at.after' => 'اختر موعد نشر في المستقبل للمقال المجدول.',
        ];
    }

    /**
     * Only a scheduled article keeps a publish time.
     *
     * @return array<string, mixed>
     */
    public function articleAttributes(): array
    {
        $data = $this->validated();

        if ($this->enum('status', ArticleStatus::class) !== ArticleStatus::Scheduled) {
            $data['publish_at'] = null;
        }

        return $data;
    }

    /**
     * The scheduled time only matters for scheduled articles; drop it for the rest before validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('status') !== ArticleStatus::Scheduled->value) {
            $this->merge(['publish_at' => null]);
        }
    }
}
