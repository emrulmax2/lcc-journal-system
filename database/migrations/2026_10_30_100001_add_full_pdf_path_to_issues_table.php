<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The whole printed issue as one PDF — cover, editorial panel, contents, every article and
 * the contributor guidelines. The front and back matter exist nowhere else on the site, so
 * this is the only place a reader can see them.
 *
 * Same storage rule as article PDFs: the PRIVATE disk, streamed through a stable public
 * route, so the URL never depends on where the bytes happen to live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('full_pdf_path')->nullable()->after('cover_media_id');
        });
    }

    public function down(): void
    {
        Schema::table('issues', fn (Blueprint $table) => $table->dropColumn('full_pdf_path'));
    }
};
