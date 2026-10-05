<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Arabic messages for the validator. Field names come from "attributes"
    | at the bottom of this file.
    |
    */

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'حقل :attribute يجب أن يكون رابطاً صحيحاً.',
    'after' => 'حقل :attribute يجب أن يكون تاريخاً بعد :date.',
    'after_or_equal' => 'حقل :attribute يجب أن يكون تاريخاً بعد :date أو يساويه.',
    'alpha' => 'حقل :attribute يجب أن يحتوي على حروف فقط.',
    'alpha_dash' => 'حقل :attribute يجب أن يحتوي على حروف وأرقام وشرطات فقط.',
    'alpha_num' => 'حقل :attribute يجب أن يحتوي على حروف وأرقام فقط.',
    'any_of' => 'حقل :attribute غير صالح.',
    'array' => 'حقل :attribute يجب أن يكون مصفوفة.',
    'array_keys' => 'حقل :attribute يجب أن يحتوي على المفاتيح التالية فقط: :values.',
    'ascii' => 'حقل :attribute يجب أن يحتوي على حروف ورموز إنجليزية فقط.',
    'base64' => 'حقل :attribute يجب أن يكون نصاً صالحاً بترميز Base64.',
    'before' => 'حقل :attribute يجب أن يكون تاريخاً قبل :date.',
    'before_or_equal' => 'حقل :attribute يجب أن يكون تاريخاً قبل :date أو يساويه.',
    'between' => [
        'array' => 'حقل :attribute يجب أن يحتوي على عدد عناصر بين :min و :max.',
        'file' => 'حجم :attribute يجب أن يكون بين :min و :max كيلوبايت.',
        'numeric' => 'قيمة :attribute يجب أن تكون بين :min و :max.',
        'string' => 'طول :attribute يجب أن يكون بين :min و :max حرفاً.',
    ],
    'boolean' => 'حقل :attribute يجب أن يكون نعم أو لا.',
    'can' => 'حقل :attribute يحتوي على قيمة غير مسموح بها.',
    'confirmed' => 'تأكيد :attribute غير متطابق.',
    'contains' => 'حقل :attribute ينقصه قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'حقل :attribute يجب أن يكون تاريخاً صحيحاً.',
    'date_equals' => 'حقل :attribute يجب أن يكون تاريخاً مساوياً لـ :date.',
    'date_format' => 'حقل :attribute يجب أن يطابق الصيغة :format.',
    'decimal' => 'حقل :attribute يجب أن يحتوي على :decimal منازل عشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other هو :value.',
    'different' => 'حقل :attribute و :other يجب أن يكونا مختلفين.',
    'digits' => 'حقل :attribute يجب أن يتكون من :digits أرقام.',
    'digits_between' => 'حقل :attribute يجب أن يتكون من :min إلى :max أرقام.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'doesnt_contain' => 'حقل :attribute يجب ألا يحتوي على أي من: :values.',
    'doesnt_end_with' => 'حقل :attribute يجب ألا ينتهي بأي من: :values.',
    'doesnt_start_with' => 'حقل :attribute يجب ألا يبدأ بأي من: :values.',
    'email' => 'حقل :attribute يجب أن يكون بريداً إلكترونياً صحيحاً.',
    'encoding' => 'حقل :attribute يجب أن يكون بترميز :encoding.',
    'ends_with' => 'حقل :attribute يجب أن ينتهي بأحد القيم التالية: :values.',
    'enum' => 'قيمة :attribute المختارة غير صالحة.',
    'exists' => 'قيمة :attribute المختارة غير موجودة.',
    'extensions' => 'حقل :attribute يجب أن يكون بأحد الامتدادات التالية: :values.',
    'file' => 'حقل :attribute يجب أن يكون ملفاً.',
    'filled' => 'حقل :attribute يجب أن يحتوي على قيمة.',
    'gt' => [
        'array' => 'حقل :attribute يجب أن يحتوي على أكثر من :value عناصر.',
        'file' => 'حجم :attribute يجب أن يكون أكبر من :value كيلوبايت.',
        'numeric' => 'قيمة :attribute يجب أن تكون أكبر من :value.',
        'string' => 'طول :attribute يجب أن يكون أكثر من :value حرفاً.',
    ],
    'gte' => [
        'array' => 'حقل :attribute يجب أن يحتوي على :value عناصر أو أكثر.',
        'file' => 'حجم :attribute يجب أن يكون :value كيلوبايت أو أكثر.',
        'numeric' => 'قيمة :attribute يجب أن تكون :value أو أكثر.',
        'string' => 'طول :attribute يجب أن يكون :value حرفاً أو أكثر.',
    ],
    'hex_color' => 'حقل :attribute يجب أن يكون لوناً صحيحاً بصيغة HEX.',
    'image' => 'حقل :attribute يجب أن يكون صورة.',
    'in' => 'قيمة :attribute المختارة غير صالحة.',
    'in_array' => 'حقل :attribute يجب أن يكون موجوداً في :other.',
    'in_array_keys' => 'حقل :attribute يجب أن يحتوي على أحد المفاتيح التالية: :values.',
    'integer' => 'حقل :attribute يجب أن يكون عدداً صحيحاً.',
    'ip' => 'حقل :attribute يجب أن يكون عنوان IP صحيحاً.',
    'ipv4' => 'حقل :attribute يجب أن يكون عنوان IPv4 صحيحاً.',
    'ipv6' => 'حقل :attribute يجب أن يكون عنوان IPv6 صحيحاً.',
    'json' => 'حقل :attribute يجب أن يكون نص JSON صحيحاً.',
    'list' => 'حقل :attribute يجب أن يكون قائمة.',
    'lowercase' => 'حقل :attribute يجب أن يكون بحروف صغيرة.',
    'lt' => [
        'array' => 'حقل :attribute يجب أن يحتوي على أقل من :value عناصر.',
        'file' => 'حجم :attribute يجب أن يكون أقل من :value كيلوبايت.',
        'numeric' => 'قيمة :attribute يجب أن تكون أقل من :value.',
        'string' => 'طول :attribute يجب أن يكون أقل من :value حرفاً.',
    ],
    'lte' => [
        'array' => 'حقل :attribute يجب ألا يحتوي على أكثر من :value عناصر.',
        'file' => 'حجم :attribute يجب ألا يتجاوز :value كيلوبايت.',
        'numeric' => 'قيمة :attribute يجب ألا تتجاوز :value.',
        'string' => 'طول :attribute يجب ألا يتجاوز :value حرفاً.',
    ],
    'mac_address' => 'حقل :attribute يجب أن يكون عنوان MAC صحيحاً.',
    'max' => [
        'array' => 'حقل :attribute يجب ألا يحتوي على أكثر من :max عناصر.',
        'file' => 'حجم :attribute يجب ألا يتجاوز :max كيلوبايت.',
        'numeric' => 'قيمة :attribute يجب ألا تتجاوز :max.',
        'string' => 'طول :attribute يجب ألا يتجاوز :max حرفاً.',
    ],
    'max_digits' => 'حقل :attribute يجب ألا يحتوي على أكثر من :max أرقام.',
    'mimes' => 'حقل :attribute يجب أن يكون ملفاً من نوع: :values.',
    'mimetypes' => 'حقل :attribute يجب أن يكون ملفاً من نوع: :values.',
    'min' => [
        'array' => 'حقل :attribute يجب أن يحتوي على :min عناصر على الأقل.',
        'file' => 'حجم :attribute يجب أن يكون :min كيلوبايت على الأقل.',
        'numeric' => 'قيمة :attribute يجب أن تكون :min على الأقل.',
        'string' => 'طول :attribute يجب أن يكون :min أحرف على الأقل.',
    ],
    'min_digits' => 'حقل :attribute يجب أن يحتوي على :min أرقام على الأقل.',
    'missing' => 'حقل :attribute يجب أن يكون غير موجود.',
    'missing_if' => 'حقل :attribute يجب أن يكون غير موجود عندما يكون :other هو :value.',
    'missing_unless' => 'حقل :attribute يجب أن يكون غير موجود ما لم يكن :other هو :value.',
    'missing_with' => 'حقل :attribute يجب أن يكون غير موجود عند وجود :values.',
    'missing_with_all' => 'حقل :attribute يجب أن يكون غير موجود عند وجود :values.',
    'multiple_of' => 'حقل :attribute يجب أن يكون من مضاعفات :value.',
    'not_in' => 'قيمة :attribute المختارة غير صالحة.',
    'not_regex' => 'صيغة حقل :attribute غير صالحة.',
    'numeric' => 'حقل :attribute يجب أن يكون رقماً.',
    'password' => [
        'letters' => 'حقل :attribute يجب أن يحتوي على حرف واحد على الأقل.',
        'mixed' => 'حقل :attribute يجب أن يحتوي على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'حقل :attribute يجب أن يحتوي على رقم واحد على الأقل.',
        'symbols' => 'حقل :attribute يجب أن يحتوي على رمز واحد على الأقل.',
        'uncompromised' => ':attribute ظهرت في تسريب بيانات سابق، اختر :attribute مختلفة.',
    ],
    'present' => 'حقل :attribute يجب أن يكون موجوداً.',
    'present_if' => 'حقل :attribute يجب أن يكون موجوداً عندما يكون :other هو :value.',
    'present_unless' => 'حقل :attribute يجب أن يكون موجوداً ما لم يكن :other هو :value.',
    'present_with' => 'حقل :attribute يجب أن يكون موجوداً عند وجود :values.',
    'present_with_all' => 'حقل :attribute يجب أن يكون موجوداً عند وجود :values.',
    'prohibited' => 'حقل :attribute غير مسموح به.',
    'prohibited_if' => 'حقل :attribute غير مسموح به عندما يكون :other هو :value.',
    'prohibited_if_accepted' => 'حقل :attribute غير مسموح به عند قبول :other.',
    'prohibited_if_declined' => 'حقل :attribute غير مسموح به عند رفض :other.',
    'prohibited_unless' => 'حقل :attribute غير مسموح به ما لم يكن :other ضمن :values.',
    'prohibits' => 'حقل :attribute يمنع وجود :other.',
    'regex' => 'صيغة حقل :attribute غير صالحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'حقل :attribute يجب أن يحتوي على: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عند قبول :other.',
    'required_if_declined' => 'حقل :attribute مطلوب عند رفض :other.',
    'required_unless' => 'حقل :attribute مطلوب ما لم يكن :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أي من :values.',
    'same' => 'حقل :attribute يجب أن يطابق :other.',
    'size' => [
        'array' => 'حقل :attribute يجب أن يحتوي على :size عناصر.',
        'file' => 'حجم :attribute يجب أن يكون :size كيلوبايت.',
        'numeric' => 'قيمة :attribute يجب أن تكون :size.',
        'string' => 'طول :attribute يجب أن يكون :size حرفاً.',
    ],
    'starts_with' => 'حقل :attribute يجب أن يبدأ بأحد القيم التالية: :values.',
    'string' => 'حقل :attribute يجب أن يكون نصاً.',
    'timezone' => 'حقل :attribute يجب أن يكون منطقة زمنية صحيحة.',
    'unique' => ':attribute مستخدم مسبقاً.',
    'uploaded' => 'تعذّر رفع :attribute، غالباً لأن حجمها أكبر من الحد الذي يقبله الخادم.',
    'uppercase' => 'حقل :attribute يجب أن يكون بحروف كبيرة.',
    'url' => 'حقل :attribute يجب أن يكون رابطاً صحيحاً.',
    'ulid' => 'حقل :attribute يجب أن يكون ULID صحيحاً.',
    'uuid' => 'حقل :attribute يجب أن يكون UUID صحيحاً.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'current_password' => 'كلمة المرور الحالية',
        'role' => 'الصلاحية',
        'title' => 'المسمى',
        'phone' => 'الهاتف',
        'bio' => 'النبذة',
        'github' => 'حساب GitHub',
        'linkedin' => 'حساب LinkedIn',
        'avatar' => 'الصورة',
        'cover' => 'صورة الغلاف',
        'photo' => 'الصورة',
        'token' => 'رمز الاستعادة',
    ],

];
