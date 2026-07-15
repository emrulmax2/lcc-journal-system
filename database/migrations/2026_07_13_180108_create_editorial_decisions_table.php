<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The decision. It closes the round it was taken on.
 *
 * review_round_id is NULLABLE: a desk rejection at the editor-check stage is a real
 * decision taken before any round was ever opened, and it must be recordable.
 *
 * editor_id is restrictOnDelete — "who decided this, and when" is the question the whole
 * audit trail exists to answer, and it cannot be allowed to become NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_round_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('editor_id')->constrained('users')->restrictOnDelete();

            $table->enum('decision', ['accept', 'minor_revision', 'major_revision', 'reject']);
            $table->text('body');
            $table->timestamp('decided_at');
            $table->timestamps();

            // The days-to-first-decision series reads (submission, decided_at) in order.
            $table->index(['submission_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_decisions');
    }
};
