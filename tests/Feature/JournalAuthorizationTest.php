<?php

declare(strict_types=1);

use App\Exceptions\FrozenIdentifierException;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\User;
use App\Models\Volume;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;   // NB: shadows the App\ namespace in this file —
use Illuminate\Support\Facades\App;
use Spatie\Permission\PermissionRegistrar;

   // import App\ classes explicitly, above.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->journalA = Journal::factory()->create(['slug' => 'journal-a']);
    $this->journalB = Journal::factory()->create(['slug' => 'journal-b']);
});

/** Grant a role ON A SPECIFIC JOURNAL. This is the whole point of Spatie teams. */
function grantOn(User $user, Journal $journal, string $role): User
{
    $registrar = App::make(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($journal->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);

    return $user->fresh();
}

describe('cross-journal isolation', function () {
    it('lets an editor of Journal A manage Journal A', function () {
        $editor = grantOn(User::factory()->create(), $this->journalA, 'journal-editor');

        expect($editor->can('manageArticles', $this->journalA))->toBeTrue()
            ->and($editor->can('publish', $this->journalA))->toBeTrue()
            ->and($editor->can('manageSettings', $this->journalA))->toBeTrue();
    });

    it('does NOT let an editor of Journal A touch Journal B', function () {
        // The test that matters. If this ever goes green by accident, one journal's
        // editors can publish and deposit DOIs on another journal's behalf.
        $editor = grantOn(User::factory()->create(), $this->journalA, 'journal-editor');

        expect($editor->can('manageArticles', $this->journalB))->toBeFalse()
            ->and($editor->can('publish', $this->journalB))->toBeFalse()
            ->and($editor->can('manageSettings', $this->journalB))->toBeFalse()
            ->and($editor->can('depositDois', $this->journalB))->toBeFalse();
    });

    it('lets one person edit Journal A and only review on Journal B', function () {
        // The exact scenario a global role cannot express, and the reason teams exist.
        $user = User::factory()->create();
        $user = grantOn($user, $this->journalA, 'journal-editor');
        $user = grantOn($user, $this->journalB, 'reviewer');

        expect($user->can('publish', $this->journalA))->toBeTrue()
            ->and($user->can('publish', $this->journalB))->toBeFalse()
            ->and($user->can('manageArticles', $this->journalB))->toBeFalse();
    });

    it('does not leak the team context between two checks in one request', function () {
        // JournalPolicy restores the previous team id after every check. Without that,
        // checking Journal A then Journal B would answer the second question using the
        // first journal's context — a cross-tenant authorisation bug that only appears
        // when a single request touches two journals.
        $editor = grantOn(User::factory()->create(), $this->journalA, 'journal-editor');

        expect($editor->can('publish', $this->journalA))->toBeTrue();
        expect($editor->can('publish', $this->journalB))->toBeFalse();
        expect($editor->can('publish', $this->journalA))->toBeTrue();
    });
});

describe('the publish gate', function () {
    it('lets production manage articles but NOT publish them', function () {
        // Production can prepare everything and make nothing permanent. Publishing spends
        // money at Crossref and freezes URLs forever; that is an editorial decision.
        $production = grantOn(User::factory()->create(), $this->journalA, 'production');

        expect($production->can('manageArticles', $this->journalA))->toBeTrue()
            ->and($production->can('manageIssues', $this->journalA))->toBeTrue()
            ->and($production->can('publish', $this->journalA))->toBeFalse()
            ->and($production->can('depositDois', $this->journalA))->toBeFalse();
    });

    it('does not let a reviewer manage or publish anything', function () {
        $reviewer = grantOn(User::factory()->create(), $this->journalA, 'reviewer');

        expect($reviewer->can('manageArticles', $this->journalA))->toBeFalse()
            ->and($reviewer->can('publish', $this->journalA))->toBeFalse();
    });

    it('does not let an author manage or publish anything', function () {
        $author = grantOn(User::factory()->create(), $this->journalA, 'author');

        expect($author->can('manageArticles', $this->journalA))->toBeFalse()
            ->and($author->can('publish', $this->journalA))->toBeFalse();
    });
});

describe('published issues are immutable', function () {
    it('refuses to edit, delete or reorder a published issue — even for an editor', function () {
        $editor = grantOn(User::factory()->create(), $this->journalA, 'journal-editor');

        $volume = Volume::factory()->create(['journal_id' => $this->journalA->id]);
        $issue = Issue::factory()->published()->create(['volume_id' => $volume->id]);

        // Adding an article shifts pagination. Removing one orphans a DOI. Reordering
        // changes which article a sequence-derived suffix points at. All three silently
        // invalidate citations already made.
        expect($editor->can('update', $issue))->toBeFalse()
            ->and($editor->can('delete', $issue))->toBeFalse()
            ->and($editor->can('manageArticles', $issue))->toBeFalse();
    });

    it('allows all of that on a DRAFT issue', function () {
        $editor = grantOn(User::factory()->create(), $this->journalA, 'journal-editor');

        $volume = Volume::factory()->create(['journal_id' => $this->journalA->id]);
        $issue = Issue::factory()->create(['volume_id' => $volume->id]);

        expect($editor->can('update', $issue))->toBeTrue()
            ->and($editor->can('manageArticles', $issue))->toBeTrue();
    });
});

describe('site admin', function () {
    it('bypasses every gate on every journal', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);

        expect($admin->can('publish', $this->journalA))->toBeTrue()
            ->and($admin->can('publish', $this->journalB))->toBeTrue()
            ->and($admin->can('manageSettings', $this->journalB))->toBeTrue();
    });

    it('still cannot delete a published article — permanence outranks privilege', function () {
        $admin = User::factory()->create(['is_site_admin' => true]);
        $article = Article::factory()->published()->create(['journal_id' => $this->journalA->id]);

        // Gate::before would normally short-circuit to TRUE here. It does — which is
        // exactly why the model-layer ArticleObserver, not the policy, is the real
        // guarantee. A published article can only be withdrawn, never deleted.
        expect(fn () => $article->delete())
            ->toThrow(FrozenIdentifierException::class);

        expect(Article::find($article->id))->not->toBeNull();
    });
});
