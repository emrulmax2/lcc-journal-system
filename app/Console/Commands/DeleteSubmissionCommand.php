<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes TEST submissions — e.g. `journal:delete-submission JCDMS-2026-0002`.
 *
 * Real submissions are an editorial record (who submitted what, who reviewed it, what was
 * decided) and are never deleted from the admin. This exists for the manuscripts sent in
 * while trying the system out. It is therefore guarded:
 *
 *   - it shows each submission and asks before deleting (--force skips the question;
 *     --dry-run only shows);
 *   - it REFUSES a submission that was accepted and became an article — that article may be
 *     scheduled or published, and has to be dealt with in the admin first.
 *
 * Deleted with it (database cascades): its authors, file records, events, review rounds,
 * reviewer assignments and reports, decisions and discussions. The uploaded files are then
 * removed from the private disk. Each deletion is written to the log.
 */
class DeleteSubmissionCommand extends Command
{
    protected $signature = 'journal:delete-submission
        {reference* : Manuscript reference(s), e.g. JCDMS-2026-0002}
        {--dry-run : Show what would be deleted, change nothing}
        {--force : Do not ask for confirmation}';

    protected $description = 'Permanently delete test submissions by reference';

    public function handle(): int
    {
        $references = array_unique($this->argument('reference'));
        $submissions = Submission::query()
            ->whereIn('reference', $references)
            ->with(['journal', 'authors'])
            ->withCount(['files', 'reviewRounds', 'decisions'])
            ->orderBy('id')
            ->get();

        $missing = array_diff($references, $submissions->pluck('reference')->all());
        if ($missing !== []) {
            $this->error('No submission with reference: '.implode(', ', $missing).'. Nothing deleted.');

            return self::FAILURE;
        }

        $this->table(
            ['Reference', 'Title', 'Status', 'Corresponding author', 'Submitted', 'Files', 'Rounds', 'Decisions'],
            $submissions->map(fn (Submission $s) => [
                $s->reference,
                str($s->title)->limit(40),
                $s->status->label(),
                $s->authors->firstWhere('is_corresponding', true)?->email ?? '—',
                $s->submitted_at?->toDateString() ?? '—',
                $s->files_count,
                $s->review_rounds_count,
                $s->decisions_count,
            ])->all(),
        );

        $converted = $submissions->filter(fn (Submission $s) => $s->article_id !== null);
        if ($converted->isNotEmpty()) {
            $this->error('Refusing: '.$converted->pluck('reference')->implode(', ')
                .' was accepted and became an article. Remove or withdraw that article in the admin first. Nothing deleted.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN — nothing was deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Permanently delete '.($submissions->count() === 1 ? 'this submission' : 'these submissions').' and everything attached?', false)) {
            $this->line('Cancelled. Nothing was deleted.');

            return self::FAILURE;
        }

        $paths = DB::table('submission_files')->whereIn('submission_id', $submissions->pluck('id'))->pluck('path')->all();

        DB::transaction(function () use ($submissions): void {
            foreach ($submissions as $submission) {
                $submission->delete();   // authors, files, events, rounds, reviews, decisions, discussions cascade
            }
        });

        // After the commit: a rolled-back delete must not leave rows pointing at destroyed files.
        foreach ($paths as $path) {
            Storage::disk('private')->delete($path);
        }

        Log::notice('Test submissions deleted from the console', [
            'references' => $submissions->pluck('reference')->all(),
            'files' => count($paths),
        ]);

        $this->info(sprintf('Deleted %d submission(s) and %d file(s).', $submissions->count(), count($paths)));

        return self::SUCCESS;
    }
}
