<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: a published issue is a permanent citable object. Deleting
            // the volume out from under it would orphan live DOIs. The IssuePolicy backs
            // this up at the application layer.
            $table->foreignId('volume_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('season')->nullable();              // "Spring 2026"
            $table->date('publication_date')->nullable();      // NULL until published
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('cover_path')->nullable();
            $table->timestamps();

            $table->unique(['volume_id', 'number']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
