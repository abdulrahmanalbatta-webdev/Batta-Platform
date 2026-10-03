<?php

namespace App\Enums;

enum CouponScope: string
{
    case AllCourses = 'all-courses';
    case Workshops = 'workshops';
    case Course = 'course';

    /**
     * Whether a coupon with this scope may discount the given item.
     */
    public function covers(OrderItemType $type, ?int $itemId, ?int $couponCourseId): bool
    {
        return match ($this) {
            self::AllCourses => $type === OrderItemType::Course,
            self::Workshops => $type === OrderItemType::Workshop,
            self::Course => $type === OrderItemType::Course && $itemId !== null && $itemId === $couponCourseId,
        };
    }
}
