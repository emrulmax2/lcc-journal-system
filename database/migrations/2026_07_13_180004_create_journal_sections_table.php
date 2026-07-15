<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-journal article types. JCD&MS: Editorial, Research Article, Research Report,
 * Research Protocol.
 *
 * doi_eligible exists because front matter gets no DOI while editorials do — the
 * Crossref XML builder skips sections where this is false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('doi_eligible')->default(true);
            $table->timestamps();

            $table->index('journal_id');
            $table->unique(['journal_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_sections');
    }
};
