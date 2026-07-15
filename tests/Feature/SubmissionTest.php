<?php

declare(strict_types=1);

use App\Enums\SubmissionStage;
use App\Enums\SubmissionStatus;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Storage::fake('private');

    $this->journal = Journal::factory()->create([
        'slug' => 'jcdms',
        'title' => 'Journal of Contemporary Development & Management Studies',
        'abbreviation' => 'JCD&MS',
    ]);

    JournalSection::factory()->create([
        'journal_id' => $this->journal->id,
        'name' => 'Research Article',
    ]);

    $this->author = grantRoleOn(User::factory()->create(), $this->journal, 'author');
});

/* -------------------------------------------------------------------------- */

describe('drafts', function () {
    it('persists a draft across a browser close and resumes at the right step', function () {
        // Step 3 of the wizard, typed and saved. Then the browser dies.
        $this->actingAs($this->author)->post('/submit/draft', [
            'journal' => 'jcdms',
            'title' => 'Half-written manuscript',
            'type' => 'Research Article',
            'abstract' => 'Two paragraphs in and the laptop battery died.',
            'keywords' => 'drafts, resilience',
            'fileName' => 'manuscript-v3.pdf',
            'authors' => [
                ['name' => 'Ada Byron King', 'email' => 'ada@example.edu', 'affiliation' => 'LCC', 'corresponding' => true],
            ],
            'funding' => 'Wellcome Trust 12345',
            'ethics' => true,
            'conflicts' => false,
            'dataAvailable' => false,
            'step' => 3,
        ])->assertRedirect();

        // The "browser close": the session is gone. Nothing survives but the database — no
        // localStorage, no cookie, no component state. If the draft lives anywhere but a
        // row, this is where it evaporates.
        $this->flushSession();

        $props = pageProps($this->actingAs($this->author)->get('/submit'));

        expect($props['draft'])->not->toBeNull()
            ->and($props['draft']['step'])->toBe(3)
            ->and($props['draft']['journal'])->toBe('jcdms')          // the SLUG, not the title
            ->and($props['draft']['title'])->toBe('Half-written manuscript')
            ->and($props['draft']['type'])->toBe('Research Article')
            ->and($props['draft']['keywords'])->toBe('drafts, resilience')
            ->and($props['draft']['funding'])->toBe('Wellcome Trust 12345')
            ->and($props['draft']['ethics'])->toBeTrue()
            ->and($props['draft']['conflicts'])->toBeFalse()
            ->and($props['draft']['authors'][0]['email'])->toBe('ada@example.edu')
            // The NAME of the file, never a pretend file: a File does not survive a JSON
            // round-trip, and the wizard makes the author re-attach it.
            ->and($props['draft']['fileName'])->toBe('manuscript-v3.pdf');
    });

    it('keeps a draft invisible to editors and gives it no reference', function () {
        $editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');

        $this->actingAs($this->author)->post('/submit/draft', [
            'journal' => 'jcdms',
            'title' => 'Not yet sent to anyone',
            'step' => 1,
        ]);

        $draft = Submission::sole();

        expect($draft->status)->toBe(SubmissionStatus::Draft)
            // No reference: a reference is minted at SUBMISSION, so an abandoned draft does
            // not burn a number out of the middle of the journal's sequence.
            ->and($draft->reference)->toBeNull();

        $props = pageProps($this->actingAs($editor)->get('/dashboard'));

        expect($props['submissions'])->toBeEmpty();
    });

    it('updates the author\'s existing draft rather than piling up new ones', function () {
        foreach ([0, 1, 2, 3] as $step) {
            $this->actingAs($this->author)->post('/submit/draft', [
                'journal' => 'jcdms',
                'title' => "Revision at step {$step}",
                'step' => $step,
            ]);
        }

        expect(Submission::count())->toBe(1)
            ->and(Submission::sole()->draft_step)->toBe(3);
    });
});

/* -------------------------------------------------------------------------- */

describe('submitting', function () {
    it('puts a submitted manuscript in the editor\'s queue with a reference', function () {
        $editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');

        $response = $this->actingAs($this->author)->post('/submit', [
            'journal' => 'jcdms',
            'title' => 'Coral refugia under repeated thermal stress',
            'type' => 'Research Article',
            'abstract' => 'A study of thermal refugia across three reef systems, and what they imply.',
            'keywords' => 'coral, refugia',
            'file' => UploadedFile::fake()->create('manuscript.pdf', 120, 'application/pdf'),
            'authors' => [
                ['name' => 'Ada Byron King', 'email' => 'ada@example.edu', 'affiliation' => 'LCC', 'corresponding' => true],
            ],
            'ethics' => true,
            'conflicts' => true,
            'dataAvailable' => false,
        ]);

        $response->assertRedirect();

        $submission = Submission::sole();

        expect($submission->status)->toBe(SubmissionStatus::Submitted)
            ->and($submission->stage)->toBe(SubmissionStage::Submitted)
            ->and($submission->submitted_at)->not->toBeNull()
            // The declarations are a compliance record, not a checkbox — WHEN they were
            // affirmed is the part an integrity investigation asks for.
            ->and($submission->declarations_at)->not->toBeNull()
            ->and($submission->ethics_declared)->toBeTrue()
            ->and($submission->reference)->toBe('JCDMS-'.now()->year.'-0001');

        // The manuscript is on the PRIVATE disk, versioned.
        $file = $submission->files()->sole();
        expect($file->version)->toBe(1)
            ->and($file->original_name)->toBe('manuscript.pdf');
        Storage::disk('private')->assertExists($file->path);

        // And it is now in the editor's queue.
        $props = pageProps($this->actingAs($editor)->get('/dashboard'));

        expect($props['submissions'])->toHaveCount(1)
            ->and($props['submissions'][0]['id'])->toBe($submission->reference)
            ->and($props['submissions'][0]['status'])->toBe('Submitted');
    });

    it('lets a GUEST submit without an account, and identifies them by email', function () {
        // Submission is public: an open-access journal wants papers, and forcing an account
        // first is a barrier for no gain. No actingAs — this is a signed-out visitor.
        $this->get('/submit')->assertOk();   // the wizard, not a redirect to login

        $response = $this->post('/submit', [
            'journal' => 'jcdms',
            'title' => 'A manuscript from someone with no account',
            'type' => 'Research Article',
            'abstract' => 'A guest submission that must reach the editorial office without a login.',
            'keywords' => 'open, access',
            'file' => UploadedFile::fake()->create('manuscript.pdf', 80, 'application/pdf'),
            'authors' => [
                ['name' => 'Grace Hopper', 'email' => 'grace@example.edu', 'affiliation' => 'LCC', 'corresponding' => true],
            ],
            'ethics' => true,
            'conflicts' => true,
        ]);

        $response->assertRedirect();   // NOT a 302 to /login

        $submission = Submission::sole();

        expect($submission->status)->toBe(SubmissionStatus::Submitted)
            // No user account — the corresponding author is the submission_author, by email.
            ->and($submission->corresponding_author_id)->toBeNull()
            ->and($submission->reference)->toBe('JCDMS-'.now()->year.'-0001');

        $author = $submission->authors()->where('is_corresponding', true)->sole();
        expect($author->email)->toBe('grace@example.edu')
            ->and($author->name)->toBe('Grace Hopper');
    });

    it('flashes a receipt the success screen can print, and invents nothing', function () {
        $this->actingAs($this->author)->post('/submit', [
            'journal' => 'jcdms',
            'title' => 'Coral refugia',
            'abstract' => 'A study of thermal refugia across three reef systems, and what they imply.',
            'file' => UploadedFile::fake()->create('manuscript.pdf', 10, 'application/pdf'),
            'authors' => [['name' => 'Ada King', 'email' => 'ada@example.edu']],
            'ethics' => true,
            'conflicts' => true,
        ])->assertSessionHas('submission', fn (array $receipt): bool => $receipt['reference'] === Submission::sole()->reference
            && $receipt['journal'] === $this->journal->title
            && $receipt['title'] === 'Coral refugia'
            // NULL, not a made-up "51 days": this journal has never decided anything.
            && $receipt['medianDaysToDecision'] === null
        );
    });

    it('turns the author\'s draft into the submission instead of orphaning it', function () {
        $this->actingAs($this->author)->post('/submit/draft', [
            'journal' => 'jcdms',
            'title' => 'Coral refugia',
            'step' => 4,
        ]);

        submitManuscript($this->author, $this->journal);

        expect(Submission::count())->toBe(1)
            ->and(Submission::sole()->status)->toBe(SubmissionStatus::Submitted)
            ->and(Submission::sole()->draft_step)->toBeNull();
    });

    it('rejects a submission with no declarations, using the keys the wizard binds to', function () {
        $this->actingAs($this->author)
            ->post('/submit', [
                'journal' => 'jcdms',
                'title' => 'Coral refugia',
                'abstract' => 'A study of thermal refugia across three reef systems, and what they imply.',
                'file' => UploadedFile::fake()->create('manuscript.pdf', 10, 'application/pdf'),
                'authors' => [['name' => '', 'email' => 'not-an-email']],
                'ethics' => false,
                'conflicts' => false,
            ])
            // These exact keys are the contract: Submit.tsx maps them back to the step that
            // owns them and jumps the author there. Rename one and the rejection is silent.
            ->assertSessionHasErrors(['ethics', 'conflicts', 'authors.0.name', 'authors.0.email']);

        expect(Submission::count())->toBe(0);
    });
});

/* -------------------------------------------------------------------------- */

describe('the reference', function () {
    it('is unique, gapless and per journal, per year', function () {
        $other = Journal::factory()->create(['slug' => 'jnr', 'abbreviation' => 'JNR']);

        $references = collect(range(1, 12))
            ->map(fn (): string => submitManuscript(User::factory()->create(), $this->journal)->reference);

        expect($references->unique())->toHaveCount(12)
            ->and($references->first())->toBe('JCDMS-'.now()->year.'-0001')
            ->and($references->last())->toBe('JCDMS-'.now()->year.'-0012');

        // A different journal starts its own sequence at 1 — the number an editor reasons
        // about is "the Nth manuscript THIS journal received this year".
        expect(submitManuscript(User::factory()->create(), $other)->reference)
            ->toBe('JNR-'.now()->year.'-0001');
    });

    it('strips punctuation out of the abbreviation', function () {
        // "JCD&MS" is the citation abbreviation. A reference gets typed into search boxes
        // and pasted into emails, so it is A-Z0-9 only.
        expect(submitManuscript($this->author, $this->journal)->reference)
            ->toStartWith('JCDMS-');
    });

    it('is protected by a UNIQUE INDEX, not merely by careful code', function () {
        // The lockForUpdate in SubmitManuscriptAction is what makes two simultaneous
        // submissions serialise. THIS is the backstop underneath it: if the lock ever fails
        // us, the database still refuses the duplicate — and the action retries rather than
        // handing two manuscripts the same number. Two papers sharing a reference means an
        // editor opens the wrong one and an author is told about someone else's decision.
        $indexes = collect(Schema::getIndexes('submissions'));

        expect($indexes->contains(fn (array $index): bool => $index['columns'] === ['reference'] && $index['unique']))
            ->toBeTrue();

        $first = submitManuscript($this->author, $this->journal);

        expect(fn () => Submission::factory()->create([
            'reference' => $first->reference,
            'journal_id' => $this->journal->id,
        ]))->toThrow(QueryException::class);
    });
});

/* -------------------------------------------------------------------------- */

it('writes an audit event for the submission itself', function () {
    $submission = submitManuscript($this->author, $this->journal);

    $event = $submission->events()->where('event', 'submission.submitted')->sole();

    expect($event->user_id)->toBe($this->author->id)
        ->and($event->payload['reference'])->toBe($submission->reference)
        ->and($event->created_at)->not->toBeNull();
});
