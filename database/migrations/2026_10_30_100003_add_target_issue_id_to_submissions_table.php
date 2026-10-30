<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The issue the author is AIMING for — "Volume 11, Number 1 · Spring 2027" — chosen in the
 * submission wizard from the issues the editors have opened (unpublished issues).
 *
 * A PREFERENCE, not a placement. NULL means "the next available issue". Where an accepted
 * paper actually appears is still the editors' production decision, made on the article;
 * this column only tells them what the author hoped for. nullOnDelete: removing a planned
 * issue must not remove the manuscript.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('target_issue_id')->nullable()->after('journal_section_id')
                ->constrained('issues')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', fn (Blueprint $table) => $table->dropConstrainedForeignId('target_issue_id'));
    }
};
