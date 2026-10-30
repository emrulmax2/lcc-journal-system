<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A small image of the article's FIRST PAGE, shown on article cards (public disk).
 *
 * Deliberately not hero_media_id: that is the article's own figure, rendered on the article
 * page AS a figure. A thumbnail is a preview of the paper itself — never a stock photograph,
 * which on a research paper would misrepresent the work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable()->after('hero_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('thumbnail_path'));
    }
};
