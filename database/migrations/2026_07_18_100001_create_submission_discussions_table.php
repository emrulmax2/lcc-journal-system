<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal editorial discussions on a manuscript — the OJS-style per-stage forum.
 *
 * INTERNAL: the author is never a participant and this is never author-facing. It is where
 * editors (and, later, reviewers added with care) work out a manuscript between themselves.
 * `stage` scopes a thread to a pipeline stage (SubmissionStage 0-4) or is null for a general
 * thread.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            // Null = a general thread not tied to one stage.
            $table->unsignedTinyInteger('stage')->nullable();

            $table->string('subject');

            // nullOnDelete: the thread outlives the account that opened it. "Who started this"
            // becoming null is survivable; losing the editorial record is not.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['submission_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_discussions');
    }
};
