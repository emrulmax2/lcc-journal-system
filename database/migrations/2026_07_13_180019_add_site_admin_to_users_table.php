<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * site-admin is NOT a per-journal role, and trying to make it one was wrong.
 *
 * Spatie's teams feature puts the team key in the PRIMARY KEY of model_has_roles, so
 * every role assignment must name a journal. That is correct for journal-editor,
 * reviewer, production and the rest — "editor" is meaningless without "of what".
 *
 * But site-admin is a property of the PERSON, not of their relationship to any one
 * journal. Forcing it into the teams table would require either a sentinel journal_id
 * (a magic 0 row that isn't a journal) or granting the role once per journal (so it
 * would silently fail to apply to a journal created tomorrow). Both are worse than a
 * column.
 *
 * Gate::before reads this. It is the only global bypass in the system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_site_admin')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_site_admin'));
    }
};
