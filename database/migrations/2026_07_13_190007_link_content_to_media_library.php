<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points every content type at a real, self-hosted image instead of an Unsplash key.
 *
 * photo_key columns are LEFT IN PLACE and left populated. They are the fallback while
 * real assets are being sourced, and dropping them now would blank every card on the
 * site. The resolution order in the resources is:
 *
 *     media (a real asset we own)  ->  photo_key (Unsplash stock)  ->  a neutral placeholder
 *
 * The intent is that photo_key reaches zero rows and is then dropped. Two things make
 * that a real obligation rather than a wish:
 *
 *   - `articles.hero_media_id`: the article page currently captions a stock photo as
 *     "Figure 1. Representative imagery from the study site." It is not from the study
 *     site. It is a stranger's laboratory, and on a published paper that is a fabrication.
 *   - `journals.cover_media_id`: shipping a live LCC journal illustrated with stock
 *     photography of someone else's lab is not something we should do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->foreignId('cover_media_id')->nullable()->after('cover_path')
                ->constrained('media')->nullOnDelete();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('hero_media_id')->nullable()->after('hero_path')
                ->constrained('media')->nullOnDelete();
        });

        Schema::table('news_items', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->after('photo_path')
                ->constrained('media')->nullOnDelete();

            // The detail page these were always meant to have.
            $table->foreignId('author_id')->nullable()->after('media_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('research_topics', function (Blueprint $table) {
            $table->foreignId('media_id')->nullable()->after('photo_path')
                ->constrained('media')->nullOnDelete();

            // research_topics.description exists but nothing renders it, because there is
            // no detail page. A call for papers with no scope is not a call for papers.
            $table->longText('body')->nullable()->after('description');
            $table->string('submission_email')->nullable()->after('body');
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('cover_media_id')->nullable()->after('cover_path')
                ->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $t) => $t->dropConstrainedForeignId('cover_media_id'));

        Schema::table('research_topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_id');
            $table->dropColumn(['body', 'submission_email']);
        });

        Schema::table('news_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_id');
            $table->dropConstrainedForeignId('author_id');
        });

        Schema::table('articles', fn (Blueprint $t) => $t->dropConstrainedForeignId('hero_media_id'));
        Schema::table('journals', fn (Blueprint $t) => $t->dropConstrainedForeignId('cover_media_id'));
    }
};
