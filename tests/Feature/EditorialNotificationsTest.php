<?php

declare(strict_types=1);

use App\Actions\AssignReviewerAction;
use App\Actions\RespondToInvitationAction;
use App\Actions\SubmitReviewAction;
use App\Enums\Recommendation;
use App\Mail\DecisionLetterMail;
use App\Mail\SubmissionReceivedMail;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\User;
use App\Notifications\ReviewDeclinedNotification;
use App\Notifications\ReviewInvitationNotification;
use App\Notifications\ReviewReminderNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Phase 2 — the editorial workflow finally speaks. Before this, the only mail the platform
 * sent was the newsletter confirmation; every "the editor has been notified" was a promise
 * with nothing behind it. These tests pin the four moments that now send, and the reminder
 * that chases a late reviewer.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCDMS']);
    JournalSection::factory()->create(['journal_id' => $this->journal->id, 'name' => 'Research Article']);

    $this->author = grantRoleOn(User::factory()->create(), $this->journal, 'author');
    $this->editor = grantRoleOn(User::factory()->create(), $this->journal, 'journal-editor');
    $this->reviewer = grantRoleOn(User::factory()->create(['name' => 'Grace Hopper']), $this->journal, 'reviewer');

    $this->submission = submitManuscript($this->author, $this->journal, ['type' => 'Research Article']);
});

/* -------------------------------------------------------------------------- */

it('emails a receipt to the author when a manuscript is submitted', function () {
    Mail::fake();

    $this->post('/submit', [
        'journal' => $this->journal->slug,
        'title' => 'Thermal refugia revisited',
        'abstract' => 'A sufficiently long abstract to clear the forty-character minimum the wizard enforces.',
        'authors' => [
            ['name' => 'Jane Doe', 'email' => 'jane@example.edu', 'corresponding' => true],
        ],
        'ethics' => true,
        'conflicts' => true,
        'file' => UploadedFile::fake()->create('ms.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    Mail::assertQueued(SubmissionReceivedMail::class, fn (SubmissionReceivedMail $mail): bool => $mail->hasTo('jane@example.edu'));
});

it('notifies the reviewer when they are invited', function () {
    Notification::fake();

    $this->actingAs($this->editor)
        ->post("/submissions/{$this->submission->id}/reviewers", ['reviewer_id' => $this->reviewer->id])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->reviewer, ReviewInvitationNotification::class);
});

it('notifies the editors when a reviewer declines — making the flash message true', function () {
    $assignment = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewer, $this->editor);

    Notification::fake();

    $this->actingAs($this->reviewer)
        ->post("/reviews/{$assignment->id}/decline")
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($this->editor, ReviewDeclinedNotification::class);
});

it('mails the decision letter to the author, verbatim', function () {
    $assignment = app(AssignReviewerAction::class)->execute($this->submission, $this->reviewer, $this->editor);
    app(RespondToInvitationAction::class)->execute($assignment, true, $this->reviewer);
    app(SubmitReviewAction::class)->execute($assignment, Recommendation::Accept, 'Solid.', null, $this->reviewer);

    Mail::fake();

    $this->actingAs($this->editor)
        ->post("/submissions/{$this->submission->id}/decision", [
            'decision' => 'accept',
            'body' => 'We are delighted to accept your manuscript.',
        ])
        ->assertSessionHasNoErrors();

    // The corresponding SubmissionAuthor's email (ada@example.edu, from the submitManuscript helper).
    Mail::assertQueued(DecisionLetterMail::class, fn (DecisionLetterMail $mail): bool => $mail->hasTo('ada@example.edu')
        && str_contains($mail->letter, 'delighted to accept'));
});

describe('the reminder command', function () {
    it('nudges a reviewer whose report is overdue, once per cadence', function () {
        // Assigned five days ago, due four days ago — overdue, never reminded.
        $assignment = app(AssignReviewerAction::class)
            ->execute($this->submission, $this->reviewer, $this->editor, now()->subDays(4));

        Notification::fake();

        $this->artisan('reviews:remind')->assertOk();

        Notification::assertSentTo($this->reviewer, ReviewReminderNotification::class);
        expect($assignment->fresh()->last_reminded_at)->not->toBeNull();

        // Run again immediately: the cadence guard means no second nudge.
        $this->artisan('reviews:remind')->assertOk();
        Notification::assertSentToTimes($this->reviewer, ReviewReminderNotification::class, 1);
    });

    it('leaves a reviewer alone when nothing is due soon', function () {
        // Due in three weeks — well outside the "due soon" window.
        app(AssignReviewerAction::class)
            ->execute($this->submission, $this->reviewer, $this->editor, now()->addWeeks(3));

        Notification::fake();

        $this->artisan('reviews:remind')->assertOk();

        Notification::assertNothingSent();
    });
});
