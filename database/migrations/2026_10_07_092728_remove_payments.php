<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings that only priced, taxed and invoiced orders (the currency stays for project budgets).
     */
    private const PAYMENT_SETTINGS = ['vat_percent', 'refund_guarantee', 'pro_month_price', 'invoice_note', 'payment_instructions'];

    /**
     * Students register for courses and workshops for free: orders, coupons, prices and Pro go. A workshop seat,
     * which was a paid workshop order, becomes a registration of its own; course enrollments stay as they are.
     */
    public function up(): void
    {
        Schema::create('workshop_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['workshop_id', 'student_id']);
        });

        $seats = DB::table('orders')
            ->join('workshops', 'workshops.id', '=', 'orders.item_id')
            ->where('orders.item_type', 'workshop')
            ->where('orders.status', 'completed')
            ->orderBy('orders.paid_at')
            ->get(['orders.item_id', 'orders.student_id', 'orders.paid_at', 'orders.created_at'])
            ->unique(fn (object $order): string => $order->item_id.'-'.$order->student_id);

        foreach ($seats->chunk(500) as $chunk) {
            DB::table('workshop_registrations')->insert($chunk->map(fn (object $order): array => [
                'workshop_id' => $order->item_id,
                'student_id' => $order->student_id,
                'created_at' => $order->paid_at ?? $order->created_at,
                'updated_at' => $order->paid_at ?? $order->created_at,
            ])->values()->all());
        }

        Schema::table('enrollments', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_id'));
        Schema::drop('orders');
        Schema::drop('coupons');

        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['price', 'old_price', 'has_regional_pricing', 'is_included_in_pro']));
        Schema::table('workshops', fn (Blueprint $table) => $table->dropColumn('price'));
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn('pro_until'));

        // accountants have nothing left to do: they become support
        DB::table('users')->where('role', 'accountant')->update(['role' => 'support']);
        DB::table('settings')->whereIn('key', self::PAYMENT_SETTINGS)->delete();
        DB::table('activities')->whereIn('subject_type', ['App\\Models\\Order', 'App\\Models\\Coupon'])->delete();
        DB::table('notifications')->where('type', 'App\\Notifications\\Alerts\\OrderPaid')->delete();
    }

    /**
     * Reverse the migrations: the columns and tables come back empty (prices at zero); registrations are dropped.
     */
    public function down(): void
    {
        Schema::table('students', fn (Blueprint $table) => $table->timestamp('pro_until')->nullable());
        Schema::table('workshops', fn (Blueprint $table) => $table->decimal('price', 10, 2)->default(0));
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->boolean('has_regional_pricing')->default(true);
            $table->boolean('is_included_in_pro')->default(true);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('type');
            $table->decimal('value', 10, 2);
            $table->string('scope');
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->date('expires_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('item_type');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 20)->nullable();
            $table->string('payment_method');
            $table->string('status')->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
        });

        Schema::table('enrollments', fn (Blueprint $table) => $table->foreignId('order_id')->nullable()->after('course_id')->constrained()->nullOnDelete());
        Schema::drop('workshop_registrations');
    }
};
