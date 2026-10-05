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
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            // a category with tools is only deleted after its tools move elsewhere (ToolCategoryController)
            $table->foreignId('tool_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 40)->unique();
            $table->string('short', 3);
            $table->string('color', 7);
            $table->string('why', 120);
            $table->unsignedSmallInteger('since');
            $table->string('url')->nullable();
            $table->boolean('is_affiliate')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
