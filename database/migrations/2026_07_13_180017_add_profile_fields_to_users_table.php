<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authors and reviewers are people with scholarly identities, not just logins.
 * given_name/family_name are separate because Crossref deposits require them
 * separately — you cannot reliably split "Nick Papé" back into parts, and guessing
 * wrong misattributes the work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('given_name')->nullable()->after('name');
            $table->string('family_name')->nullable()->after('given_name');
            $table->string('affiliation')->nullable()->after('family_name');
            $table->string('orcid', 19)->nullable()->after('affiliation');
            $table->string('avatar_path')->nullable()->after('orcid');
            $table->boolean('is_active')->default(true)->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'given_name', 'family_name', 'affiliation', 'orcid', 'avatar_path', 'is_active',
            ]);
        });
    }
};
