<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * An article slug must be unique across the WHOLE table, not per journal.
 *
 * The public URL is /articles/{slug} — there is no journal in it. Under the old
 * (journal_id, slug) index, two journals could each publish "editorial", and route binding
 * would serve whichever row MySQL returned first at BOTH addresses: one article's DOI
 * resolving to another article's landing page, PDF and citation_* tags. Or, if that first
 * row was still a draft, to a 404.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('articles')
            ->select('slug', DB::raw('GROUP_CONCAT(id ORDER BY id) AS ids'))
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        // Refuse rather than rename. Any of these slugs may already be a live URL with a DOI
        // pointing at it, and deciding which article keeps the address is an editorial call,
        // not something a migration may guess at.
        if ($duplicates->isNotEmpty()) {
            $list = $duplicates->map(fn ($row) => "'{$row->slug}' (article ids {$row->ids})")->implode(', ');

            throw new RuntimeException(
                "Cannot make article slugs globally unique — these are shared across journals: {$list}. "
                .'Rename the unpublished article in each pair in the admin, then re-run the migration.'
            );
        }

        Schema::table('articles', function (Blueprint $table) {
            // Added BEFORE the old index is dropped, so there is never a moment without one.
            $table->unique('slug');

            // Safe to drop: journal_id's foreign key is still served by the
            // (journal_id, status, published_at) index.
            $table->dropUnique(['journal_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unique(['journal_id', 'slug']);
            $table->dropUnique(['slug']);
        });
    }
};
