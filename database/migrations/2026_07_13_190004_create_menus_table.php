<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navigation, as data.
 *
 * The navbar and footer currently hold 17 footer links and a mega-menu as literal arrays
 * in TSX. Nine of them point at "#" or at the homepage, and three point the author at the
 * submission wizard when they asked for the fee policy. Links rot; hardcoded links rot
 * invisibly, because nothing can check them.
 *
 * A menu_item points at EXACTLY ONE of: a page, a named route, or an external URL. The
 * three are mutually exclusive and the model enforces it — an item that "sort of" points
 * at two places is how you end up with a Careers link that lands on the homepage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // main | footer-guidelines | footer-explore | ...
            $table->string('name');
            $table->string('location')->default('footer');   // navbar | navbar-mega | footer | legal
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['location', 'sequence']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();

            $table->string('label');
            $table->string('description')->nullable();   // the mega-menu shows these

            // Exactly one of the three. MenuItem::url() resolves whichever is set, and the
            // model refuses to save more than one.
            $table->foreignId('page_id')->nullable()->constrained('pages')->cascadeOnDelete();
            $table->string('route_name')->nullable();     // a NAMED route, so it cannot 404 silently
            $table->string('external_url')->nullable();

            $table->boolean('opens_in_new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['menu_id', 'parent_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
