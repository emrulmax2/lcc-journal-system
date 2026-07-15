<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The authors as the submitting author typed them — one `name` field, because that is what
 * the wizard asks for. They are split into given_name / family_name only at the moment of
 * conversion to an Article, where Crossref requires the two separately.
 *
 * Order is MEANINGFUL: `sequence` carries author order onto the article and into the
 * Crossref deposit. Never sort these alphabetically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('email');
            $table->string('affiliation')->nullable();

            // NULL where the author has no ORCID. NEVER fabricate one — a wrong ORCID
            // attributes someone else's work to a real, identifiable person.
            $table->string('orcid', 19)->nullable();

            $table->boolean('is_corresponding')->default(false);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['submission_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_authors');
    }
};
