<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'withdrawn' to review_assignments.status so an editor can withdraw an outstanding
 * invitation and bring in a replacement.
 *
 * Raw MODIFY, not $table->enum()->change(): Doctrine DBAL does not model MySQL's ENUM type,
 * so a Schema change would either fail or silently drop the enum to a plain string. The
 * column keeps its NOT NULL and its 'invited' default.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE review_assignments MODIFY status "
            ."ENUM('invited','accepted','declined','report_submitted','withdrawn') NOT NULL DEFAULT 'invited'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE review_assignments MODIFY status "
            ."ENUM('invited','accepted','declined','report_submitted') NOT NULL DEFAULT 'invited'"
        );
    }
};
