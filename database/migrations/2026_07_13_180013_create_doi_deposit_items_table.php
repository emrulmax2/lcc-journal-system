<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-DOI outcome within a batch. Crossref can accept a batch and still reject
 * individual records, so batch status alone is not enough to know a DOI is live —
 * this is what the Registrations screen reads, and what Retry acts on.
 *
 * `doi` is stored as the full resolved string at deposit time. It duplicates
 * Article::doi(), on purpose: this is an audit record of what was actually sent,
 * and it must survive the article being edited afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doi_deposit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doi_deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('doi');
            $table->enum('status', ['pending', 'registered', 'failed'])->default('pending');
            $table->text('message')->nullable();   // Crossref's actual words on failure
            $table->timestamps();

            $table->index(['doi_deposit_id', 'status']);
            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doi_deposit_items');
    }
};
