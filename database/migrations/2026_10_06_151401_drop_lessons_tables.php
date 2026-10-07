<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Courses are announced and registered for on the site, without lessons: the curriculum, its lessons and the
     * students' progress go.
     */
    public function up(): void
    {
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_modules');
    }

    /**
     * Reverse the migrations: the tables come back empty.
     */
    public function down(): void
    {
        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_module_id', 'position']);
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');

            $table->unique(['student_id', 'lesson_id']);
        });
    }
};
