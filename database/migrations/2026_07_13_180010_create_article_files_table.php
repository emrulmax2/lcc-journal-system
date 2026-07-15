<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files live on a PRIVATE disk; `path` is the key, and they are streamed through a
 * stable public route. citation_pdf_url must match that route exactly — a mismatch
 * between the advertised PDF URL and the real one is the single most common reason
 * Google Scholar silently refuses to index a journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['pdf', 'xml', 'supplementary', 'dataset']);
            $table->string('path');
            $table->string('label')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();

            $table->index(['article_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_files');
    }
};
