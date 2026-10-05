<?php

namespace App\Http\Controllers\Api;

use App\Actions\PlatformData\WipePlatformData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataWipeController extends Controller
{
    /**
     * Typed in the confirmation box, word for word.
     */
    public const CONFIRMATION = 'احذف كل البيانات';

    /**
     * Delete all of the platform's data. Twice protected: the owner's password, and the confirmation sentence typed out.
     */
    public function store(Request $request, WipePlatformData $wipe): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'string', 'in:'.self::CONFIRMATION],
        ], [
            'confirmation.in' => 'اكتب الجملة "'.self::CONFIRMATION.'" كما هي للتأكيد.',
        ], [
            'password' => 'كلمة المرور',
            'confirmation' => 'جملة التأكيد',
        ]);

        $deleted = $wipe->handle($request->user());

        return response()->json(['message' => 'تم حذف كل بيانات المنصة.', 'deleted' => $deleted]);
    }
}
