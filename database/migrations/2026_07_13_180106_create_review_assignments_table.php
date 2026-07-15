<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invitation to review: invited → accepted/declined → report_submitted.
 *
 * reviewer_id is restrictOnDelete. A reviewer's account cannot be deleted out from under a
 * review they wrote — the assignment is the only thing tying a report to the person who
 * wrote it, and an editorial record with an anonymous author is worthless in a dispute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();

            $table->enum('status', ['invited', 'accepted', 'declined', 'report_submitted'])
                ->default('invited');

            $table->timestamp('invited_at');
            $table->timestamp('due_at');
            $table->timestamp('responded_at')->nullable();   // accepted or declined
            $table->timestamp('completed_at')->nullable();   // report landed
            $table->timestamps();

            // One invitation per reviewer per round. Inviting the same person twice into
            // one round would count their opinion twice in the editor's tally.
            $table->unique(['review_round_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_assignments');
    }
};
