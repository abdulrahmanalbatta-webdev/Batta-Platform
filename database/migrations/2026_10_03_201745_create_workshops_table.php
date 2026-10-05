<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // local date and wall-clock time as the organiser typed them (no timezone conversion)
            $table->date('date')->index();
            $table->time('start_time');
            $table->string('format');
            $table->string('place', 100);
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('seats');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshops');
    }
};
