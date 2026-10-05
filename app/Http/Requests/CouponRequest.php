<?php

namespace App\Http\Requests;

use App\Enums\CouponScope;
use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z0-9_-]{3,20}$/', Rule::unique('coupons')],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'numeric', 'gt:0', Rule::when($this->input('type') === DiscountType::Percent->value, ['max:100'], ['max:100000'])],
            'usage_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'expires_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'scope' => ['required', Rule::enum(CouponScope::class)],
            'course_id' => ['nullable', 'required_if:scope,'.CouponScope::Course->value, 'integer', Rule::exists('courses', 'id')],
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
            'code' => 'الكود',
            'type' => 'نوع الخصم',
            'value' => 'الخصم',
            'usage_limit' => 'حد الاستخدام',
            'expires_on' => 'تاريخ الانتهاء',
            'scope' => 'نطاق الكوبون',
            'course_id' => 'الدورة',
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
            'code.regex' => 'الكود: 3–20 حرفاً إنجليزياً أو رقماً.',
            'code.unique' => 'هذا الكود موجود مسبقاً.',
            'expires_on.after_or_equal' => 'تاريخ الانتهاء يجب أن يكون اليوم أو بعده.',
            'course_id.required_if' => 'اختر الدورة التي ينطبق عليها الكوبون.',
        ];
    }

    /**
     * The coupon's columns: a limit of 0 means unlimited, and only a course coupon keeps a course.
     *
     * @return array<string, mixed>
     */
    public function couponAttributes(): array
    {
        $data = $this->validated();

        return [
            ...$data,
            'usage_limit' => (int) $data['usage_limit'] === 0 ? null : (int) $data['usage_limit'],
            'course_id' => $data['scope'] === CouponScope::Course->value ? $data['course_id'] : null,
        ];
    }

    /**
     * Codes are stored upper-case: "launch30" and "LAUNCH30" are the same coupon.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }
}
