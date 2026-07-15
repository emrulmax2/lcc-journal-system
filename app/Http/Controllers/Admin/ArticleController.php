<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ArticleFileType;
use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleFile;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Volume;
use App\Services\Doi\DoiSuffixGenerator;
use App\Support\AdminChrome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The article editor.
 *
 * THREE RULES ARE ENFORCED HERE AND NOWHERE ELSE IN THE UI LAYER:
 *
 * 1. AUTHORS AND REFERENCES SAVE IN ONE TRANSACTION with the article. The repeaters are not
 *    N separate requests: a half-saved author list on a metadata correction is how an
 *    article goes to Crossref with two of its four authors.
 *
 * 2. AN ORCID IS NEVER GUESSED. The format is validated; nothing is auto-filled, matched or
 *    inferred. A wrong ORCID does not produce a broken link — it attributes this work to a
 *    real, identifiable other person, in every index that consumes the deposit.
 *
 * 3. SLUG / SEQUENCE / DOI_SUFFIX ARE NOT ACCEPTED FROM A PUBLISHED ARTICLE'S FORM. They are
 *    frozen at publication. ArticleObserver throws if anything tries to change them, so this
 *    is a courtesy to the editor rather than the actual guarantee — but a form that posts a
 *    value the model will reject is a 500 waiting for a typo.
 */
final class ArticleController extends Controller
{
    private const ORCID = '/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/';

    public function create(Request $request, Journal $journal): Response
    {
        $this->authorize('manageArticles', $journal);

        return Inertia::render('Admin/ArticleEditor', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'article' => null,
                'issues' => $this->issues($journal),
                'sections' => $this->sections($journal),

                'meta' => [
                    'title' => 'New article — '.$journal->title,
                    'description' => 'Article metadata, authors, references and PDF.',
                ],
            ],
        ));
    }

    public function edit(Request $request, Article $article): Response
    {
        $article->load(['journal', 'issue.volume', 'authors', 'references', 'section', 'pdf']);

        $this->authorize('update', $article);

        $journal = $article->journal;

        return Inertia::render('Admin/ArticleEditor', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'article' => $this->present($article),
                'issues' => $this->issues($journal),
                'sections' => $this->sections($journal),

                'meta' => [
                    'title' => 'Edit — '.str($article->title)->limit(60),
                    'description' => 'Article metadata, authors, references and PDF.',
                ],
            ],
        ));
    }

    public function store(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorize('manageArticles', $journal);

        $data = $this->validated($request, $journal, null);

        $article = DB::transaction(function () use ($request, $journal, $data): Article {
            $article = new Article([
                'journal_id' => $journal->id,
                'status' => ArticleStatus::Draft,
            ]);

            $this->fill($article, $data, $journal);
            $article->save();

            $this->syncAuthors($article, $data);
            $this->syncReferences($article, $data);
            $this->storePdf($article, $request->file('pdf'));

            return $article;
        });

        return to_route('admin.articles.edit', $article->id)
            ->with('success', 'Article created. It is a DRAFT — nothing is public and no DOI exists yet.');
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $article->load(['journal', 'authors', 'pdf']);

        $this->authorize('update', $article);

        $journal = $article->journal;
        $data = $this->validated($request, $journal, $article);

        DB::transaction(function () use ($request, $article, $data, $journal): void {
            $this->fill($article, $data, $journal);
            $article->save();

            // ONE transaction with the article. Authors and references are not independent
            // resources — they are part of the record's identity, and a partial save of them
            // is a partial save of the article.
            $this->syncAuthors($article, $data);
            $this->syncReferences($article, $data);
            $this->storePdf($article, $request->file('pdf'));
        });

        return back()->with('success', 'Article saved.');
    }

    // --- Validation ---------------------------------------------------------

    /** @return array<string, mixed> */
    private function validated(Request $request, Journal $journal, ?Article $article): array
    {
        $frozen = $article?->isFrozen() ?? false;

        $rules = [
            'title' => ['required', 'string', 'max:500'],
            'abstract' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'keywords' => ['nullable', 'array', 'max:12'],
            'keywords.*' => ['string', 'max:120'],

            'journal_section_id' => [
                'nullable',
                Rule::exists('journal_sections', 'id')->where('journal_id', $journal->id),
            ],

            'issue_id' => [
                $journal->usesIssues() ? 'nullable' : 'prohibited',
                Rule::exists('issues', 'id')->whereIn(
                    'volume_id',
                    $journal->volumes()->pluck('id')->all() ?: [0],
                ),
            ],

            'first_page' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'last_page' => ['nullable', 'integer', 'min:1', 'max:65535', 'gte:first_page'],

            'corporate_author' => ['nullable', 'string', 'max:500'],

            'authors' => ['array', 'max:50'],
            'authors.*.given_name' => ['required', 'string', 'max:255'],
            'authors.*.family_name' => ['required', 'string', 'max:255'],
            'authors.*.affiliation' => ['nullable', 'string', 'max:255'],
            'authors.*.email' => ['nullable', 'email', 'max:255'],
            'authors.*.is_corresponding' => ['boolean'],

            // The format, and ONLY the format. Nothing here looks an author up, completes a
            // partial identifier or reuses one from another article of the same name.
            'authors.*.orcid' => ['nullable', 'string', 'regex:'.self::ORCID],

            'references' => ['array', 'max:500'],
            'references.*.raw_text' => ['required', 'string'],
            'references.*.doi' => ['nullable', 'string', 'max:255'],

            'pdf' => ['nullable', 'file', 'mimetypes:application/pdf', 'extensions:pdf', 'max:51200'],
        ];

        // FROZEN. A published article's URL and identifier are public promises; the form
        // renders them read-only and the endpoint does not accept them at all.
        if (! $frozen) {
            $rules['slug'] = [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')
                    ->where('journal_id', $journal->id)
                    ->ignore($article?->id),
            ];
            $rules['sequence'] = ['nullable', 'integer', 'min:1', 'max:9999'];
        }

        $data = $request->validate($rules, [
            'authors.*.orcid.regex' => 'An ORCID looks like 0000-0002-1825-0097. Leave it empty rather '
                .'than guessing — a wrong ORCID attributes this work to a real, identifiable other person.',
            'slug.regex' => 'The slug is the permanent URL: lowercase letters, numbers and hyphens only.',
            'issue_id.prohibited' => 'This journal publishes continuously. It has no issues to place an article in.',
            'last_page.gte' => 'The page range ends before it starts.',
            'pdf.mimetypes' => 'The full text must be a PDF — citation_pdf_url is advertised to Google Scholar.',
        ]);

        $authors = array_values(array_filter(
            $data['authors'] ?? [],
            fn (array $author): bool => filled($author['given_name'] ?? null) || filled($author['family_name'] ?? null),
        ));

        // An article has named authors OR a corporate author, never both. PublishArticleAction
        // refuses the combination at the gate; refusing it here means it cannot be SAVED,
        // rather than being discovered at the moment of publication.
        if (filled($data['corporate_author'] ?? null) && $authors !== []) {
            throw ValidationException::withMessages([
                'corporate_author' => 'An article has named authors or a corporate author, never both. '
                    .'Crossref accepts one or the other.',
            ]);
        }

        $data['authors'] = $authors;

        return $data;
    }

    // --- Persistence --------------------------------------------------------

    /** @param  array<string, mixed>  $data */
    private function fill(Article $article, array $data, Journal $journal): void
    {
        $article->fill([
            'title' => $data['title'],
            'abstract' => $data['abstract'] ?? null,
            'body' => $data['body'] ?? null,
            'keywords' => array_values(array_filter($data['keywords'] ?? [])),
            'journal_section_id' => $data['journal_section_id'] ?? null,
            'issue_id' => $journal->usesIssues() ? ($data['issue_id'] ?? null) : null,
            'first_page' => $data['first_page'] ?? null,
            'last_page' => $data['last_page'] ?? null,
            'corporate_author' => $data['corporate_author'] ?? null,
        ]);

        // Frozen attributes are absent from $data for a published article — validated()
        // never asked for them — so this cannot overwrite one by accident.
        if (array_key_exists('slug', $data)) {
            $article->slug = $data['slug'];
        }

        if (array_key_exists('sequence', $data)) {
            $article->sequence = $data['sequence'];
        }
    }

    /** @param  array<string, mixed>  $data */
    private function syncAuthors(Article $article, array $data): void
    {
        // A corporate-authored article has ZERO author rows. That is the valid shape, not an
        // empty state — the CLIR editorial in JCD&MS Vol 10 is exactly this.
        $article->authors()->delete();

        if (filled($data['corporate_author'] ?? null)) {
            return;
        }

        foreach ($data['authors'] as $i => $author) {
            $article->authors()->create([
                'given_name' => $author['given_name'],
                'family_name' => $author['family_name'],
                'affiliation' => $author['affiliation'] ?? null,
                'email' => $author['email'] ?? null,

                // NULL where the author has none. Never fabricated, never inherited from a
                // namesake, never carried over from another article.
                'orcid' => filled($author['orcid'] ?? null) ? strtoupper((string) $author['orcid']) : null,

                'is_corresponding' => (bool) ($author['is_corresponding'] ?? false),

                // Author order is meaningful — it is the contribution order, and Crossref
                // deposits it. It comes from the repeater's drag order, not from a sort.
                'sequence' => $i + 1,
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function syncReferences(Article $article, array $data): void
    {
        $article->references()->delete();

        foreach (array_values($data['references'] ?? []) as $i => $reference) {
            if (blank($reference['raw_text'] ?? null)) {
                continue;
            }

            $article->references()->create([
                'ordinal' => $i + 1,
                'raw_text' => $reference['raw_text'],
                'doi' => filled($reference['doi'] ?? null) ? $reference['doi'] : null,
            ]);
        }
    }

    /**
     * The PDF lives on the PRIVATE disk and is streamed through the stable public route.
     * citation_pdf_url points at that route, so the file must exist for as long as the DOI
     * does — which is for ever.
     */
    private function storePdf(Article $article, ?UploadedFile $pdf): void
    {
        if ($pdf === null) {
            return;
        }

        $path = $pdf->store('articles', 'private');

        $existing = $article->files()->where('type', ArticleFileType::Pdf)->first();

        if ($existing instanceof ArticleFile) {
            // Delete the old bytes only after the new ones are written. A replaced PDF that
            // failed to upload must never leave the article with no PDF at all.
            $old = $existing->path;

            $existing->update([
                'path' => $path,
                'original_name' => $pdf->getClientOriginalName(),
                'mime_type' => $pdf->getClientMimeType(),
                'size_bytes' => $pdf->getSize(),
            ]);

            if ($old !== $path && Storage::disk('private')->exists($old)) {
                Storage::disk('private')->delete($old);
            }

            return;
        }

        $article->files()->create([
            'type' => ArticleFileType::Pdf,
            'path' => $path,
            'label' => 'Full text (PDF)',
            'original_name' => $pdf->getClientOriginalName(),
            'mime_type' => $pdf->getClientMimeType(),
            'size_bytes' => $pdf->getSize(),
        ]);
    }

    // --- Presentation -------------------------------------------------------

    /** @return array<string, mixed> */
    private function present(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'abstract' => $article->abstract,
            'body' => $article->body,
            'keywords' => $article->keywords ?? [],
            'journal_section_id' => $article->journal_section_id,
            'issue_id' => $article->issue_id,
            'sequence' => $article->sequence,
            'first_page' => $article->first_page,
            'last_page' => $article->last_page,
            'corporate_author' => $article->corporate_author,

            'status' => $article->status->value,
            'statusLabel' => $article->status->label(),
            'isPublished' => $article->isPublished(),

            // Withdrawn articles are frozen too: the landing page keeps resolving, with a
            // notice, because the DOI must not die.
            'isFrozen' => $article->isFrozen(),
            'publishedAt' => $article->published_at?->toIso8601String(),

            'doiSuffix' => $article->doi_suffix ?? $this->derivedSuffix($article),
            'doi' => $article->doi(),
            'landingUrl' => $article->landingUrl(),

            'authors' => $article->authors->map(fn ($a): array => [
                'given_name' => $a->given_name,
                'family_name' => $a->family_name,
                'affiliation' => $a->affiliation,
                'email' => $a->email,
                'orcid' => $a->orcid,
                'is_corresponding' => (bool) $a->is_corresponding,
            ])->values()->all(),

            'references' => $article->references->map(fn ($r): array => [
                'raw_text' => $r->raw_text,
                'doi' => $r->doi,
            ])->values()->all(),

            'pdf' => $article->pdf === null ? null : [
                'name' => $article->pdf->original_name ?? 'article.pdf',
                'size' => $article->pdf->size_bytes,
                'url' => $article->pdfUrl(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function issues(Journal $journal): array
    {
        if (! $journal->usesIssues()) {
            return [];
        }

        return $journal->volumes()
            ->with('issues')
            ->orderByDesc('number')
            ->get()
            ->flatMap(fn (Volume $volume) => $volume->issues->map(fn (Issue $issue): array => [
                'id' => $issue->id,
                'label' => "Vol {$volume->number}, No {$issue->number}"
                    .($issue->season ? " ({$issue->season})" : ''),
                'isPublished' => $issue->isPublished(),
            ]))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function sections(Journal $journal): array
    {
        return $journal->sections()
            ->where('is_active', true)
            ->get()
            ->map(fn (JournalSection $section): array => [
                'id' => $section->id,
                'name' => $section->name,

                // Front matter gets no DOI. The editor needs to know that BEFORE filing an
                // article under a section, not after the deposit skips it.
                'doiEligible' => (bool) $section->doi_eligible,
            ])
            ->all();
    }

    /** What the suffix WOULD be. NULL when it cannot be derived — never a guess. */
    private function derivedSuffix(Article $article): ?string
    {
        try {
            return app(DoiSuffixGenerator::class)->generate($article);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
