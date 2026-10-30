<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * journal:delete-submission removes manuscripts sent in while testing the system. Real
 * submissions are an editorial record, so it asks first and refuses one that became an article.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('private');

    $this->journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCD&MS']);
    $author = grantRoleOn(User::factory()->create(), $this->journal, 'author');

    // Through the real submission path, so it has authors, a file and an event like a real one.
    $this->test = submitManuscript($author, $this->journal, ['title' => 'test']);
    $this->keep = submitManuscript($author, $this->journal, ['title' => 'A real manuscript']);
});

it('deletes a test submission, its records and its uploaded file, and nothing else', function () {
    $path = $this->test->files()->value('path');
    Storage::disk('private')->assertExists($path);

    $this->artisan('journal:delete-submission', ['reference' => [$this->test->reference], '--force' => true])
        ->assertSuccessful();

    expect(Submission::pluck('reference')->all())->toBe([$this->keep->reference])
        ->and(DB::table('submission_authors')->where('submission_id', $this->test->id)->exists())->toBeFalse()
        ->and(DB::table('submission_events')->where('submission_id', $this->test->id)->exists())->toBeFalse();
    Storage::disk('private')->assertMissing($path);
});

it('asks first, and keeps everything when the answer is no', function () {
    $this->artisan('journal:delete-submission', ['reference' => [$this->test->reference]])
        ->expectsConfirmation('Permanently delete this submission and everything attached?', 'no')
        ->assertFailed();

    expect(Submission::count())->toBe(2);
});

it('changes nothing on a dry run, or for an unknown reference', function () {
    $this->artisan('journal:delete-submission', ['reference' => [$this->test->reference], '--dry-run' => true])->assertSuccessful();
    $this->artisan('journal:delete-submission', ['reference' => ['JCDMS-2026-9999'], '--force' => true])->assertFailed();

    expect(Submission::count())->toBe(2);
});

it('refuses a submission that became an article', function () {
    $article = Article::factory()->create(['journal_id' => $this->journal->id]);
    $this->test->forceFill(['article_id' => $article->id])->save();

    $this->artisan('journal:delete-submission', ['reference' => [$this->test->reference], '--force' => true])->assertFailed();

    expect(Submission::count())->toBe(2);
});
