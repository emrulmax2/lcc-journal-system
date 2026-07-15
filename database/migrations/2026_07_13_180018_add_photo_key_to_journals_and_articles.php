<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The card components look up an image via PHOTO[record.photo], keyed by one of the 18
 * literal keys in resources/js/lib/images.ts. A key that is not in that map renders a
 * broken Unsplash URL, so the backend cannot return an arbitrary string here.
 *
 * photo_key is therefore the DEMO path (Unsplash, fine for dev), and cover_path /
 * photo_path is the REAL path (an asset on LCC storage). Before go-live the live journal
 * must use cover_path — shipping a live LCC journal illustrated with stock photography
 * of someone else's laboratory is not acceptable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->string('photo_key')->nullable()->after('cover_path');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('photo_key')->nullable()->after('body');
            $table->string('hero_path')->nullable()->after('photo_key');
        });
    }

    public function down(): void
    {
        Schema::table('journals', fn (Blueprint $t) => $t->dropColumn('photo_key'));
        Schema::table('articles', fn (Blueprint $t) => $t->dropColumn(['photo_key', 'hero_path']));
    }
};
