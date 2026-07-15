<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Author order is MEANINGFUL — first author, then contribution order. `sequence`
 * carries it and the Crossref deposit reads authors in this order. Never sort these
 * alphabetically for display.
 *
 * An article with a corporate_author has ZERO rows here. That is valid, not a bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();

            $table->string('given_name');
            $table->string('family_name');
            $table->string('affiliation')->nullable();

            // NULL where the author has no ORCID. NEVER fabricate one: a wrong ORCID in
            // a Crossref deposit attributes someone else's work to a real, identifiable
            // person, and it propagates to every downstream index.
            $table->string('orcid', 19)->nullable();

            $table->string('email')->nullable();
            $table->boolean('is_corresponding')->default(false);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['article_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_authors');
    }
};
