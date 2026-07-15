<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('abbreviation')->nullable();  // "JCD&MS" — citation_journal_abbrev
            $table->foreignId('field_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('aims_and_scope')->nullable();
            $table->string('cover_path')->nullable();

            // NULL until the British Library assigns them. Deliberate: an un-issued
            // ISSN must be absent from the deposit, never a "0000-0000" placeholder.
            $table->string('issn_online', 9)->nullable();
            $table->string('issn_print', 9)->nullable();

            // NULL until Crossref issues the prefix. Nothing else in the codebase may
            // hardcode a prefix; changing this one row must move every DOI the journal owns.
            $table->string('doi_prefix', 32)->nullable();

            // Read by DoiSuffixGenerator. Two observed patterns:
            //   issue_based : jcdms.v{volume}i{issue}.{seq}   -> jcdms.v10i2.001
            //   continuous  : mrdn.{year}.{seq}              -> mrdn.2026.00412
            $table->string('doi_suffix_pattern')->default('{journal}.v{volume}i{issue}.{seq}');
            $table->unsignedTinyInteger('doi_sequence_padding')->default(3);

            $table->enum('publication_model', ['continuous', 'issue_based'])->default('issue_based');
            $table->boolean('open_access')->default(true);
            $table->string('publisher')->default('London Churchill College');
            $table->string('principal_editor')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('license')->nullable();          // e.g. "CC BY 4.0"; NULL = all rights reserved
            $table->string('license_holder')->nullable();

            // Write-only in the API, encrypted at rest, never logged, never serialised
            // into a queue payload.
            $table->string('crossref_username')->nullable();
            $table->text('crossref_password')->nullable();
            $table->boolean('crossref_deposit_references')->default(true);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['field_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
