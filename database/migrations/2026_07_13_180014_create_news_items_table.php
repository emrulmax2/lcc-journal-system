<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title', 500);
            $table->string('category');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('photo_key')->nullable();   // key into the frontend PHOTO map
            $table->string('photo_path')->nullable();  // real asset, once we have one
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
