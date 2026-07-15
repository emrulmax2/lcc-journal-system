<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Open calls for papers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_topics', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('photo_key')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('deadline')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });

        Schema::create('research_topic_editors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['research_topic_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_topic_editors');
        Schema::dropIfExists('research_topics');
    }
};
