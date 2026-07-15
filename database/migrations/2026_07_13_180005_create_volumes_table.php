<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Issue-based journals only. Continuous journals have no rows here. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->year('year');
            $table->timestamps();

            $table->unique(['journal_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volumes');
    }
};
