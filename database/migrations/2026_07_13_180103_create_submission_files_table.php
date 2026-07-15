<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuscript files, VERSIONED. A revision does not overwrite the file it revises: the
 * version reviewer 2 actually read must still be retrievable when the decision is
 * challenged, so uploads only ever append.
 *
 * Files live on the PRIVATE disk. A manuscript under review is not public — an
 * unpublished, unaccepted paper sitting on a guessable public URL is a scoop waiting to
 * happen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('version')->default(1);
            $table->enum('type', ['manuscript', 'cover_letter', 'figure', 'supplementary', 'revision']);

            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // nullOnDelete: the file and its version history outlive the account that
            // uploaded it. "Who uploaded this" becoming NULL is survivable; losing the
            // manuscript is not.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['submission_id', 'type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_files');
    }
};
