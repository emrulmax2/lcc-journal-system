<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\Field;
use App\Models\Journal;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes every journal EXCEPT the one(s) named in --keep — built to clear the six invented
 * "Meridian" demo journals (DemoSeeder) off a server that should carry JCD&MS only.
 *
 * THIS DELETES DATA, INCLUDING PUBLISHED ARTICLES. That is normally impossible — the
 * ArticleObserver refuses to delete a published article, because a real one is a permanent
 * record with a live DOI. Demo articles are not a record of anything, so this command deletes
 * them with the query builder, which bypasses the observer. It is therefore guarded:
 *
 *   - it lists exactly what will go and asks before doing anything (--force skips the
 *     question, for scripts; --dry-run only lists);
 *   - it REFUSES to touch a journal that has submissions or issues — real editorial work
 *     or a print run, i.e. not demo content — unless that journal is named with --allow;
 *   - everything happens in one transaction; the stored PDFs are removed only after commit.
 *
 * Removed with each journal: its articles (and their authors, references, files, metrics,
 * deposit items), sections, volumes, metrics, DOI deposits, its per-journal role
 * assignments, and any field (subject area) no other journal uses. Research topics that
 * pointed at it are kept, unlinked. Media files are kept — the homepage may use them.
 */
class PruneJournalsCommand extends Command
{
    protected $signature = 'journal:prune-journals
        {--keep=* : Slug(s) of the journal(s) to KEEP (default: jcdms)}
        {--allow=* : Also allow removing these journals even though they have submissions or issues}
        {--dry-run : List what would be removed, change nothing}
        {--force : Do not ask for confirmation}';

    protected $description = 'Remove every journal except the kept one(s) — e.g. the demo journals';

    public function handle(): int
    {
        $keep = $this->option('keep') ?: ['jcdms'];
        $allow = $this->option('allow');

        $kept = Journal::whereIn('slug', $keep)->pluck('slug')->all();
        if (array_diff($keep, $kept) !== []) {
            $this->error('No journal with slug: '.implode(', ', array_diff($keep, $kept)).'. Nothing removed.');

            return self::FAILURE;
        }

        $doomed = Journal::query()
            ->whereNotIn('slug', $keep)
            ->withCount([
                'articles',
                'articles as published_count' => fn ($q) => $q->where('status', 'published'),
            ])
            ->orderBy('id')
            ->get();

        if ($doomed->isEmpty()) {
            $this->info('Nothing to remove — only '.implode(', ', $kept).' exists.');

            return self::SUCCESS;
        }

        $blocked = [];
        $rows = [];
        foreach ($doomed as $journal) {
            $submissions = Submission::where('journal_id', $journal->id)->count();
            $issues = DB::table('issues')->join('volumes', 'volumes.id', '=', 'issues.volume_id')
                ->where('volumes.journal_id', $journal->id)->count();

            if (($submissions > 0 || $issues > 0) && ! in_array($journal->slug, $allow, true)) {
                $blocked[] = "{$journal->slug} ({$submissions} submissions, {$issues} issues)";
            }

            $rows[] = [$journal->slug, $journal->title, $journal->articles_count, $journal->published_count, $submissions, $issues];
        }

        $this->line('Keeping: <info>'.implode(', ', $kept).'</info>');
        $this->line('Removing:');
        $this->table(['Slug', 'Title', 'Articles', 'Published', 'Submissions', 'Issues'], $rows);

        if ($blocked !== []) {
            $this->error('Refusing: these look like REAL journals, not demo content — '.implode('; ', $blocked).'.');
            $this->line('If you are sure, name each one with --allow=<slug>. Nothing was removed.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — nothing was removed.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Permanently remove these journals and everything in them?', false)) {
            $this->line('Cancelled. Nothing was removed.');

            return self::FAILURE;
        }

        $ids = $doomed->pluck('id')->all();
        $files = ArticleFile::whereIn('article_id', Article::whereIn('journal_id', $ids)->select('id'))->pluck('path')->all();

        $counts = DB::transaction(function () use ($ids): array {
            // Submissions first (only reachable via --allow): their files, reviews and
            // events cascade from the submission row.
            $submissions = DB::table('submissions')->whereIn('journal_id', $ids)->delete();

            // Query builder, not Eloquent: the observer refuses to delete published articles,
            // and these are exactly the ones being cleared. Authors, references, files,
            // metrics and deposit items cascade.
            $articles = DB::table('articles')->whereIn('journal_id', $ids)->delete();

            // Issues hold their volume with restrictOnDelete, so they go before the journal
            // cascades the volumes away.
            DB::table('issues')->whereIn('volume_id', DB::table('volumes')->whereIn('journal_id', $ids)->select('id'))->delete();

            // Per-journal roles live in Spatie's team column, which has no foreign key.
            $team = config('permission.column_names.team_foreign_key', 'journal_id');
            $roles = DB::table('model_has_roles')->whereIn($team, $ids)->delete();
            if (Schema::hasColumn('model_has_permissions', $team)) {
                DB::table('model_has_permissions')->whereIn($team, $ids)->delete();
            }

            $fieldIds = DB::table('journals')->whereIn('id', $ids)->whereNotNull('field_id')->pluck('field_id')->unique();
            $journals = DB::table('journals')->whereIn('id', $ids)->delete();   // sections, volumes, metrics, deposits cascade

            // A subject area that only the removed journals used.
            $fields = Field::whereIn('id', $fieldIds)->whereDoesntHave('journals')->delete();

            return compact('journals', 'articles', 'submissions', 'roles', 'fields');
        });

        // Bytes after the commit: a rolled-back transaction must not leave articles whose
        // PDFs have already been destroyed.
        foreach ($files as $path) {
            Storage::disk('private')->delete($path);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf(
            'Removed %d journals, %d articles (%d PDFs), %d submissions, %d role assignments, %d unused fields.',
            $counts['journals'], $counts['articles'], count($files), $counts['submissions'], $counts['roles'], $counts['fields'],
        ));
        $this->line('Now run: php artisan journal:compute-metrics && php artisan optimize:clear');

        return self::SUCCESS;
    }
}
