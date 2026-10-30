<?php

declare(strict_types=1);

use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\ArticleFile;
use App\Models\Field;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\User;
use App\Models\Volume;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * journal:prune-journals clears the demo journals off a server that should carry JCD&MS
 * only. It deletes published articles (which nothing else may), so it must be precise and
 * refuse anything that looks like real editorial work.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('private');

    $this->jcdms = Journal::factory()->create(['slug' => 'jcdms']);
    $this->real = Article::factory()->published()->create(['journal_id' => $this->jcdms->id, 'slug' => 'real-paper']);

    $field = Field::factory()->create(['name' => 'Demo field']);
    $this->demo = Journal::factory()->create(['slug' => 'business-management', 'field_id' => $field->id]);
    // Demo journals publish continuously: no volumes, no issues.
    $this->demoArticle = Article::factory()->published()->create([
        'journal_id' => $this->demo->id, 'issue_id' => null, 'slug' => 'demo-paper',
    ]);
    ArticleAuthor::factory()->create(['article_id' => $this->demoArticle->id]);
    Storage::disk('private')->put('articles/demo.pdf', '%PDF demo');
    ArticleFile::factory()->create(['article_id' => $this->demoArticle->id, 'type' => 'pdf', 'path' => 'articles/demo.pdf']);

    $this->editor = grantRoleOn(User::factory()->create(), $this->demo, 'journal-editor');
});

it('removes every other journal — published articles, files, roles, unused fields — and keeps JCD&MS', function () {
    $this->artisan('journal:prune-journals', ['--force' => true])->assertSuccessful();

    expect(Journal::pluck('slug')->all())->toBe(['jcdms'])
        ->and(Article::pluck('slug')->all())->toBe(['real-paper'])
        ->and(ArticleAuthor::where('article_id', $this->demoArticle->id)->exists())->toBeFalse()
        ->and(Field::where('name', 'Demo field')->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('journal_id', $this->demo->id)->exists())->toBeFalse()
        ->and(User::find($this->editor->id))->not->toBeNull();   // the person stays; only the role goes

    Storage::disk('private')->assertMissing('articles/demo.pdf');
});

it('changes nothing on a dry run', function () {
    $this->artisan('journal:prune-journals', ['--dry-run' => true])->assertSuccessful();

    expect(Journal::count())->toBe(2)->and(Article::count())->toBe(2);
});

it('asks first, and does nothing when the answer is no', function () {
    $this->artisan('journal:prune-journals')
        ->expectsConfirmation('Permanently remove these journals and everything in them?', 'no')
        ->assertFailed();

    expect(Journal::count())->toBe(2);
});

it('refuses a journal with issues or submissions unless explicitly allowed', function () {
    $volume = Volume::factory()->create(['journal_id' => $this->demo->id]);
    Issue::factory()->create(['volume_id' => $volume->id]);

    $this->artisan('journal:prune-journals', ['--force' => true])->assertFailed();
    expect(Journal::count())->toBe(2);

    $this->artisan('journal:prune-journals', ['--force' => true, '--allow' => ['business-management']])->assertSuccessful();
    expect(Journal::pluck('slug')->all())->toBe(['jcdms']);
});

it('refuses to run when the journal to keep does not exist', function () {
    $this->artisan('journal:prune-journals', ['--keep' => ['typo'], '--force' => true])->assertFailed();

    expect(Journal::count())->toBe(2);
});
