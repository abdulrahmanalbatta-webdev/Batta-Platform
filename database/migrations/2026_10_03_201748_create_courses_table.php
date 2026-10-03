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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('short_description', 140)->nullable();
            $table->text('description')->nullable();
            $table->json('outcomes')->nullable();
            $table->json('tags')->nullable();
            $table->string('level');
            $table->string('category');
            $table->string('status')->index();
            $table->date('publish_at')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->boolean('has_regional_pricing')->default(true);
            $table->boolean('is_included_in_pro')->default(true);
            $table->boolean('has_certificate')->default(true);
            $table->boolean('allows_questions')->default(true);
            $table->string('cover_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
