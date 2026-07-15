<?php

declare(strict_types=1);

use App\Actions\AssignReviewerAction;
use App\Actions\SubmitReviewAction;
use App\Enums\Recommendation;
use App\Models\Journal;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| THE LEAK TESTS
|--------------------------------------------------------------------------
| These exist to TRY to leak a reviewer's identity to an author, and to fail.
|
| Under single-blind review the reviewer was promised anonymity, and an author who learns
| who reviewed them can retaliate — on a grant panel, at a conference, in a hiring
| committee. That is a research-integrity incident, not a bug, and it is not recoverable by
| a patch: once the author knows, they know.
|
| So the assertions here are deliberately crude and total. They do not check that a
| component fails to PRINT a name; they check that the name never crosses the wire at all,
| by searching the entire serialised prop payload for it. A field that is present but
| unrendered today is a field the next component renders by accident.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCDMS']);

    $this->author = grantRoleOn(User::factory()->create(), $this->journal, 'author');
    $this->editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');

    $this->reviewer = grantRoleOn(User::factory()->create([
        'name' => 'Grace Hopper',
        'given_name' => 'Grace',
        'family_name' => 'Hopper',
        'email' => 'grace.hopper@navy.example',
        'affiliation' => 'United States Navy',
        'avatar_path' => 'avatars/grace-hopper.jpg',
    ]), $this->journal, 'reviewer');

    $this->submission = submitManuscript($this->author, $this->journal);

    $assignment = app(AssignReviewerAction::class)
        ->execute($this->submission, $this->reviewer, $this->editor);

    app(SubmitReviewAction::class)->execute(
        $assignment,
        Recommendation::MajorRevision,
        'The sampling frame is under-described.',
        'CONFIDENTIAL: I suspect this overlaps with their 2024 paper.',
        $this->reviewer,
    );
});

/* -------------------------------------------------------------------------- */

describe('an author fetching their own dashboard', function () {
    it('NEVER receives a reviewer name, email, affiliation or avatar', function () {
        $json = pagePropsJson($this->actingAs($this->author)->get('/dashboard'));

        expect($json)
            ->not->toContain('Grace Hopper')
            ->and($json)->not->toContain('Grace')
            ->and($json)->not->toContain('Hopper')
            ->and($json)->not->toContain('grace.hopper@navy.example')
            ->and($json)->not->toContain('United States Navy')
            ->and($json)->not->toContain('avatars/grace-hopper.jpg')
            // The reviewer's user id is an identity too: it joins straight to a name.
            ->and($json)->not->toContain('"reviewer_id"');
    });

    it('NEVER receives comments_to_editor, or any report text', function () {
        $json = pagePropsJson($this->actingAs($this->author)->get('/dashboard'));

        expect($json)
            ->not->toContain('CONFIDENTIAL')
            ->and($json)->not->toContain('overlaps with their 2024 paper')
            ->and($json)->not->toContain('comments_to_editor')
            ->and($json)->not->toContain('commentsToEditor')
            // Even the comments meant FOR the author are not on this page — the dashboard
            // shows status and recommendation. Nothing here is a channel for report text.
            ->and($json)->not->toContain('The sampling frame is under-described.');
    });

    it('DOES receive the two facts an author is entitled to: status and recommendation', function () {
        $reviewers = pageProps($this->actingAs($this->author)->get('/dashboard'))['submissions'][0]['reviewers'];

        expect($reviewers)->toHaveCount(1)
            ->and($reviewers[0]['status'])->toBe('Report submitted')
            ->and($reviewers[0]['recommendation'])->toBe('Major revision')

            // Real values in the right shape, so the shared component has nothing to fall
            // back to and no branch that could be got wrong. Dashboard.tsx renders
            // reviewer.name/.affiliation/.avatar unconditionally — and it must not be edited.
            ->and($reviewers[0]['name'])->toBe('Reviewer 1')
            ->and($reviewers[0]['affiliation'])->toBe('Identity withheld — single-blind review')
            ->and($reviewers[0]['avatar'])->toStartWith('data:image/svg+xml');
    });

    it('numbers reviewers stably, so "Reviewer 2" means the same person on every visit', function () {
        $second = grantRoleOn(User::factory()->create(['name' => 'Katherine Johnson']), $this->journal, 'reviewer');

        app(AssignReviewerAction::class)->execute($this->submission->fresh(), $second, $this->editor);

        $names = collect(pageProps($this->actingAs($this->author)->get('/dashboard'))['submissions'][0]['reviewers'])->pluck('name');

        expect($names->all())->toBe(['Reviewer 1', 'Reviewer 2']);

        // Same page, fetched again. The labels do not shuffle.
        $again = collect(pageProps($this->actingAs($this->author)->get('/dashboard'))['submissions'][0]['reviewers'])->pluck('name');

        expect($again->all())->toBe(['Reviewer 1', 'Reviewer 2']);
    });
});

/* -------------------------------------------------------------------------- */

describe('the editor', function () {
    it('DOES see the real reviewer, because they chose them', function () {
        $props = pageProps($this->actingAs($this->editor)->get('/dashboard'));

        $reviewer = $props['submissions'][0]['reviewers'][0];

        expect($reviewer['name'])->toBe('Grace Hopper')
            ->and($reviewer['affiliation'])->toBe('United States Navy')
            ->and($reviewer['status'])->toBe('Report submitted')
            ->and($reviewer['recommendation'])->toBe('Major revision');
    });

    it('still does not get comments_to_editor pushed into the page props', function () {
        // The editor is entitled to read it — but not from the Dashboard, which is a
        // SHARED component also served to authors. Confidential text does not travel on a
        // payload whose audience depends on a boolean.
        $json = pagePropsJson($this->actingAs($this->editor)->get('/dashboard'));

        expect($json)->not->toContain('CONFIDENTIAL')
            ->and($json)->not->toContain('comments_to_editor');
    });
});

/* -------------------------------------------------------------------------- */

describe('cross-journal', function () {
    it('does not reveal reviewers to an editor of a DIFFERENT journal', function () {
        $other = Journal::factory()->create(['slug' => 'other']);
        $outsider = grantRoleOn(User::factory()->create(), $other, 'journal-editor');

        $props = pageProps($this->actingAs($outsider)->get('/dashboard'));

        // They cannot even see the manuscript: roles are per-journal, and an editor of
        // Journal A has no standing on Journal B's queue.
        expect($props['submissions'])->toBeEmpty();
        expect(pagePropsJson($this->actingAs($outsider)->get('/dashboard')))
            ->not->toContain('Grace Hopper');
    });

    it('anonymises reviewers to an editor of ANOTHER journal who is an author here', function () {
        // THE SUBTLE ONE, and the reason the reveal flag is computed per SUBMISSION rather
        // than per user. This person really is a journal-editor — of Journal B. On Journal A
        // they are an author like any other, and an author never learns who reviewed them.
        // A single "is this user an editor?" boolean anywhere in this code path leaks here.
        $otherJournal = Journal::factory()->create(['slug' => 'other', 'abbreviation' => 'OTH']);

        $person = grantRoleOn(User::factory()->create(), $otherJournal, 'journal-editor');
        $person = grantRoleOn($person, $this->journal, 'author');

        $theirs = submitManuscript($person, $this->journal);

        $reviewer = grantRoleOn(User::factory()->create(['name' => 'Katherine Johnson']), $this->journal, 'reviewer');
        app(AssignReviewerAction::class)->execute($theirs, $reviewer, $this->editor);

        $props = pageProps($this->actingAs($person)->get('/dashboard'));

        expect(json_encode($props))->not->toContain('Katherine Johnson');

        $mine = collect($props['submissions'])->firstWhere('id', $theirs->reference);

        expect($mine)->not->toBeNull()
            ->and($mine['reviewers'][0]['name'])->toBe('Reviewer 1');
    });

    it('does not leak a reviewer to a reviewer of the same manuscript', function () {
        $second = grantRoleOn(User::factory()->create(['name' => 'Katherine Johnson']), $this->journal, 'reviewer');

        app(AssignReviewerAction::class)->execute($this->submission->fresh(), $second, $this->editor);

        $json = pagePropsJson($this->actingAs($second)->get('/dashboard'));

        expect($json)->not->toContain('Grace Hopper')
            ->and($json)->not->toContain('United States Navy');
    });
});
