<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students' applications to the paid mentorship (الإرشاد): the package and its price when they applied,
     * their answers, and each step from review to payment to the first session.
     */
    public function up(): void
    {
        Schema::create('mentorship_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('package_id', 40);
            $table->string('package_name', 80);
            $table->unsignedInteger('price');
            $table->string('status', 20)->default('pending')->index();
            $table->text('goal');
            $table->text('background');
            $table->string('links', 500)->nullable();
            $table->string('availability', 300);
            // what the student is told when the request or a payment receipt is turned down
            $table->text('decision_note')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
            $table->string('transaction_ref', 100)->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('session_at')->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            // the owner's own notes, never shown to the student
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentorship_requests');
    }
};
