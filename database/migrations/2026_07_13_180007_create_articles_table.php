<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();

            // NULLABLE by design. Continuous-publication journals have no issues at all;
            // forcing an issue on them would be modelling a print artefact that does not
            // exist. JCD&MS is issue_based and will always have one.
            $table->foreignId('issue_id')->nullable()->constrained()->restrictOnDelete();

            $table->foreignId('journal_section_id')->nullable()->constrained()->nullOnDelete();

            // --- FROZEN ON PUBLISH (slug, sequence, doi_suffix) ---------------------
            // The public URL. Frozen at publication and never regenerated, even if the
            // title is later corrected. Most slug packages regenerate on title change;
            // that behaviour would silently kill a live DOI. ArticleObserver forbids it.
            $table->string('slug');
            $table->unsignedSmallInteger('sequence')->nullable();  // position within the issue

            // Unique across the WHOLE table, not per journal — a DOI suffix is only
            // meaningful when combined with a prefix, but two journals sharing a prefix
            // would collide. Global uniqueness makes that impossible.
            // NULL until generated; MySQL permits many NULLs under a unique index.
            $table->string('doi_suffix')->nullable()->unique();
            // ------------------------------------------------------------------------

            $table->string('title', 500);
            $table->text('abstract')->nullable();
            $table->json('keywords')->nullable();
            $table->longText('body')->nullable();

            // An article by a research centre rather than named people — JCD&MS Vol 10
            // No 2 Article 001 is exactly this. When set, article_authors is EMPTY, and
            // Crossref must receive an <organization> contributor, not a <person_name>.
            $table->string('corporate_author', 500)->nullable();

            $table->unsignedSmallInteger('first_page')->nullable();
            $table->unsignedSmallInteger('last_page')->nullable();

            $table->enum('status', ['draft', 'published', 'withdrawn'])->default('draft');
            $table->timestamp('published_at')->nullable();

            // Denormalised counters for display. article_metric_daily is the source of
            // truth; a scheduled command rolls these up.
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('citations_count')->default(0);

            $table->timestamps();

            $table->unique(['journal_id', 'slug']);
            $table->index(['journal_id', 'status', 'published_at']);
            $table->index(['issue_id', 'sequence']);
        });

        // The Articles page searches title + abstract. A LIKE '%…%' scan does not survive
        // contact with a real archive, so this is a real FULLTEXT index. InnoDB has
        // supported these since MySQL 5.6, and the dev box is 5.7.
        DB::statement('ALTER TABLE articles ADD FULLTEXT articles_fulltext (title, abstract)');
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
