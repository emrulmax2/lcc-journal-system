<?php

declare(strict_types=1);

use App\Actions\AssignReviewerAction;
use App\Actions\RespondToInvitationAction;
use App\Enums\Recommendation;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The reviewer's "Submit report" contract. The dialog posts `recommendation`, and the backend
 * validates it against App\Enums\Recommendation with Rule::enum — which accepts the VALUE
 * ('major_revision'), NOT the label ('Major revision'). The dialog used to post the label, so
 * every submit failed validation silently. These pin the contract so it cannot drift back.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCDMS']);
    JournalSection::factory()->create(['journal_id' => $this->journal->id, 'name' => 'Research Article']);

    $this->editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');
    $this->reviewer = grantRoleOn(User::factory()->create(), $this->journal, 'reviewer');
    $author = grantRoleOn(User::factory()->create(), $this->journal, 'author');
    $submission = submitManuscript($author, $this->journal, ['type' => 'Research Article']);

    $this->assignment = app(AssignReviewerAction::class)->execute($submission, $this->reviewer, $this->editor);
    app(RespondToInvitationAction::class)->execute($this->assignment, true, $this->reviewer);
});

it('accepts the recommendation VALUE the dialog now sends', function () {
    $this->actingAs($this->reviewer)
        ->post("/reviews/{$this->assignment->id}/report", [
            'recommendation' => 'major_revision',
            'comments_to_author' => 'Please expand the methods section.',
        ])
        ->assertSessionHasNoErrors();

    expect($this->assignment->fresh()->review->recommendation)->toBe(Recommendation::MajorRevision);
});

it('rejects the recommendation LABEL — the exact bug that made Submit report do nothing', function () {
    $this->actingAs($this->reviewer)
        ->post("/reviews/{$this->assignment->id}/report", [
            'recommendation' => 'Major revision', // the label the old dialog posted
            'comments_to_author' => 'Please expand the methods section.',
        ])
        ->assertSessionHasErrors('recommendation');
});
