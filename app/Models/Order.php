<?php

namespace App\Models;

use App\Enums\OrderItemType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\LogsActivity;
use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Created and changed only through the order actions (PlaceOrder, CompleteOrder, RefundOrder, FailOrder),
 * so nothing here is mass assignable.
 */
#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    /**
     * Order numbers start at #1001 rather than #1.
     */
    public const NUMBER_OFFSET = 1000;

    /** @use HasFactory<OrderFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => OrderItemType::class,
            'payment_method' => PaymentMethod::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The order number customers see on invoices, e.g. "#1042".
     */
    public function number(): string
    {
        return '#'.(self::NUMBER_OFFSET + $this->id);
    }

    public function activityLabel(): string
    {
        return 'الطلب';
    }

    public function activityName(): string
    {
        return $this->number();
    }

    protected function activityStateAttribute(): ?string
    {
        return 'status';
    }

    protected function activityStateLabel(): ?string
    {
        return $this->status->label();
    }

    /**
     * @return list<string>
     */
    protected function activityIgnoredAttributes(): array
    {
        return ['paid_at', 'refunded_at', 'fee'];
    }
}
