<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One message in a discussion. APPEND-ONLY — created_at, no updated_at (the model sets
 * UPDATED_AT = null), mirroring submission_events. An editorial-discussion record that can be
 * silently edited after the fact is not the trustworthy trail this system is built around.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_discussion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')
                ->constrained('submission_discussions')
                ->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            // created_at only — see the class docblock. useCurrent so a raw insert still stamps it.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['discussion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_discussion_messages');
    }
};
