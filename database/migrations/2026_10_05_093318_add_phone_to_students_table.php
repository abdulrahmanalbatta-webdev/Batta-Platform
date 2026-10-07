<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The student's phone (WhatsApp), so the team has a way to reach them.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
