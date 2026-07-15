<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('affiliation')->nullable();
            $table->string('orcid', 19)->nullable();
            $table->json('expertise')->nullable();
            $table->boolean('available')->default(true);
            $table->unsignedSmallInteger('max_concurrent_reviews')->default(3);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_profiles');
    }
};
