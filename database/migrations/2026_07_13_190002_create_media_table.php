<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The media library — real, self-hosted assets.
 *
 * This exists to end the Unsplash dependency. Every photo on the site is currently a
 * remote stock image fetched from images.unsplash.com, with a picsum.photos fallback, and
 * the article page captions one of them as "Representative imagery from the study site".
 * It is not. It is a stranger's laboratory, and on a live academic publisher that is a
 * misrepresentation, not a placeholder.
 *
 * `alt` is NOT nullable-by-omission in the UI: an image with no alt text is invisible to
 * a screen reader, and an academic publisher is held to WCAG. Decorative images pass an
 * explicit empty string, which is a different statement from "we forgot".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();

            // Required in the form. An empty string is a deliberate "decorative"; NULL is
            // "nobody has said", and the UI must not let that reach a public page.
            $table->string('alt', 500)->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('credit')->nullable();   // photographer / licence attribution

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
