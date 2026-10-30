<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Issue;
use App\Models\Journal;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One issue's table of contents: /journals/{journal}/volume/{volume}/issue/{number}.
 *
 * The address carries the volume and issue NUMBERS — the way the issue is printed and
 * cited — so it reads correctly and survives a database rebuild. Only PUBLISHED issues of
 * ACTIVE journals are public; anything else is a 404, never a half-empty page.
 */
class IssueShowController extends Controller
{
    public function show(Journal $journal, int $volume, int $number): Response
    {
        $issue = $this->resolve($journal, $volume, $number);

        $articles = Article::query()
            ->published()
            ->where('issue_id', $issue->id)
            ->with(['authors', 'section', 'pdf'])
            ->orderBy('sequence')
            ->get();

        // Neighbouring issues in publication order, for "previous / next issue".
        $siblings = Issue::query()
            ->published()
            ->whereHas('volume', fn ($q) => $q->where('journal_id', $journal->id))
            ->with('volume.journal')
            ->orderBy('publication_date')
            ->orderBy('number')
            ->get();
        $at = $siblings->search(fn (Issue $i) => $i->id === $issue->id);
        $neighbour = fn (?Issue $i) => $i ? ['label' => $i->label(), 'season' => $i->season, 'url' => $i->url()] : null;

        $label = $issue->label();

        return Inertia::render('Issue', [
            'journal' => [
                'slug' => $journal->slug,
                'title' => $journal->title,
                'abbreviation' => $journal->abbreviation,
                'openAccess' => (bool) $journal->open_access,
            ],
            'issue' => [
                'volume' => $issue->volume->number,
                'number' => $issue->number,
                'label' => $label,
                'season' => $issue->season,
                'publicationDate' => $issue->publication_date?->toDateString(),
                'cover' => $issue->coverMedia?->only(['url', 'alt']),
                'fullPdfUrl' => $issue->fullPdfUrl(),
            ],
            'articles' => $articles->map(fn (Article $a) => [
                'slug' => $a->slug,
                'title' => $a->title,
                'section' => $a->section?->name,
                // A research centre writing as a body is credited as such — never blank.
                'authors' => $a->hasCorporateAuthor()
                    ? [$a->corporate_author]
                    : $a->authors->map(fn ($au) => trim("{$au->given_name} {$au->family_name}"))->values()->all(),
                'pageRange' => $a->pageRange(),
                'pdfUrl' => $a->pdf ? $a->pdfUrl() : null,
            ])->values(),
            'previous' => $at === false ? null : $neighbour($siblings->get($at - 1)),
            'next' => $at === false ? null : $neighbour($siblings->get($at + 1)),
            'meta' => [
                'title' => trim("{$label} · {$issue->season}", ' ·').' — '.$journal->title,
                'description' => "{$journal->title}, {$label}".($issue->season ? " ({$issue->season})" : '')
                    .'. '.$articles->count().' open-access articles.',
            ],
        ])->withViewData(['canonical' => $issue->url()]);
    }

    /** The whole printed issue — streamed inline, like every article PDF. */
    public function pdf(Journal $journal, int $volume, int $number): StreamedResponse
    {
        $issue = $this->resolve($journal, $volume, $number);

        abort_if(blank($issue->full_pdf_path), 404);
        abort_unless(Storage::disk('private')->exists($issue->full_pdf_path), 404);

        // "JCD&MS" -> "JCDMS", then "JCDMS-Vol5-No1-Summer-2017.pdf".
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', sprintf(
            '%s Vol%d No%d %s',
            preg_replace('/[^A-Za-z0-9]/', '', (string) ($journal->abbreviation ?: $journal->slug)),
            $volume,
            $number,
            $issue->season ?? '',
        ));
        $name = trim((string) $name, '-').'.pdf';

        return Storage::disk('private')->response($issue->full_pdf_path, $name, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    private function resolve(Journal $journal, int $volume, int $number): Issue
    {
        abort_unless($journal->is_active, 404);

        $issue = Issue::query()
            ->published()
            ->where('number', $number)
            ->whereHas('volume', fn ($q) => $q->where('journal_id', $journal->id)->where('number', $volume))
            ->with(['volume.journal', 'coverMedia'])
            ->first();

        abort_if($issue === null, 404);

        return $issue;
    }
}
