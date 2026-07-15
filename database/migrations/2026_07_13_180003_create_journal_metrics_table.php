<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two kinds of number live here and they must not be confused.
 *
 *   EXTERNAL  impact_factor, cite_score  — issued by JCR / Scopus. Entered by hand.
 *             The UI must label these as externally sourced.
 *   COMPUTED  acceptance_rate, median_days_to_decision, article_count, editor_count
 *             — derived from our own data on a schedule, so that nobody can type a
 *             flattering number into a field that readers will treat as a fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();

            $table->decimal('impact_factor', 5, 2)->nullable();
            $table->decimal('cite_score', 5, 2)->nullable();
            $table->timestamp('external_updated_at')->nullable();

            $table->unsignedTinyInteger('acceptance_rate')->nullable();     // percent
            $table->unsignedSmallInteger('median_days_to_decision')->nullable();
            $table->unsignedInteger('article_count')->default(0);
            $table->unsignedInteger('editor_count')->default(0);
            $table->timestamp('computed_at')->nullable();

            $table->timestamps();
            $table->unique('journal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_metrics');
    }
};
