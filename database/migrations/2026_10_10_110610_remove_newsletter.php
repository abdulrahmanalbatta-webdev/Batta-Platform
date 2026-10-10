<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The newsletter is gone from the site and the dashboard: the subscriber list, the article's "send to
     * subscribers" box and the platform switch that emailed new articles.
     */
    public function up(): void
    {
        Schema::dropIfExists('subscribers');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('send_newsletter');
        });

        DB::table('settings')->where('key', 'newsletter_new_articles')->delete();
        Cache::forget('site.content');
        Cache::forget('platform.settings');
    }

    /**
     * Reverse the migrations: the tables come back empty, the subscribers are gone.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('send_newsletter')->default(true);
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('source', 30)->default('site');
            $table->timestamp('unsubscribed_at')->nullable()->index();
            $table->timestamps();
        });
    }
};
