<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($this->user())],
            'title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]+$/'],
            'bio' => ['nullable', 'string', 'max:280'],
            'github' => ['nullable', 'string', 'max:39', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/'],
            'linkedin' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-_]+$/'],
            // changing the sign-in address needs the password, so a borrowed session can't take the account
            'current_password' => [Rule::requiredIf(fn (): bool => $this->changesEmail()), 'nullable', 'string', 'current_password'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['current_password' => 'كلمة المرور الحالية'];
    }

    public function changesEmail(): bool
    {
        return is_string($this->input('email')) && $this->input('email') !== $this->user()->email;
    }

    /**
     * Normalise the email before validation so "Me@Batta.dev" is accepted as "me@batta.dev".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
