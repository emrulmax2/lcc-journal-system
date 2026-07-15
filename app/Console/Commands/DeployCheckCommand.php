<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A pre/post-deploy health check for the whole application.
 *
 * The deploy pipeline runs this on the server after every deploy. It is the difference
 * between "the deploy script exited 0" and "the site is actually serving correctly" —
 * those are not the same thing, and on a system where a dead SSR process looks fine in a
 * browser and a wrong charset corrupts author names silently, the gap between them is
 * where outages hide.
 *
 * Each check is PASS, WARN or FAIL. Any FAIL exits non-zero, so the deploy job goes red
 * instead of quietly shipping a broken site. WARN never fails the build — it flags things
 * that are suboptimal but not broken (e.g. config not cached).
 *
 * This is the STATIC/config half. The live "is the page machine-readable right now" half
 * is `journal:check-ssr`, which the deploy script runs straight after this one.
 */
class DeployCheckCommand extends Command
{
    protected $signature = 'deploy:check {--skip-migrations : Do not fail on pending migrations (e.g. checking before the migrate step)}';

    protected $description = 'Verify the deployment is healthy: environment, database, assets, SSR bundle, storage and queue';

    /** @var array<int, array{0: string, 1: string, 2: string}> [status, check, detail] */
    private array $results = [];

    private bool $failed = false;

    public function handle(): int
    {
        $this->line('Deploy check — '.config('app.name').' ('.app()->environment().')');
        $this->newLine();

        $this->checkAppKey();
        $this->checkEnvironment();
        $this->checkDebug();
        $this->checkUrl();
        $this->checkDatabase();
        $this->checkCharset();
        $this->checkMigrations();
        $this->checkStorageLink();
        $this->checkWritablePaths();
        $this->checkViteManifest();
        $this->checkSsrBundle();
        $this->checkSsrConfig();
        $this->checkConfigCached();
        $this->checkQueue();
        $this->checkCrossref();
        $this->checkDoiReadiness();

        $this->render();

        if ($this->failed) {
            $this->newLine();
            $this->error('DEPLOY CHECK FAILED — do not consider this deploy live. Fix the FAIL rows above.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Deploy check passed. Now run `php artisan journal:check-ssr` to confirm the live page is machine-readable.');

        return self::SUCCESS;
    }

    // --- Checks -------------------------------------------------------------

    private function checkAppKey(): void
    {
        $this->assert('Application key', filled(config('app.key')),
            fail: 'APP_KEY is empty. Run `php artisan key:generate`. Sessions and encrypted values (incl. the Crossref password) depend on it.');
    }

    private function checkEnvironment(): void
    {
        $env = app()->environment();

        // On the server this should be production. Locally it is fine that it is not — this
        // is a warning, never a failure, so `deploy:check` is still useful in development.
        $this->result($env === 'production' ? 'pass' : 'warn', 'Environment', "APP_ENV = {$env}");
    }

    private function checkDebug(): void
    {
        $debug = (bool) config('app.debug');

        if (app()->environment('production') && $debug) {
            $this->result('fail', 'Debug mode', 'APP_DEBUG is TRUE in production — a stack trace on a public page leaks paths, queries and env. Set APP_DEBUG=false.');
            $this->failed = true;

            return;
        }

        $this->result($debug ? 'warn' : 'pass', 'Debug mode', 'APP_DEBUG = '.($debug ? 'true' : 'false'));
    }

    private function checkUrl(): void
    {
        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');

        if (app()->environment('production') && ! $https) {
            // citation_pdf_url / citation_abstract_html_url are built from APP_URL. An
            // http:// URL advertised on an https:// site reads to Scholar as a different
            // page from the one the DOI resolves to — a common, silent indexing failure.
            $this->result('fail', 'App URL', "APP_URL is '{$url}' — must be https:// in production, or citation URLs will mismatch the canonical page.");
            $this->failed = true;

            return;
        }

        $this->result($https ? 'pass' : 'warn', 'App URL', $url);
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->result('pass', 'Database connection', DB::connection()->getDatabaseName());
        } catch (Throwable $e) {
            $this->result('fail', 'Database connection', 'Cannot connect: '.str($e->getMessage())->limit(120));
            $this->failed = true;
        }
    }

    private function checkCharset(): void
    {
        try {
            $charset = DB::selectOne('SELECT @@character_set_database AS cs')->cs ?? '';

            if (! str_starts_with((string) $charset, 'utf8mb4')) {
                // The whole reason this check exists: a latin1 database corrupts author
                // diacritics (Papé, Ramírez) SILENTLY, and the corruption is then deposited
                // to Crossref and every index. No error is ever raised — only this check.
                $this->result('fail', 'Database charset', "Database charset is '{$charset}', not utf8mb4. Author names with diacritics will corrupt silently.");
                $this->failed = true;

                return;
            }

            $this->result('pass', 'Database charset', $charset);
        } catch (Throwable $e) {
            $this->result('warn', 'Database charset', 'Could not read charset: '.str($e->getMessage())->limit(80));
        }
    }

    private function checkMigrations(): void
    {
        if ($this->option('skip-migrations')) {
            $this->result('warn', 'Migrations', 'skipped (--skip-migrations)');

            return;
        }

        try {
            // Exit code of `migrate:status` alone is not enough; parse for a Pending row.
            $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
                ->map(fn ($path) => app('migrator')->getMigrationName($path))
                ->reject(fn ($name) => in_array($name, app('migration.repository')->getRan(), true))
                ->count();

            if ($pending > 0) {
                $this->result('fail', 'Migrations', "{$pending} pending migration(s). Run `php artisan migrate --force`.");
                $this->failed = true;

                return;
            }

            $this->result('pass', 'Migrations', 'all applied');
        } catch (Throwable $e) {
            $this->result('warn', 'Migrations', 'Could not determine status: '.str($e->getMessage())->limit(80));
        }
    }

    private function checkStorageLink(): void
    {
        $link = public_path('storage');

        // Uploaded media (journal covers, the hero) is served through this symlink. Without
        // it every image on the site 404s.
        $this->result(is_link($link) || is_dir($link) ? 'pass' : 'warn', 'Storage symlink',
            is_link($link) || is_dir($link) ? 'public/storage present' : 'missing — run `php artisan storage:link`');
    }

    private function checkWritablePaths(): void
    {
        foreach (['storage/framework' => storage_path('framework'), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            if (! is_writable($path)) {
                $this->result('fail', "Writable: {$label}", 'not writable — caches, sessions and compiled views cannot be written.');
                $this->failed = true;
            } else {
                $this->result('pass', "Writable: {$label}", 'ok');
            }
        }
    }

    private function checkViteManifest(): void
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            $this->result('fail', 'Frontend assets', 'public/build/manifest.json is missing — `npm run build` did not run or was not uploaded. Every page will 500 on @vite.');
            $this->failed = true;

            return;
        }

        $this->result('pass', 'Frontend assets', 'build manifest present');
    }

    private function checkSsrBundle(): void
    {
        // Without the SSR bundle, Inertia falls back to client rendering: humans see a
        // working site, crawlers see an empty <div id="app"> and every DOI rots. The Blade
        // citation tags are the floor beneath that, but the body content still needs this.
        foreach (['bootstrap/ssr/ssr.js', 'bootstrap/ssr/ssr.mjs'] as $candidate) {
            if (is_file(base_path($candidate))) {
                $this->result('pass', 'SSR bundle', $candidate.' present');

                return;
            }
        }

        $this->result('fail', 'SSR bundle', 'bootstrap/ssr/ssr.js is missing — `vite build --ssr` did not run or was not uploaded. The public site will not be server-rendered.');
        $this->failed = true;
    }

    private function checkSsrConfig(): void
    {
        $enabled = (bool) config('inertia.ssr.enabled', true);
        $this->result($enabled ? 'pass' : 'warn', 'SSR enabled',
            $enabled ? 'INERTIA_SSR_ENABLED=true — verify the process is running with journal:check-ssr' : 'INERTIA_SSR_ENABLED is off — the public site is NOT server-rendered');
    }

    private function checkConfigCached(): void
    {
        // A warning, not a failure — the site works uncached, just slower. But an uncached
        // config in production is a missed `config:cache`, worth flagging.
        $cached = is_file(base_path('bootstrap/cache/config.php'));

        if (app()->environment('production')) {
            $this->result($cached ? 'pass' : 'warn', 'Config cache',
                $cached ? 'cached' : 'not cached — run `php artisan config:cache route:cache view:cache`');
        } else {
            $this->result('pass', 'Config cache', 'n/a outside production');
        }
    }

    private function checkQueue(): void
    {
        $driver = (string) config('queue.default');

        if ($driver === 'sync') {
            // Crossref deposits run on the queue precisely so publishing never blocks on
            // Crossref being up. On `sync`, the deposit runs INSIDE the publish request —
            // a Crossref timeout would then stall, or roll back, a publication.
            $this->result('warn', 'Queue driver', "queue.default = sync — Crossref deposits would run inline. Use 'database' (or redis) plus a worker.");

            return;
        }

        if ($driver === 'database' && ! Schema::hasTable('jobs')) {
            $this->result('fail', 'Queue driver', "queue.default = database but the 'jobs' table is missing. Run migrations.");
            $this->failed = true;

            return;
        }

        $this->result('pass', 'Queue driver', $driver);
    }

    private function checkCrossref(): void
    {
        $endpoint = (string) config('crossref.endpoint');

        // Not a pass/fail — a deliberate, loud statement of which endpoint is live, because
        // 'production' spends real money and mints permanent identifiers.
        $this->result($endpoint === 'production' ? 'warn' : 'pass', 'Crossref endpoint',
            $endpoint === 'production' ? 'PRODUCTION — deposits mint real, permanent DOIs' : $endpoint.' (safe)');
    }

    private function checkDoiReadiness(): void
    {
        // Informational: JCDMS ships with NULL prefix/ISSN by design, so this is never a
        // failure — it just reports how many active journals can currently register DOIs.
        try {
            if (! Schema::hasTable('journals')) {
                return;
            }

            $active = DB::table('journals')->where('is_active', true)->count();
            $mintable = DB::table('journals')->where('is_active', true)->whereNotNull('doi_prefix')->count();

            $this->result('pass', 'DOI readiness', "{$mintable}/{$active} active journal(s) have a Crossref prefix (NULL is deliberate until issued)");
        } catch (Throwable) {
            // Non-fatal — the database checks above already covered connectivity.
        }
    }

    // --- Reporting ----------------------------------------------------------

    private function assert(string $check, bool $ok, string $fail): void
    {
        if ($ok) {
            $this->result('pass', $check, 'ok');

            return;
        }

        $this->result('fail', $check, $fail);
        $this->failed = true;
    }

    private function result(string $status, string $check, string $detail): void
    {
        $this->results[] = [$status, $check, $detail];
    }

    private function render(): void
    {
        $icon = ['pass' => '<fg=green>PASS</>', 'warn' => '<fg=yellow>WARN</>', 'fail' => '<fg=red>FAIL</>'];

        foreach ($this->results as [$status, $check, $detail]) {
            $this->line(sprintf('  %s  %-22s %s', $icon[$status], $check, $detail));
        }
    }
}
