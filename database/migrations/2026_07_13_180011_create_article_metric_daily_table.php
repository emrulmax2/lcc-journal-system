<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The source of truth for "Views & downloads". articles.views_count is a denormalised
 * roll-up of this table, not the other way round.
 *
 * Counts must be bot-filtered before they mean anything — a raw hit count on an
 * academic landing page is mostly crawlers, and publishing that number as "views"
 * would be a misrepresentation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_metric_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->timestamps();

            $table->unique(['article_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_metric_daily');
    }
};
