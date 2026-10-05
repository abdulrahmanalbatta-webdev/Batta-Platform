<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating a tool (POST) needs every required field; updating (PATCH) accepts any subset, e.g. only is_published.
 */
class ToolRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:40', Rule::unique('tools')->ignore($this->route('tool'))],
            'tool_category_id' => [$required, 'integer', Rule::exists('tool_categories', 'id')],
            'short' => [$required, 'string', 'max:3'],
            'color' => [$required, 'hex_color'],
            'why' => [$required, 'string', 'max:120'],
            'since' => [$required, 'integer', 'between:2000,'.(now()->year + 1)],
            'url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'is_affiliate' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
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
            'name' => 'اسم الأداة',
            'tool_category_id' => 'التصنيف',
            'short' => 'الرمز',
            'color' => 'اللون',
            'why' => 'سبب الاستخدام',
            'since' => 'سنة البدء',
            'url' => 'الرابط',
        ];
    }
}
