<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a reviewer was last nudged, so the reminder command chases a late reviewer without
 * spamming them daily. Null = never reminded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->timestamp('last_reminded_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('last_reminded_at');
        });
    }
};
