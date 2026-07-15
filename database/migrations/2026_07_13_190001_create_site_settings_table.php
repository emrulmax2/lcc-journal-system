<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide content, as a key/value store rather than a wide singleton row.
 *
 * A key/value table means adding a new editable string is a seeder line, not a migration
 * — which matters, because the alternative is what we had: every new piece of copy gets
 * hardcoded into a React component, and six months later the footer is telling users the
 * publisher is fictional and nobody can change it without a deploy.
 *
 * `group` exists so the admin can render one form per section rather than 40 loose fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->default('general');   // general | hero | footer | social | contact
            $table->longText('value')->nullable();

            // Drives the admin control AND the server-side validation. `markdown` is
            // rendered through MarkdownRenderer (raw HTML disallowed); `media` holds a
            // media id; `url` is validated as one.
            $table->enum('type', ['text', 'textarea', 'markdown', 'url', 'email', 'media', 'boolean'])
                ->default('text');

            $table->string('label');
            $table->text('help')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['group', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
