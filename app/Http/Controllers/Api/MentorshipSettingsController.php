<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MentorshipSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorshipSettingsController extends Controller
{
    public function show(MentorshipSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->all()]);
    }

    /**
     * Save all of them at once (the settings form sends the whole set).
     */
    public function update(Request $request, MentorshipSettings $settings): JsonResponse
    {
        $settings->update($request->validate(MentorshipSettings::rules(), [], [
            'capacity' => 'عدد المقاعد',
            'payment_hours' => 'مهلة الدفع',
            'wallets.*.number' => 'رقم المحفظة',
            'wallets.*.holder' => 'اسم صاحب المحفظة',
            'packages' => 'الباقات',
            'packages.*.name' => 'اسم الباقة',
            'packages.*.price' => 'السعر',
            'packages.*.description' => 'وصف الباقة',
            'packages.*.features.*' => 'الميزة',
            'policy' => 'سياسة الإلغاء والاسترجاع',
        ]));

        return response()->json(['data' => $settings->all(), 'message' => 'تم حفظ إعدادات الإرشاد.']);
    }
}
