<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One comments table for articles, courses and workshops, with one level of replies (a student answering a
     * comment). The article comments move here with their ids, moderation and team replies.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status')->default('pending')->index();
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        DB::table('article_comments')->orderBy('id')->chunk(500, function ($comments): void {
            DB::table('comments')->insert($comments->map(fn (object $comment): array => [
                'id' => $comment->id,
                'commentable_type' => 'App\\Models\\Article',
                'commentable_id' => $comment->article_id,
                'parent_id' => null,
                'student_id' => $comment->student_id,
                'body' => $comment->body,
                'status' => $comment->status,
                'reply' => $comment->reply,
                'replied_by' => $comment->replied_by,
                'replied_at' => $comment->replied_at,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ])->all());
        });

        Schema::drop('article_comments');
    }

    /**
     * Reverse the migrations: article comments go back to their own table; replies and course or workshop
     * comments have nowhere to go there.
     */
    public function down(): void
    {
        Schema::create('article_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status')->default('pending')->index();
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        DB::table('comments')->where('commentable_type', 'App\\Models\\Article')->whereNull('parent_id')->orderBy('id')
            ->chunk(500, function ($comments): void {
                DB::table('article_comments')->insert($comments->map(fn (object $comment): array => [
                    'id' => $comment->id,
                    'article_id' => $comment->commentable_id,
                    'student_id' => $comment->student_id,
                    'body' => $comment->body,
                    'status' => $comment->status,
                    'reply' => $comment->reply,
                    'replied_by' => $comment->replied_by,
                    'replied_at' => $comment->replied_at,
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                ])->all());
            });

        Schema::drop('comments');
    }
};
