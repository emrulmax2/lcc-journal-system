<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * THE AUDIT TRAIL. This is not optional and it is not a log file.
 *
 * Editorial decisions get challenged, sometimes years after the fact, and "who assigned
 * that reviewer, and when" must be answerable from the database rather than from someone's
 * memory. EVERY state transition writes a row here — see Submission::recordEvent(), which
 * is the only way rows get in.
 *
 * There is no updated_at, deliberately: an audit row that can be edited is not an audit
 * row. Rows are append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();

            // nullOnDelete, and nullable for events with no actor (a scheduled reminder,
            // an automatic escalation). The event survives the account that caused it.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event');
            $table->json('payload')->nullable();

            $table->timestamp('created_at')->useCurrent();

            // The trail is always read as "this manuscript, in order".
            $table->index(['submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_events');
    }
};
