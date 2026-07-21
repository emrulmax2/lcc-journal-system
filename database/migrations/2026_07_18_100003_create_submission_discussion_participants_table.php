<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is explicitly on a discussion thread. The creator is always one; others are added from
 * the journal's EDITORIAL staff. Authors are never added — this is an internal forum.
 *
 * This is the set Phase 2 will notify when a reply lands. Any editor of the journal can still
 * READ every thread (SubmissionDiscussionPolicy falls back to viewAllSubmissions); the
 * participant rows mark active involvement, not the outer bound of who may look.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_discussion_participants', function (Blueprint $table) {
            $table->foreignId('discussion_id')
                ->constrained('submission_discussions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->primary(['discussion_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_discussion_participants');
    }
};
