<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students sign in to the public site through the API (Sanctum tokens). The password stays empty for
     * students the team added or who bought before having an account: they set one with "forgot password".
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });

        Schema::create('student_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_password_reset_tokens');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};
