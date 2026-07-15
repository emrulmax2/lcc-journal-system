<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ===========================================================================
 *  THE SEAM BETWEEN THE TWO HALVES OF THE SYSTEM.
 * ===========================================================================
 *
 * Everything to the left of this class is editorial: a manuscript, its authors as they
 * typed them, its reviewers, its decision. Everything to the right is publishing: an
 * Article with a frozen slug, a DOI suffix, a Crossref deposit and a permanent URL. This
 * is the one place they touch, and `submissions.article_id` is the join.
 *
 * Three rules govern the crossing:
 *
 *  1. THE ARTICLE IS CREATED AS A DRAFT. Acceptance is an editorial decision; PUBLICATION
 *     is a separate, higher-privilege act that freezes URLs and spends money at Crossref.
 *     An accepted manuscript still has to go through PublishArticleAction's pre-flight —
 *     it needs a section, a page range, a position in the issue. Converting straight to
 *     `published` would skip every one of those checks and mint a DOI for a paper with no
 *     pages.
 *
 *  2. NOTHING IS MOVED, ONLY COPIED. The submission keeps its file, its version history
 *     and its audit trail. The version reviewer 2 actually read must still be retrievable
 *     when the decision is challenged.
 *
 *  3. IT IS IDEMPOTENT. A submission already linked to an article returns that article. A
 *     retried decision must not mint a second paper.
 */
final class ConvertSubmissionToArticleAction
{
    public function execute(Submission $submission, ?User $actor = null): Article
    {
        $submission->loadMissing(['journal', 'authors', 'latestManuscript']);

        if ($submission->article_id !== null) {
            return $submission->article;
        }

        return DB::transaction(function () use ($submission, $actor): Article {
            $article = Article::create([
                'journal_id' => $submission->journal_id,
                'journal_section_id' => $submission->journal_section_id,

                // issue_id, sequence and the page range are left NULL: placing the paper in
                // an issue is a production decision, and PublishArticleAction refuses to
                // publish an issue-based article without them. That refusal is the point.
                'issue_id' => null,
                'sequence' => null,

                'slug' => $this->uniqueSlug($submission),
                'doi_suffix' => null,     // only DoiSuffixGenerator may ever fill this in

                'title' => $submission->title,
                'abstract' => $submission->abstract,
                'keywords' => $submission->keywords,

                'status' => ArticleStatus::Draft,
                'published_at' => null,
            ]);

            $this->copyAuthors($submission, $article);
            $this->copyManuscript($submission, $article);

            $submission->forceFill(['article_id' => $article->id])->save();

            $submission->recordEvent('submission.converted_to_article', [
                'article_id' => $article->id,
                'slug' => $article->slug,
                'status' => ArticleStatus::Draft->value,
            ], $actor);

            return $article;
        });
    }

    /**
     * The slug is NOT frozen yet — publication freezes it. It must still be unique within
     * the journal from the moment it exists, because (journal_id, slug) is a unique index
     * and two accepted papers with the same title are not a hypothetical.
     */
    private function uniqueSlug(Submission $submission): string
    {
        $base = Str::limit(Str::slug($submission->title), 180, '');

        if ($base === '') {
            $base = 'article';
        }

        $slug = $base;
        $n = 2;

        while (Article::where('journal_id', $submission->journal_id)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    /**
     * The submission holds ONE name field, because that is what the wizard asks for.
     * Crossref needs given and family separately, so the split happens here, on the LAST
     * space: "Maria del Carmen Ramírez" → given "Maria del Carmen", family "Ramírez".
     */
    private function copyAuthors(Submission $submission, Article $article): void
    {
        foreach ($submission->authors as $author) {
            [$given, $family] = $author->splitName();

            $article->authors()->create([
                'given_name' => $given,
                'family_name' => $family,
                'affiliation' => $author->affiliation,
                'orcid' => $author->orcid,
                'email' => $author->email,
                'is_corresponding' => $author->is_corresponding,
                'sequence' => $author->sequence,
            ]);
        }
    }

    /**
     * The accepted manuscript becomes the article's PDF.
     *
     * It is COPIED, not moved (see rule 2). If the stored file has gone missing the row is
     * still written against the same path — a missing file is a production problem to fix,
     * whereas silently dropping the only copy of an accepted paper is not recoverable. The
     * publish gate refuses to publish an article with no PDF regardless.
     */
    private function copyManuscript(Submission $submission, Article $article): void
    {
        $manuscript = $submission->latestManuscript;

        if ($manuscript === null) {
            return;
        }

        $disk = Storage::disk('private');
        $target = 'articles/'.$article->id.'/'.basename($manuscript->path);

        if ($disk->exists($manuscript->path) && ! $disk->exists($target)) {
            $disk->copy($manuscript->path, $target);
        }

        $article->files()->create([
            'type' => ArticleFileType::Pdf,
            'path' => $target,
            'label' => 'Full text (PDF)',
            'original_name' => $manuscript->original_name,
            'mime_type' => $manuscript->mime_type,
            'size_bytes' => $manuscript->size_bytes,
        ]);
    }
}
