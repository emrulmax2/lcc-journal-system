<?php

declare(strict_types=1);

use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Submission;
use App\Models\User;
use App\Models\Volume;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The wizard's first step is "Journal & issue": the author names the issue they are aiming
 * for — one of the journal's UNPUBLISHED issues — or "next available issue". It is a
 * preference the editors see, never a placement.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCD&MS']);
    JournalSection::factory()->create(['journal_id' => $this->journal->id, 'name' => 'Research Article']);

    $v10 = Volume::factory()->create(['journal_id' => $this->journal->id, 'number' => 10, 'year' => 2026]);
    $v11 = Volume::factory()->create(['journal_id' => $this->journal->id, 'number' => 11, 'year' => 2027]);
    $this->published = Issue::factory()->published()->create([
        'volume_id' => $v10->id, 'number' => 2, 'season' => 'Spring 2026', 'publication_date' => '2026-09-09',
    ]);
    $this->upcoming = Issue::factory()->create(['volume_id' => $v11->id, 'number' => 1, 'season' => 'Spring 2027']);

    $other = Journal::factory()->create(['slug' => 'other']);
    $this->foreign = Issue::factory()->create([
        'volume_id' => Volume::factory()->create(['journal_id' => $other->id, 'number' => 1])->id, 'number' => 1,
    ]);

    $this->author = grantRoleOn(User::factory()->create(), $this->journal, 'author');
});

function issueSubmission(array $overrides = []): array
{
    return array_merge([
        'journal' => 'jcdms',
        'title' => 'Coral refugia under repeated thermal stress',
        'type' => 'Research Article',
        'abstract' => 'A study of thermal refugia across three reef systems, and what they imply for resilience. We surveyed coral cover over five years, compared sites under repeated heat stress, and found that shaded refugia recovered faster than exposed reef flats.',
        'file' => UploadedFile::fake()->create('manuscript.pdf', 120, 'application/pdf'),
        'authors' => [['name' => 'Ada Byron King', 'email' => 'ada@example.edu', 'corresponding' => true]],
        'ethics' => true,
        'conflicts' => true,
    ], $overrides);
}

it('offers only the journal’s unpublished issues, and shows the latest published one', function () {
    $journal = collect(pageProps($this->get('/submit'))['journals'])->firstWhere('slug', 'jcdms');

    expect($journal['openIssues'])->toBe([['id' => $this->upcoming->id, 'label' => 'Volume 11, Number 1 · Spring 2027']])
        ->and($journal['latestIssue']['label'])->toBe('Volume 10, Number 2 · Spring 2026');
});

it('records the issue the author is aiming for, and puts it on the receipt', function () {
    $this->actingAs($this->author)
        ->post('/submit', issueSubmission(['issue' => $this->upcoming->id]))
        ->assertRedirect()
        ->assertSessionHas('submission.issue', 'Volume 11, Number 1 · Spring 2027');

    expect(Submission::sole()->target_issue_id)->toBe($this->upcoming->id);
});

it('treats no issue as "next available issue"', function () {
    $this->actingAs($this->author)
        ->post('/submit', issueSubmission(['issue' => '']))
        ->assertRedirect()
        ->assertSessionHas('submission', fn (array $receipt) => $receipt['issue'] === null);

    expect(Submission::sole()->target_issue_id)->toBeNull();
});

it('refuses a published or another journal’s issue on final submission', function () {
    foreach ([$this->published->id, $this->foreign->id] as $id) {
        $this->actingAs($this->author)
            ->post('/submit', issueSubmission(['issue' => $id]))
            ->assertSessionHasErrors('issue');
    }

    expect(Submission::count())->toBe(0);
});

it('saves the issue with the draft, and quietly drops one that is not open', function () {
    $draft = ['journal' => 'jcdms', 'title' => 'Draft', 'step' => 1];

    $this->actingAs($this->author)->post('/submit/draft', $draft + ['issue' => $this->upcoming->id]);
    expect(pageProps($this->actingAs($this->author)->get('/submit'))['draft']['issue'])->toBe($this->upcoming->id);

    $this->actingAs($this->author)->post('/submit/draft', $draft + ['issue' => $this->published->id]);
    expect(pageProps($this->actingAs($this->author)->get('/submit'))['draft']['issue'])->toBeNull();
});
