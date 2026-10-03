<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CouponController extends Controller
{
    /**
     * Every coupon, newest first.
     */
    public function index(): AnonymousResourceCollection
    {
        return CouponResource::collection(Coupon::query()->with('course:id,title')->latest()->latest('id')->get());
    }

    public function store(CouponRequest $request): CouponResource
    {
        return new CouponResource(Coupon::create($request->couponAttributes())->load('course:id,title'));
    }

    /**
     * Switch a coupon on or off (codes, values and limits are fixed once customers may have it).
     */
    public function update(Request $request, Coupon $coupon): CouponResource
    {
        $coupon->update($request->validate(['is_active' => ['required', 'boolean']]));

        return new CouponResource($coupon->load('course:id,title'));
    }

    /**
     * Delete a coupon; its orders keep the code they were sold with.
     */
    public function destroy(Coupon $coupon): Response
    {
        $coupon->delete();

        return response()->noContent();
    }
}
