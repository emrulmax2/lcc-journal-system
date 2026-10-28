<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Enums\IssueStatus;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Media;
use App\Models\Volume;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the converted print back-catalogue (the jcdms-archive folder) into the journal.
 *
 * The archive is one folder per issue, one folder per article:
 *   <issue>/issue.json, cover.jpg
 *   <issue>/articles/NN-slug/article.json, body.md, article.pdf, figures/*.png
 *
 * EVERYTHING IS IMPORTED AS A DRAFT. Publishing freezes the slug and the DOI suffix for
 * ever, so that stays a human decision made in the admin (Publish issue / Publish article),
 * after an editor has checked the import. This command never publishes anything.
 *
 * Safe to re-run. Articles are matched on (issue, first page), so a second run updates the
 * drafts it created instead of duplicating them. Published or withdrawn articles are never
 * touched — they are the permanent record.
 *
 * Existing drafts (e.g. the seeded Vol 10 No 2 articles) keep their slug, title, abstract,
 * keywords, authors and PDF unless --overwrite is given; the archive only fills what is
 * blank. Body, footnotes, figures and references always come from the archive.
 *
 * Front and back matter (editorial panel, contents, contributors, guidelines) are skipped:
 * they are not articles, have no authors or abstract, and must not get DOIs.
 */
class ImportArchiveCommand extends Command
{
    protected $signature = 'journal:import-archive
        {path : The jcdms-archive folder (e.g. C:/wamp64/www/jcdms-archive)}
        {--journal=jcdms : Slug of the journal to import into}
        {--issue=* : Only these issue folders, e.g. --issue=vol05-no1-2017}
        {--overwrite : Replace title, abstract, keywords, authors and PDF on existing drafts too}
        {--dry-run : Show what would happen without writing anything}';

    protected $description = 'Import the converted JCD&MS print archive (issues, articles, figures, PDFs) as drafts';

    private const SKIPPED_TYPES = ['front-matter', 'back-matter'];

    /** @var array<string, int> */
    private array $tally = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'figures' => 0, 'references' => 0];

    /** @var list<string> */
    private array $needsAttention = [];

    public function handle(): int
    {
        $root = rtrim(str_replace('\\', '/', (string) $this->argument('path')), '/');

        if (! is_dir($root)) {
            $this->error("No archive folder at {$root}");

            return self::FAILURE;
        }

        $journal = Journal::where('slug', $this->option('journal'))->first();

        if (! $journal instanceof Journal) {
            $this->error("No journal with slug '{$this->option('journal')}'.");

            return self::FAILURE;
        }

        $issueDirs = collect(glob($root.'/vol*/issue.json') ?: [])
            ->map(fn (string $f) => dirname($f))
            ->when($this->option('issue') !== [], fn ($c) => $c->filter(
                fn (string $d) => in_array(basename($d), $this->option('issue'), true)
            ))
            ->sort()
            ->values();

        if ($issueDirs->isEmpty()) {
            $this->error('No issue folders found (looked for vol*/issue.json).');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        foreach ($issueDirs as $dir) {
            $this->importIssue($journal, $dir);
        }

        $this->newLine();
        $this->info(sprintf(
            '%d created, %d updated, %d skipped · %d figures · %d references',
            $this->tally['created'], $this->tally['updated'], $this->tally['skipped'],
            $this->tally['figures'], $this->tally['references'],
        ));

        if ($this->needsAttention !== []) {
            $this->newLine();
            $this->warn('Needs an editor before it can be published:');
            foreach ($this->needsAttention as $line) {
                $this->line("  - {$line}");
            }
        }

        $this->newLine();
        $this->line('Everything was imported as a DRAFT. Check each issue in the admin, set its publication');
        $this->line('date, then publish it there. Publishing freezes the URLs and DOI suffixes permanently.');

        return self::SUCCESS;
    }

    private function importIssue(Journal $journal, string $dir): void
    {
        $meta = $this->readJson($dir.'/issue.json');
        $label = "Vol {$meta['volume']} No {$meta['number']}";
        $dry = (bool) $this->option('dry-run');

        $this->newLine();
        $this->line("<options=bold>{$label} · {$meta['season']}</> ({$dir})");

        $volume = Volume::where('journal_id', $journal->id)->where('number', $meta['volume'])->first();
        $issue = $volume ? Issue::where('volume_id', $volume->id)->where('number', $meta['number'])->first() : null;

        if (! $dry) {
            // Existing rows keep their own year/season/status — the database is the record,
            // the archive only fills in what is missing.
            $volume ??= Volume::create(['journal_id' => $journal->id, 'number' => $meta['volume'], 'year' => $meta['year']]);
            $issue ??= Issue::create([
                'volume_id' => $volume->id,
                'number' => $meta['number'],
                'season' => $meta['season'],
                'status' => IssueStatus::Draft,
            ]);

            $this->attachCover($issue, $dir, $meta, $label);
        }

        $this->line('  issue: '.($issue?->wasRecentlyCreated || ! $issue ? 'new (draft)' : 'exists ('.$issue->status->value.')'));

        if ($issue?->status === IssueStatus::Published) {
            $this->needsAttention[] = "{$label} is already marked published; publish its new articles one by one.";
        }

        $sequence = 0;

        foreach ($meta['articles'] as $item) {
            if (in_array($item['type'], self::SKIPPED_TYPES, true)) {
                continue;
            }

            $sequence++;   // position among real articles — the DOI suffix is derived from it
            $this->importArticle($journal, $issue, $dir.'/'.$item['folder'], $sequence, $label);
        }
    }

    private function importArticle(Journal $journal, ?Issue $issue, string $dir, int $sequence, string $issueLabel): void
    {
        $data = $this->readJson($dir.'/article.json');
        $where = "{$issueLabel} #{$sequence}";
        $short = Str::limit($data['title'], 60);

        $existing = $issue ? $this->findExisting($issue, $data, $sequence) : null;

        if ($existing?->isFrozen()) {
            $this->line("  <fg=gray>skip</>    {$where} {$short} — already {$existing->status->value}");
            $this->tally['skipped']++;

            return;
        }

        if ($existing && $existing->first_page !== null && (int) $existing->first_page !== (int) $data['first_page']) {
            $this->line("  <fg=yellow>skip</>    {$where} {$short} — position {$sequence} is held by \"{$existing->title}\"");
            $this->needsAttention[] = "{$where}: position clash with an existing article; not imported.";
            $this->tally['skipped']++;

            return;
        }

        $verb = $existing ? 'update' : 'create';
        $this->line("  <fg=green>{$verb}</>  {$where} {$short}");

        if (blank($data['abstract'])) {
            $this->needsAttention[] = "{$where} ({$data['section']}): no abstract — publishing requires one.";
        }

        if ($this->option('dry-run')) {
            $this->tally[$existing ? 'updated' : 'created']++;

            return;
        }

        DB::transaction(function () use ($journal, $issue, $dir, $data, $sequence, $existing): void {
            $overwrite = $existing === null || (bool) $this->option('overwrite');
            $article = $existing ?? new Article(['journal_id' => $journal->id, 'status' => ArticleStatus::Draft]);

            $article->fill([
                'issue_id' => $issue->id,
                'journal_section_id' => $this->section($journal, $data['section'])->id,
                'sequence' => $sequence,
                'first_page' => $data['first_page'],
                'last_page' => $data['last_page'],
            ]);

            if ($existing === null) {
                $article->slug = $this->uniqueSlug($data['slug'], $data);
            }

            foreach (['title' => $data['title'], 'abstract' => $data['abstract'], 'corporate_author' => $data['corporate_author']] as $field => $value) {
                if ($overwrite || blank($article->{$field})) {
                    $article->{$field} = filled($value) ? $value : $article->{$field};
                }
            }

            if ($overwrite || blank($article->keywords)) {
                $article->keywords = $data['keywords'] ?: $article->keywords;
            }

            $article->save();   // needs an id before figures can be stored under it

            $article->body = $this->body($article, $dir, $data);
            $article->save();

            if ($overwrite || $article->authors()->doesntExist()) {
                $this->replaceAuthors($article, $data);
            }

            $this->replaceReferences($article, $data);
            $this->attachPdf($article, $dir, $data, $overwrite);
        });

        $this->tally[$existing ? 'updated' : 'created']++;
    }

    /** Same issue and same first page = same article. Falls back to the position. */
    private function findExisting(Issue $issue, array $data, int $sequence): ?Article
    {
        $query = Article::where('issue_id', $issue->id);

        if ($data['first_page'] !== null) {
            $byPage = (clone $query)->where('first_page', $data['first_page'])->first();
            if ($byPage) {
                return $byPage;
            }
        }

        return $query->where('sequence', $sequence)->first();
    }

    private function section(Journal $journal, string $name): JournalSection
    {
        return JournalSection::firstOrCreate(
            ['journal_id' => $journal->id, 'name' => $name],
            ['sequence' => (int) JournalSection::where('journal_id', $journal->id)->max('sequence') + 1, 'is_active' => true, 'doi_eligible' => true],
        );
    }

    /** Slugs are unique across ALL journals (the URL is /articles/{slug}). */
    private function uniqueSlug(string $slug, array $data): string
    {
        $slug = Str::slug($slug) ?: 'article';

        if (! Article::where('slug', $slug)->exists()) {
            return $slug;
        }

        $candidate = "{$slug}-v{$data['volume']}n{$data['number']}";

        for ($n = 2; Article::where('slug', $candidate)->exists(); $n++) {
            $candidate = "{$slug}-v{$data['volume']}n{$data['number']}-{$n}";
        }

        return $candidate;
    }

    /**
     * body.md with its figure links pointed at the public disk, plus the footnotes as a
     * Notes section (articles have no separate footnote column).
     */
    private function body(Article $article, string $dir, array $data): string
    {
        $body = (string) file_get_contents($dir.'/body.md');
        $disk = Storage::disk('public');
        $target = "articles/{$article->id}/figures";

        $disk->deleteDirectory($target);   // re-import replaces the figure set wholesale

        $body = (string) preg_replace_callback('/\]\((figures\/[^)\s]+)\)/', function (array $m) use ($dir, $disk, $target): string {
            $source = $dir.'/'.$m[1];

            if (! is_file($source)) {
                throw new RuntimeException("Figure missing: {$source}");
            }

            $path = $target.'/'.basename($m[1]);
            $disk->put($path, (string) file_get_contents($source));
            $this->tally['figures']++;

            return ']('.$disk->url($path).')';
        }, $body);

        $notes = array_values(array_filter($data['notes'] ?? []));

        if ($notes !== []) {
            $body = rtrim($body)."\n\n## Notes\n\n".implode("\n\n", $notes)."\n";
        }

        return $body;
    }

    private function replaceAuthors(Article $article, array $data): void
    {
        $article->authors()->delete();

        if (filled($article->corporate_author)) {
            return;   // a corporate author and named people are mutually exclusive
        }

        foreach ($data['authors'] as $i => $author) {
            $article->authors()->create([
                'given_name' => $author['given_name'],
                'family_name' => $author['family_name'],
                'affiliation' => $author['affiliation'] ? Str::limit($author['affiliation'], 252) : null,
                'orcid' => $author['orcid'] ?: null,   // only ever what the PDF printed
                'email' => $author['email'] ?: null,
                'is_corresponding' => (bool) ($author['is_corresponding'] ?? false),
                'sequence' => $i + 1,                  // author order is meaningful
            ]);
        }
    }

    private function replaceReferences(Article $article, array $data): void
    {
        $article->references()->delete();

        foreach ($data['references'] as $i => $reference) {
            $article->references()->create([
                'ordinal' => $i + 1,
                'raw_text' => $reference['raw_text'],
                'doi' => $reference['doi'] ?: null,
            ]);
            $this->tally['references']++;
        }
    }

    /** PDFs live on the private disk and are streamed through the stable public route. */
    private function attachPdf(Article $article, string $dir, array $data, bool $overwrite): void
    {
        $source = $dir.'/article.pdf';
        $existing = $article->files()->where('type', ArticleFileType::Pdf)->first();

        if (! is_file($source) || ($existing && ! $overwrite && Storage::disk('private')->exists($existing->path))) {
            return;
        }

        $path = 'articles/'.Str::random(40).'.pdf';
        Storage::disk('private')->put($path, (string) file_get_contents($source));

        $attributes = [
            'type' => ArticleFileType::Pdf,
            'path' => $path,
            'label' => 'Full text (PDF)',
            'original_name' => sprintf('JCDMS-Vol%d-No%d-pp%s-%s.pdf', $data['volume'], $data['number'], $data['first_page'], $data['last_page']),
            'mime_type' => 'application/pdf',
            'size_bytes' => filesize($source),
        ];

        if ($existing) {
            $old = $existing->path;
            $existing->update($attributes);   // new bytes first, then drop the old ones
            if ($old !== $path) {
                Storage::disk('private')->delete($old);
            }

            return;
        }

        $article->files()->create($attributes);
    }

    private function attachCover(Issue $issue, string $dir, array $meta, string $label): void
    {
        $source = $dir.'/'.($meta['cover'] ?? 'cover.jpg');

        if (filled($issue->cover_path) || ! is_file($source)) {
            return;
        }

        $path = sprintf('issues/jcdms-v%02dn%d-cover.jpg', $meta['volume'], $meta['number']);
        Storage::disk('public')->put($path, (string) file_get_contents($source));
        $size = @getimagesize($source);

        $media = Media::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => basename($path),
            'mime_type' => 'image/jpeg',
            'size_bytes' => filesize($source),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'alt' => "Cover of JCD&MS {$label}, {$meta['season']}",
        ]);

        $issue->update(['cover_path' => $path, 'cover_media_id' => $media->id]);
    }

    /** @return array<string, mixed> */
    private function readJson(string $file): array
    {
        $data = json_decode((string) @file_get_contents($file), true);

        if (! is_array($data)) {
            throw new RuntimeException("Unreadable JSON: {$file}");
        }

        return $data;
    }
}
