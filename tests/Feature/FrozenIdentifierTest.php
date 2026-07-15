<?php

declare(strict_types=1);

use App\Enums\ArticleStatus;
use App\Exceptions\FrozenIdentifierException;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * These tests exist because a dead DOI has no undo. Each one encodes a way the system
 * could silently break a permanent identifier.
 */
it('does NOT regenerate the slug when a published article title is edited', function () {
    // THE test. Most slug packages regenerate a slug from the title on every save. If
    // that happened here, the article's public URL would move, and every DOI, citation
    // and index entry pointing at the old URL would 404 — while the article still loads
    // at its new address, so nobody would notice.
    $article = Article::factory()->published()->create([
        'title' => 'Thermal refugia buffer coral reef collapse',
        'slug' => 'thermal-refugia-buffer-coral-reef-collapse',
    ]);

    $article->update(['title' => 'Thermal refugia buffer coral-reef collapse (corrected)']);

    expect($article->fresh()->slug)->toBe('thermal-refugia-buffer-coral-reef-collapse')
        ->and($article->fresh()->title)->toBe('Thermal refugia buffer coral-reef collapse (corrected)');
});

it('refuses to change the slug of a published article', function () {
    $article = Article::factory()->published()->create(['slug' => 'frozen-slug']);

    expect(fn () => $article->update(['slug' => 'a-better-slug']))
        ->toThrow(FrozenIdentifierException::class);

    expect($article->fresh()->slug)->toBe('frozen-slug');
});

it('refuses to change the doi_suffix of a published article', function () {
    $article = Article::factory()->published()->create(['doi_suffix' => 'jcdms.v10i2.001']);

    expect(fn () => $article->update(['doi_suffix' => 'jcdms.v10i2.999']))
        ->toThrow(FrozenIdentifierException::class);

    expect($article->fresh()->doi_suffix)->toBe('jcdms.v10i2.001');
});

it('refuses to change the sequence of a published article', function () {
    // Sequence drives the DOI suffix. Reordering a published issue would silently
    // re-point identifiers at different articles.
    $article = Article::factory()->published()->create(['sequence' => 3]);

    expect(fn () => $article->update(['sequence' => 4]))
        ->toThrow(FrozenIdentifierException::class);
});

it('refuses to delete a published article', function () {
    $article = Article::factory()->published()->create();

    expect(fn () => $article->delete())->toThrow(FrozenIdentifierException::class);
    expect(Article::find($article->id))->not->toBeNull();
});

it('allows all of those changes while the article is still a draft', function () {
    $article = Article::factory()->create([
        'status' => ArticleStatus::Draft,
        'slug' => 'draft-slug',
        'sequence' => 1,
    ]);

    $article->update(['slug' => 'revised-slug', 'sequence' => 2, 'doi_suffix' => 'x.001']);

    expect($article->fresh()->slug)->toBe('revised-slug')
        ->and($article->fresh()->sequence)->toBe(2);
});

it('allows the publish action itself to set the identifiers in one write', function () {
    // The publish transaction stamps status + identifiers together. The observer must
    // let that through, or nothing could ever be published at all.
    $article = Article::factory()->create(['status' => ArticleStatus::Draft, 'doi_suffix' => null]);

    $article->update([
        'status' => ArticleStatus::Published,
        'published_at' => now(),
        'doi_suffix' => 'jcdms.v10i2.004',
    ]);

    expect($article->fresh()->doi_suffix)->toBe('jcdms.v10i2.004')
        ->and($article->fresh()->status)->toBe(ArticleStatus::Published);
});

it('allows a NULL identifier to be filled in after publication', function () {
    // An article published before Crossref issued the prefix can still be given its
    // suffix later. Completing a NULL is not the same as mutating a live value.
    $article = Article::factory()->published()->create(['doi_suffix' => null]);

    $article->update(['doi_suffix' => 'jcdms.v10i2.005']);

    expect($article->fresh()->doi_suffix)->toBe('jcdms.v10i2.005');
});

it('keeps identifiers frozen after withdrawal', function () {
    // A withdrawn article must keep resolving — with a withdrawal notice — or its DOI
    // dies. Withdrawal is not an unlock.
    $article = Article::factory()->published()->create(['slug' => 'withdrawn-but-permanent']);
    $article->update(['status' => ArticleStatus::Withdrawn]);

    expect(fn () => $article->update(['slug' => 'now-i-can-rename-it']))
        ->toThrow(FrozenIdentifierException::class);
});
