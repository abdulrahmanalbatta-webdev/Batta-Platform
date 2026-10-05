<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The public site's own content (announcement, services, case studies, FAQs, the about page…), one JSON
     * value per section. A section that was never edited falls back to resources/data/site-content.json.
     */
    public function up(): void
    {
        Schema::create('site_blocks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_blocks');
    }
};
