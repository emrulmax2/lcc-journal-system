<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A manuscript can now be submitted WITHOUT an account.
 *
 * Submission used to be behind login, which meant every author needed an account before
 * they could send a paper. For an open-access journal that is a barrier for no gain: the
 * people you most want submitting are the ones least likely to make an account first. The
 * corresponding author is identified by the email they enter in the wizard (a
 * submission_author row flagged is_corresponding), not by a user record — so this column
 * becomes NULLABLE. When a signed-in user does submit, it is still set to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            // Drop the FK before altering nullability, then re-add it — MySQL will not let
            // a column under a foreign key change type in place.
            $table->dropForeign(['corresponding_author_id']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('corresponding_author_id')->nullable()->change();
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreign('corresponding_author_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropForeign(['corresponding_author_id']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreignId('corresponding_author_id')->nullable(false)->change();
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->foreign('corresponding_author_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });
    }
};
