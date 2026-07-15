<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Journal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Exports article metadata in DOAJ's article-upload JSON shape.
 *
 * DOAJ (the Directory of Open Access Journals) is how a great many readers and
 * institutions decide whether a journal is real. Getting in is the point of most of the
 * rest of this system.
 *
 * The command also PRE-FLIGHTS the journal against DOAJ's application criteria, because
 * the criteria are the interesting part and they are not all technical:
 *
 *   - a registered ISSN                (the British Library must have issued one)
 *   - a stated open-access licence     (ours is currently NULL — "The Authors" is a
 *                                       rights holder, not a licence)
 *   - a described peer-review process  (we have one; it must also be documented publicly)
 *   - persistent identifiers           (DOIs — blocked until Crossref issues a prefix)
 *   - machine-readable metadata        (OAI-PMH — done, /oai)
 *
 * Reporting those honestly is more useful than silently producing an export that would be
 * rejected.
 */
class DoajExportCommand extends Command
{
    protected $signature = 'journal:doaj-export {journal : Journal slug} {--out= : Write to this path}';

    protected $description = 'Export a journal to DOAJ article JSON, and check its readiness to apply';

    public function handle(): int
    {
        $journal = Journal::where('slug', $this->argument('journal'))->first();

        if ($journal === null) {
            $this->error("No journal with slug '{$this->argument('journal')}'.");

            return self::FAILURE;
        }

        $this->readiness($journal);

        $articles = Article::query()
            ->published()
            ->where('journal_id', $journal->id)
            ->with(['authors', 'section', 'issue.volume'])
            ->orderBy('published_at')
            ->get();

        if ($articles->isEmpty()) {
            $this->warn('No published articles to export.');

            return self::SUCCESS;
        }

        $payload = $articles->map(fn (Article $a) => $this->article($journal, $a))->all();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // UNESCAPED_UNICODE matters here: DOAJ shows author names to humans, and
        // "Papé" is not a name.
        $path = $this->option('out') ?: "doaj/{$journal->slug}-".now()->format('Y-m-d').'.json';
        Storage::disk('private')->put($path, (string) $json);

        $this->newLine();
        $this->info("Exported {$articles->count()} article(s) → storage/app/private/{$path}");

        return self::SUCCESS;
    }

    private function readiness(Journal $journal): void
    {
        $this->line('DOAJ readiness — '.$journal->title);
        $this->newLine();

        $checks = [
            ['ISSN registered', filled($journal->issn_online), 'The British Library has not issued an ISSN. DOAJ will not accept an application without one.'],
            ['Open-access licence stated', filled($journal->license), 'journals.license is NULL. "The Authors" is a rights holder, not a licence — DOAJ needs e.g. CC BY 4.0.'],
            ['DOI prefix issued', $journal->canMintDois(), 'Crossref has not issued a prefix, so no article carries a persistent identifier yet.'],
            ['Open access', (bool) $journal->open_access, 'The journal is not marked open access.'],
            ['Machine-readable metadata (OAI-PMH)', true, ''],
            ['Peer review implemented', true, ''],
        ];

        foreach ($checks as [$label, $ok, $why]) {
            $this->line(sprintf('  %s  %s', $ok ? '[ok]  ' : '[BLOCK]', $label));

            if (! $ok && $why !== '') {
                $this->line('          '.$why);
            }
        }

        $blocked = collect($checks)->reject(fn ($c) => $c[1])->count();

        $this->newLine();

        if ($blocked > 0) {
            $this->warn("{$blocked} blocker(s). The export below is still valid, but an application would be rejected today.");
        } else {
            $this->info('No blockers — this journal can apply to DOAJ.');
        }
    }

    /** @return array<string, mixed> */
    private function article(Journal $journal, Article $article): array
    {
        return array_filter([
            'bibjson' => array_filter([
                'title' => $article->title,
                'abstract' => $article->abstract,

                // A corporate author IS an author. Emitting an empty list would make DOAJ
                // reject the record outright.
                'author' => $article->hasCorporateAuthor()
                    ? [['name' => $article->corporate_author, 'affiliation' => $journal->publisher]]
                    : $article->authors->map(fn ($a) => array_filter([
                        'name' => $a->fullName(),
                        'affiliation' => $a->affiliation,
                        'orcid_id' => $a->orcidUrl(),   // NULL where none exists. Never invented.
                    ]))->values()->all(),

                'keywords' => $article->keywords ?? [],
                'year' => (string) $article->published_at?->year,
                'month' => (string) $article->published_at?->month,

                'identifier' => array_values(array_filter([
                    filled($journal->issn_online) ? ['type' => 'eissn', 'id' => $journal->issn_online] : null,
                    $article->doi() ? ['type' => 'doi', 'id' => $article->doi()] : null,
                ])),

                'link' => [
                    ['type' => 'fulltext', 'url' => $article->landingUrl(), 'content_type' => 'HTML'],
                ],

                'journal' => array_filter([
                    'title' => $journal->title,
                    'publisher' => $journal->publisher,
                    'license' => $journal->license
                        ? [['type' => $journal->license, 'open_access' => (bool) $journal->open_access]]
                        : null,
                    'language' => ['EN'],
                    'volume' => (string) ($article->issue?->volume?->number ?? ''),
                    'number' => (string) ($article->issue?->number ?? ''),
                    'start_page' => (string) ($article->first_page ?? ''),
                    'end_page' => (string) ($article->last_page ?? ''),
                ]),
            ]),
        ]);
    }
}
