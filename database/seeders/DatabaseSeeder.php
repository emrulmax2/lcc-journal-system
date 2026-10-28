<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            JcdmsSeeder::class,   // REAL content. Safe — and required — in production.

            // The site's chrome: settings, pages, menus, homepage sections. This is REAL
            // content, not a demo fixture — it is what replaces the hardcoded (and in
            // several places false) copy that used to live in the React components.
            // Re-running it never clobbers an editor's changes.
            CmsSeeder::class,
        ]);

        // LocalDevSeeder  — login accounts, a manuscript in peer review, placeholder PDFs
        //                   (only for articles that have no real PDF yet).
        //
        // DemoSeeder (six invented "Meridian" journals) is NO LONGER run by default: the
        // site is JCD&MS only. It is kept for exercising the continuous-publication UI —
        // run it explicitly with `php artisan db:seed --class=DemoSeeder`, never in production.
        //
        // Guarded by environment, not by a flag someone can forget to pass.
        if (! app()->environment('production')) {
            $this->call([
                LocalDevSeeder::class,
            ]);
        }
    }
}
