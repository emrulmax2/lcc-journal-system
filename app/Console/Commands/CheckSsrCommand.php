<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Inertia\Ssr\BundleDetector;
use Inertia\Ssr\Gateway;
use Inertia\Ssr\HasHealthCheck;

/**
 * Asserts that a published article page is READABLE BY A MACHINE.
 *
 * This command exists because of a specific, nasty failure mode. When the Inertia SSR
 * process dies, Inertia does not error — it falls back to client-side rendering. The
 * site keeps working perfectly for every human who looks at it, including whoever is
 * checking that the site is up. Meanwhile every crawler receives an empty <div id="app">,
 * Google Scholar quietly drops the journal, and the DOIs we paid to register resolve to
 * pages that no index can read. There is no error, no exception, no alert. It is the
 * single most dangerous thing that can happen to this system, and it looks like nothing.
 *
 * So: schedule this. Do not rely on the SSR process reporting its own health, because
 * the whole problem is that it fails by being absent.
 */
class CheckSsrCommand extends Command
{
    protected $signature = 'journal:check-ssr {--url= : Check a specific URL instead of a seeded article}';

    protected $description = 'Assert that public pages render server-side with citation metadata intact';

    public function handle(): int
    {
        $url = $this->option('url') ?: $this->firstPublishedArticleUrl();

        if ($url === null) {
            $this->warn('No published article to check. Publish one, then run this again.');

            return self::SUCCESS;
        }

        $this->line("Fetching (no JavaScript): {$url}");

        try {
            $response = Http::timeout(20)
                // Announce ourselves honestly. Do NOT spoof Googlebot — if the app ever
                // special-cases a user agent, this check would validate a code path that
                // no real crawler takes, and we would be testing a lie.
                ->withHeaders(['User-Agent' => 'MeridianSsrHealthCheck/1.0 (+journal@lcc.ac.uk)'])
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('Request failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $html = $response->body();
        $failures = [];
        $ssrIsDown = false;

        if (! $response->successful()) {
            $failures[] = "HTTP {$response->status()} (expected 200)";
        }

        // 1. The DOI-critical metadata. Rendered by Blade, in PHP — so this must hold
        //    even when the Node SSR process is dead. If THIS fails, something has moved
        //    the meta tags into React, and the DOI programme is broken.
        foreach (['citation_title', 'citation_journal_title', 'citation_author', 'citation_abstract_html_url'] as $tag) {
            if (! str_contains($html, 'name="'.$tag.'"')) {
                $failures[] = "missing meta tag: {$tag}";
            }
        }

        // 2. The body content. This is what depends on the Node SSR process. An empty
        //    app div means SSR is down and we are serving a blank page to every crawler.
        if (preg_match('/<div id="app"[^>]*>\s*<\/div>/', $html)) {
            $ssrIsDown = true;
            $failures[] = 'SSR IS DOWN — <div id="app"> is empty. Humans see a working site; '
                .'crawlers see nothing.';
        }

        // 3. The invisibility trap. framer-motion serialises `initial` variants into the
        //    style attribute, so a stray initial="hidden" ships content at opacity:0.
        //    The page would contain every word and still be unreadable.
        if (preg_match('/<(main|section|div)[^>]*style="[^"]*opacity:\s*0/i', $html)) {
            $failures[] = 'content rendered at opacity:0 — a framer-motion `initial` variant '
                .'has leaked into the server render. See Reveal.tsx / Layout.tsx.';
        }

        if ($failures !== []) {
            $this->newLine();
            $this->error('FAILED — this page is not machine-readable:');
            foreach ($failures as $failure) {
                $this->line('  • '.$failure);
            }

            if ($ssrIsDown) {
                $this->newLine();
                $this->line('Why (checked on THIS machine, in the order Inertia checks them):');
                foreach ($this->diagnose() as $finding) {
                    $this->line('  '.$finding);
                }
            }

            $this->newLine();
            $this->line('Every DOI pointing at this page is currently pointing at nothing a crawler can read.');

            return self::FAILURE;
        }

        $tags = preg_match_all('/name="(citation_[a-z_]+)"/', $html, $m) ? array_unique($m[1]) : [];

        $this->info('OK — page is server-rendered and machine-readable.');
        $this->line('  citation tags present: '.implode(', ', $tags));
        $this->line('  body bytes: '.number_format(strlen($html)));

        return self::SUCCESS;
    }

    private function firstPublishedArticleUrl(): ?string
    {
        return Article::published()->first()?->landingUrl();
    }

    /**
     * A project-relative path with forward slashes — the form you can paste into a shell.
     * Vite::hotFile() concatenates with '/', so on Windows it comes back mixed-separator.
     */
    private function relative(string $path): string
    {
        $normalised = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', base_path()).'/';

        return str_replace('//', '/', str_replace($base, '', $normalised));
    }

    /**
     * Name the reason SSR fell back, instead of guessing "start the process".
     *
     * "Start the SSR process" was the only advice this command ever gave, and it is wrong
     * whenever the process IS running — which is the case that actually costs hours. There
     * are FIVE distinct ways Inertia\Ssr\HttpGateway::dispatch() returns null, and every one
     * of them looks identical from outside: an empty <div id="app"> and no error anywhere.
     * The checks below are that method's own guards, in its own order, so the first ✗ is
     * the reason.
     *
     * These describe the machine running this command. Against a --url on another host they
     * are still worth printing, but read them as "what MY config would do".
     *
     * @return list<string>
     */
    private function diagnose(): array
    {
        $findings = [];

        // 1. The config flag. On a deploy that ran `config:cache` BEFORE .env had
        //    INERTIA_SSR_ENABLED=true, the cached config wins and nothing else matters.
        if (! config('inertia.ssr.enabled', true)) {
            return ['✗ SSR is DISABLED in config (inertia.ssr.enabled = false). '
                .'Set INERTIA_SSR_ENABLED=true in .env, then: php artisan config:clear && php artisan config:cache'];
        }
        $findings[] = '✓ enabled in config';

        // 2. The hot file. This one is vicious on a hand-uploaded deploy: `public/hot` is
        //    gitignored, so it never arrives by git — but it DOES arrive inside a zip or an
        //    FTP mirror of a dev machine. When it exists, Inertia posts the page to Vite's
        //    dev server instead of the SSR server, the SSR process is never contacted at
        //    all, and the connection failure is swallowed.
        if (Vite::isRunningHot()) {
            $hot = @file_get_contents(Vite::hotFile()) ?: 'unknown';

            return array_merge($findings, [
                '✗ public/hot EXISTS — Inertia is posting to Vite at '.trim($hot).'/__inertia_ssr,',
                '  NOT to the SSR server. On a production box this file should not exist.',
                '  Fix: rm '.$this->relative(Vite::hotFile()),
            ]);
        }
        $findings[] = '✓ no public/hot (not in Vite dev mode)';

        // 3. The bundle. Also gitignored — it travels by rsync from CI, never by git pull.
        //    A hand deploy that only ran `git pull` has no bundle and SSR can never start.
        if (app(BundleDetector::class)->detect() === null) {
            return array_merge($findings, [
                '✗ NO SSR BUNDLE — bootstrap/ssr/ssr.js is missing. It is gitignored, so',
                '  `git pull` will never deliver it; it is built by `npm run build` and',
                '  rsynced by the deploy workflow. Build it and upload bootstrap/ssr/.',
            ]);
        }
        $findings[] = '✓ SSR bundle present';

        // 4. The process itself. Only NOW is "start the SSR process" the right advice.
        $gateway = app(Gateway::class);

        if ($gateway instanceof HasHealthCheck && ! $gateway->isHealthy()) {
            return array_merge($findings, [
                '✗ nothing is answering on '.config('inertia.ssr.url', 'http://127.0.0.1:13714').' —',
                '  the SSR process is not listening. Start/restart it (docs/DEPLOYMENT.md §6):',
                '  systemctl status jcdm-ssr   — or —   php artisan inertia:start-ssr',
            ]);
        }
        $findings[] = '✓ SSR server is answering';

        // 5. Everything is wired up, so the render itself threw. Node logs it; PHP does not.
        return array_merge($findings, [
            '✗ all four checks pass, so the RENDER is failing — the SSR server is up but',
            '  returning an error for this page. The stack trace is in the Node process\'s',
            '  output, not in Laravel: storage/logs/ssr.log, or journalctl -u jcdm-ssr -n 50.',
            '  A stale bundle is the usual cause: rebuild and redeploy bootstrap/ssr/.',
        ]);
    }
}
