<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per deposit BATCH sent to Crossref.
 *
 * issue_id is nullable: issue-based journals deposit an issue at a time, continuous
 * journals deposit per-article.
 *
 * A 200 on the POST is NOT confirmation of registration — Crossref processes deposits
 * asynchronously. `submitted` means accepted for processing; only the polled submission
 * report can move an item to `registered` or `failed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doi_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();

            $table->uuid('batch_id')->unique();
            $table->string('payload_path')->nullable();   // the exact XML we sent, kept for audit
            $table->enum('status', ['queued', 'depositing', 'submitted', 'registered', 'failed'])
                ->default('queued');
            $table->string('crossref_submission_id')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->enum('endpoint', ['sandbox', 'production'])->default('sandbox');

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['journal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doi_deposits');
    }
};
