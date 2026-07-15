<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The homepage's editorial sections: the author-path cards and the "how publishing works"
 * steps, plus the eyebrow/heading/blurb of every band on the page.
 *
 * WHY THIS EXISTS, specifically: the "how it works" steps currently render four medians —
 * "About 20 minutes", "Median 4 days", "Median 38 days", "Median 9 days" — that are
 * invented. They are not computed from anything, they contradict the "Median 51 days"
 * claim in the navbar, and they are presented to authors as this platform's performance.
 *
 * So `meta` here is a free string an editor OWNS and is accountable for, and the seeder
 * ships those step cards with NO timing claims at all. Where a real median exists we
 * compute it (journal_metrics.median_days_to_decision) and show it on the Journals page,
 * where it is attributable to a specific journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // hero | author-paths | featured-journals | how-it-works | ...
            $table->string('name');            // shown in the admin, not on the site

            $table->string('eyebrow')->nullable();
            $table->string('heading')->nullable();
            $table->text('blurb')->nullable();

            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->boolean('is_visible')->default(true);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained()->cascadeOnDelete();

            $table->string('icon')->nullable();     // a Lucide icon NAME, resolved against an allow-list
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('meta')->nullable();     // e.g. a step's timing — editable, and OWNED by an editor

            $table->string('cta_label')->nullable();
            $table->string('route_name')->nullable();
            $table->string('external_url')->nullable();

            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['home_section_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
        Schema::dropIfExists('home_sections');
    }
};
