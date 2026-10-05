<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The JSON key file of a Google Cloud service account, as downloaded from the console
 * (type "service_account" with client_email and a readable private_key).
 */
class GoogleServiceAccountKey implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = is_string($value) ? json_decode($value, true) : null;

        $valid = is_array($key)
            && ($key['type'] ?? null) === 'service_account'
            && filter_var($key['client_email'] ?? null, FILTER_VALIDATE_EMAIL)
            && is_string($key['private_key'] ?? null)
            && openssl_pkey_get_private($key['private_key']) !== false;

        if (! $valid) {
            $fail('الصق محتوى ملف JSON الخاص بحساب الخدمة (Service account) كما نزّلته من Google Cloud.');
        }
    }
}
