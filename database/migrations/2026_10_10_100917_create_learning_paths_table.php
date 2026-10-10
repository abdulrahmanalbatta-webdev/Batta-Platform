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
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->string('title', 80);
            $table->string('icon', 20);
            $table->string('summary', 300)->nullable();
            $table->string('audience', 120)->nullable();
            $table->string('duration', 30)->nullable();
            $table->json('outcomes')->nullable();
            // the map: stages in order, each with its topics, free resources and the platform's own courses, workshops and articles
            $table->json('stages')->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_paths');
    }
};
