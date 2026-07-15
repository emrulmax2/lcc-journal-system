<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deposited to Crossref as <citation_list> when journals.crossref_deposit_references
 * is on. This is what powers Cited-by: other publishers' deposits linking to our DOIs
 * only work if we participate in the same graph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ordinal');
            $table->text('raw_text');
            $table->string('doi')->nullable();   // the cited work's DOI, where known
            $table->timestamps();

            $table->index(['article_id', 'ordinal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_references');
    }
};
