<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The report itself.
 *
 * `comments_to_editor` IS CONFIDENTIAL. Under single-blind review it must never reach an
 * author through any endpoint, serialiser, eager-load or error message — a leak here is a
 * research-integrity incident, not a bug. The column is deliberately kept on a table that
 * no author-facing serialiser touches, and ReviewAssignmentPolicy is what stands between
 * it and everyone but the handling editor and its own author.
 *
 * review_assignment_id is UNIQUE: one report per invitation. A reviewer who wants to
 * change their mind writes a new review in the next round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_assignment_id')->unique()->constrained()->cascadeOnDelete();

            $table->enum('recommendation', ['accept', 'minor_revision', 'major_revision', 'reject']);

            $table->text('comments_to_author');
            $table->text('comments_to_editor')->nullable();   // CONFIDENTIAL — never author-facing

            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
