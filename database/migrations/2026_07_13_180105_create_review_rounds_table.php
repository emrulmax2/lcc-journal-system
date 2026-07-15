<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A round of peer review. Round 2 exists because round 1 asked for revisions — the two
 * are separate records, not an overwrite, because the reports from round 1 are part of the
 * decision's justification and must remain readable.
 *
 * An editorial decision CLOSES the round (closed_at). A round with closed_at = NULL is the
 * one currently open, and there is at most one per submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('round_number');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // Two "round 2"s on one manuscript is a corrupt history — the database refuses.
            $table->unique(['submission_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_rounds');
    }
};
