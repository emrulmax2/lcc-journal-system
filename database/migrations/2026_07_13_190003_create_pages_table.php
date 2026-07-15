<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable content pages: author guidelines, publication ethics, APC/fees, privacy,
 * terms, accessibility statement, contact, help.
 *
 * Every one of these is currently a link in the navbar or footer that goes to "#" or to
 * the homepage. An "Accessibility statement" link that goes nowhere is not a missing
 * feature, it is itself an accessibility failure — and "Article processing charges" is
 * the link an author clicks to find out what it costs.
 *
 * BODY IS MARKDOWN, and raw HTML is disallowed when it is rendered (see MarkdownRenderer).
 * A WYSIWYG storing HTML would hand every editor a stored-XSS vector on the public site.
 * For a publisher whose entire product is credibility, that is not a trade worth making.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->longText('body')->nullable();          // markdown
            $table->foreignId('hero_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            // Some pages are structural (the footer's legal links point at them) and must
            // not be deletable from the admin, or the footer starts 404ing.
            $table->boolean('is_system')->default(false);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
