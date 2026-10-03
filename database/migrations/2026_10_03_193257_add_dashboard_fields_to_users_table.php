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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('editor')->after('password');
            $table->string('title')->nullable()->after('role');
            $table->string('phone', 30)->nullable()->after('title');
            $table->text('bio')->nullable()->after('phone');
            $table->string('github')->nullable()->after('bio');
            $table->string('linkedin')->nullable()->after('github');
            $table->string('avatar_path')->nullable()->after('linkedin');
            $table->timestamp('last_login_at')->nullable()->after('avatar_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'title', 'phone', 'bio', 'github', 'linkedin', 'avatar_path', 'last_login_at']);
        });
    }
};
