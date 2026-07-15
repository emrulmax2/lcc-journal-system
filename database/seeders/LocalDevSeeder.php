<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ArticleFileType;
use App\Enums\Recommendation;
use App\Enums\ReviewerStatus;
use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Models\Article;
use App\Models\Journal;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\ReviewRound;
use App\Models\Submission;
use App\Models\SubmissionAuthor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * LOCAL DEVELOPMENT ONLY. Never runs in production — DatabaseSeeder guards it.
 *
 * Exists so the app is actually usable the moment it is seeded: login accounts for every
 * role, a manuscript sitting in peer review (so /dashboard has something to render), and
 * placeholder PDFs (so the publish gate can be exercised without waiting for real files).
 *
 * The PDFs are clearly marked as placeholders in their own content. They are a stand-in
 * for the real typeset article, and the publish gate refuses to publish without one —
 * which is correct, and is exactly what this seeder exists to let you see working.
 */
class LocalDevSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $jcdms = Journal::where('slug', 'jcdms')->first();

        if ($jcdms === null) {
            $this->command->warn('JcdmsSeeder has not run — skipping local dev data.');

            return;
        }

        $admin = $this->siteAdmin();
        $author = $this->author($jcdms);
        $reviewers = $this->reviewers($jcdms);

        $this->attachPlaceholderPdfs($jcdms);
        $this->manuscriptInPeerReview($jcdms, $author, $reviewers);

        $this->command->newLine();
        $this->command->info('Local login accounts — all use the password: '.self::PASSWORD);
        $this->command->table(
            ['Email', 'Role'],
            [
                [$admin->email, 'site admin (bypasses every gate)'],
                ['t.anderson-jaquest@lcc.ac.uk', 'journal-editor on JCD&MS — can publish & deposit'],
                ['t.wyrley-birch@lcc.ac.uk', 'production on JCD&MS — can edit, CANNOT publish'],
                [$author->email, 'author on JCD&MS — has a manuscript in review'],
                [$reviewers[0]->email, 'reviewer on JCD&MS — has submitted a report'],
                [$reviewers[1]->email, 'reviewer on JCD&MS — invitation still open'],
            ],
        );
    }

    private function siteAdmin(): User
    {
        // is_site_admin is a COLUMN, not a Spatie role: Spatie's teams feature puts the
        // journal id in the primary key of model_has_roles, so every role assignment must
        // name a journal. "Site admin" is not a relationship to a journal.
        return User::updateOrCreate(
            ['email' => 'admin@lcc.ac.uk'],
            [
                'name' => 'Site Administrator',
                'given_name' => 'Site',
                'family_name' => 'Administrator',
                'password' => Hash::make(self::PASSWORD),
                'is_active' => true,
                'is_site_admin' => true,
            ],
        );
    }

    private function author(Journal $journal): User
    {
        $author = User::updateOrCreate(
            ['email' => 'author@lcc.ac.uk'],
            [
                'name' => 'Alina Okonkwo',
                'given_name' => 'Alina',
                'family_name' => 'Okonkwo',
                'affiliation' => 'London Churchill College, UK',
                'password' => Hash::make(self::PASSWORD),
                'is_active' => true,
            ],
        );

        return $this->grant($author, $journal, ['author']);
    }

    /** @return array<int, User> */
    private function reviewers(Journal $journal): array
    {
        $people = [
            ['reviewer@lcc.ac.uk', 'Sofia', 'Ramírez', 'Institute of Marine Ecology'],
            ['reviewer2@lcc.ac.uk', 'Daniel', 'Osei', 'Coastal Systems Lab'],
        ];

        return array_map(function (array $p) use ($journal): User {
            [$email, $given, $family, $affiliation] = $p;

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => trim("{$given} {$family}"),
                    'given_name' => $given,
                    'family_name' => $family,
                    'affiliation' => $affiliation,
                    'password' => Hash::make(self::PASSWORD),
                    'is_active' => true,
                ],
            );

            return $this->grant($user, $journal, ['reviewer']);
        }, $people);
    }

    /**
     * A manuscript mid-peer-review, so the editorial dashboard renders something real.
     *
     * Log in as the AUTHOR and you see "Reviewer 1 / Identity withheld". Log in as the
     * EDITOR and you see Sofia Ramírez by name. That difference is the anonymity
     * guarantee, and it is worth looking at with your own eyes.
     */
    private function manuscriptInPeerReview(Journal $journal, User $author, array $reviewers): void
    {
        $submission = Submission::updateOrCreate(
            ['reference' => 'JCDMS-2026-0001'],
            [
                'journal_id' => $journal->id,
                'journal_section_id' => $journal->sections()->where('name', 'Research Article')->value('id'),
                'corresponding_author_id' => $author->id,
                'title' => 'Thermal refugia buffer coral reef collapse under repeated bleaching events',
                'abstract' => 'Repeated marine heatwaves are reshaping reef assemblages faster than '
                    .'recovery windows allow. Across 214 monitored sites we identify hydrodynamic '
                    .'thermal refugia that reduce bleaching severity by 38%.',
                'keywords' => ['coral bleaching', 'marine heatwave', 'refugia'],
                'status' => SubmissionStatus::UnderReview,
                'stage' => SubmissionStage::PeerReview,
                'ethics_declared' => true,
                'conflicts_declared' => true,
                'data_available' => true,
                'declarations_at' => now()->subDays(30),
                'submitted_at' => now()->subDays(30),
            ],
        );

        SubmissionAuthor::updateOrCreate(
            ['submission_id' => $submission->id, 'email' => $author->email],
            [
                'name' => $author->fullName(),
                'affiliation' => $author->affiliation,
                'is_corresponding' => true,
                'sequence' => 1,
            ],
        );

        $round = ReviewRound::updateOrCreate(
            ['submission_id' => $submission->id, 'round_number' => 1],
            ['opened_at' => now()->subDays(25)],
        );

        // Reviewer 1 has reported back.
        $reported = ReviewAssignment::updateOrCreate(
            ['review_round_id' => $round->id, 'reviewer_id' => $reviewers[0]->id],
            [
                'status' => ReviewerStatus::ReportSubmitted,
                'invited_at' => now()->subDays(25),
                'due_at' => now()->subDays(4),
                'responded_at' => now()->subDays(24),
                'completed_at' => now()->subDays(2),
            ],
        );

        Review::updateOrCreate(
            ['review_assignment_id' => $reported->id],
            [
                'recommendation' => Recommendation::MinorRevision,
                'comments_to_author' => 'A clear and well-argued paper. Please clarify how the '
                    .'214 sites were selected, and add confidence intervals to Figure 3.',
                // The author must NEVER see this, through any endpoint. SubmissionPresenter
                // is the only thing allowed to read it, and only for an editor.
                'comments_to_editor' => 'Sound methodology. I would accept after minor revision. '
                    .'Note the corresponding author is a former colleague — I have no conflict, '
                    .'but flagging it.',
                'submitted_at' => now()->subDays(2),
            ],
        );

        // Reviewer 2 is invited and overdue, so the "overdue" banner has something to show.
        ReviewAssignment::updateOrCreate(
            ['review_round_id' => $round->id, 'reviewer_id' => $reviewers[1]->id],
            [
                'status' => ReviewerStatus::Invited,
                'invited_at' => now()->subDays(20),
                'due_at' => now()->subDays(3),
            ],
        );

        $submission->recordEvent('seeded', ['note' => 'local development fixture']);
    }

    /**
     * The JCD&MS articles are real, but we have no typeset PDFs for them.
     *
     * The publish gate REFUSES to publish an article without a PDF — because
     * citation_pdf_url is advertised to Google Scholar and an advertised PDF that 404s
     * downgrades the whole journal. That refusal is correct, and locally it means you
     * could never see a publish or a Crossref deposit actually run.
     *
     * So: a placeholder PDF, whose own content says it is a placeholder. Replace them with
     * the real typeset articles before anything is published for real.
     */
    private function attachPlaceholderPdfs(Journal $journal): void
    {
        foreach ($journal->articles()->get() as $article) {
            $path = "articles/{$article->doi_suffix}.pdf";

            Storage::disk('private')->put($path, $this->placeholderPdf($article));

            $article->files()->updateOrCreate(
                ['type' => ArticleFileType::Pdf],
                [
                    'path' => $path,
                    'label' => 'Full text (PDF)',
                    'original_name' => basename($path),
                    'mime_type' => 'application/pdf',
                    'size_bytes' => Storage::disk('private')->size($path),
                ],
            );
        }
    }

    /** A minimal, valid, one-page PDF. Hand-built so this needs no PDF library. */
    private function placeholderPdf(Article $article): string
    {
        $text = 'PLACEHOLDER - not the real article. Replace before publishing.';
        $title = str_replace(['(', ')', '\\'], '', mb_substr($article->title, 0, 60));

        $content = "BT /F1 12 Tf 40 750 Td ({$text}) Tj 0 -24 Td ({$title}) Tj ET";
        $len = strlen($content);

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                .'/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
            "5 0 obj << /Length {$len} >> stream\n{$content}\nendstream endobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /** @param  list<string>  $roles */
    private function grant(User $user, Journal $journal, array $roles): User
    {
        // Roles are granted IN THE CONTEXT OF A JOURNAL. Without setting the team id
        // first, Spatie attaches the role globally and cross-journal isolation breaks.
        $registrar = App::make(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($journal->id);
        $user->syncRoles($roles);
        $registrar->setPermissionsTeamId(null);

        return $user->fresh();
    }
}
