<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The manuscript, from the moment an author starts typing until an editor decides.
 *
 * `reference` is NULLABLE and unique. A DRAFT has no reference: references are issued by
 * SubmitManuscriptAction at the moment of submission, so that an abandoned draft does not
 * burn a number out of the middle of the journal's sequence. MySQL permits many NULLs
 * under a unique index, which is exactly the behaviour wanted here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            // MRDN-2026-0417 / JCDMS-2026-0417 — the number the author quotes in an email
            // and the editorial office looks up. Unique across the whole platform.
            $table->string('reference', 32)->nullable()->unique();

            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_section_id')->nullable()->constrained()->nullOnDelete();

            // The account that owns the submission. restrictOnDelete: deleting the user who
            // submitted a manuscript must not silently delete the manuscript, or its audit
            // trail, or the reviews written about it.
            $table->foreignId('corresponding_author_id')->constrained('users')->restrictOnDelete();

            $table->string('title', 500);
            $table->text('abstract')->nullable();
            $table->json('keywords')->nullable();

            $table->enum('status', [
                'draft', 'submitted', 'under_review', 'revisions_requested', 'accepted', 'rejected',
            ])->default('draft');

            // Submitted(0) → Editor check(1) → Peer review(2) → Decision(3) → Production(4).
            $table->unsignedTinyInteger('stage')->default(0);

            $table->text('funding')->nullable();

            // A COMPLIANCE RECORD, not a checkbox. declarations_at is when the author
            // affirmed them; the three booleans are what they affirmed. Published with the
            // article, and the thing an integrity investigation asks for years later.
            $table->boolean('ethics_declared')->default(false);
            $table->boolean('conflicts_declared')->default(false);
            $table->boolean('data_available')->default(false);
            $table->timestamp('declarations_at')->nullable();

            $table->timestamp('submitted_at')->nullable();

            // THE SEAM BETWEEN THE TWO HALVES OF THE SYSTEM. Set by
            // ConvertSubmissionToArticleAction when a submission is accepted; NULL for
            // everything else. nullOnDelete rather than cascade — deleting a draft article
            // must not delete the editorial history that produced it.
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();

            // ---- Draft-only scratch state. Both are NULL once the manuscript is submitted.
            // The wizard promises "your progress is saved at every step", and a draft that
            // resumes at step 1 with four steps of typing intact has still lost the author's
            // place. `draft_file_name` remembers only the NAME of the file the author had
            // attached: the file itself is not uploaded on a draft save, so the wizard makes
            // them re-attach it — printing a filename we cannot actually upload would be a
            // lie the author only discovers when the manuscript arrives empty.
            $table->unsignedTinyInteger('draft_step')->nullable();
            $table->string('draft_file_name')->nullable();

            $table->timestamps();

            $table->index(['journal_id', 'status']);
            $table->index(['corresponding_author_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
