<?php

namespace App\Http\Requests;

use App\Enums\LeadService;
use App\Enums\LeadStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a request (POST) needs the client's name and the service; updating (PATCH) accepts any subset, e.g. only the stage when a card is dragged.
 */
class LeadRequest extends FormRequest
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
            'name' => [$required, 'string', 'max:120'],
            'company' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'service' => [$required, Rule::enum(LeadService::class)],
            'budget' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'stage' => ['sometimes', Rule::enum(LeadStage::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
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
            'name' => 'اسم العميل',
            'company' => 'الجهة',
            'email' => 'البريد الإلكتروني',
            'phone' => 'الهاتف',
            'service' => 'الخدمة',
            'budget' => 'الميزانية',
            'stage' => 'المرحلة',
            'note' => 'الملاحظات',
        ];
    }
}
