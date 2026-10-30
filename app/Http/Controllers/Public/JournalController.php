<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PublicationModel;
use App\Http\Controllers\Controller;
use App\Http\Resources\IssueCardResource;
use App\Http\Resources\JournalResource;
use App\Models\Issue;
use App\Models\Journal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /journals — browsed ISSUE BY ISSUE.
 *
 * An issue-based journal (JCD&MS) is shown as one card per published issue — "Volume 5 ·
 * Number 1 · Summer 2017" — not as a single card holding every article it has ever printed.
 * Readers filter by volume and choose newest or oldest first; both live in the URL, so a
 * filtered view can be shared and bookmarked.
 *
 * A continuous journal has no issues, so it still appears as one journal card.
 */
class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->string('sort')->toString() === 'oldest' ? 'oldest' : 'newest';
        $volume = $request->integer('volume') ?: null;

        $all = IssueCardResource::query()->get();

        // The volume chips come from ALL issues, so choosing one never hides the others.
        $volumes = $all
            ->groupBy(fn (Issue $i) => $i->volume->number)
            ->map(function ($issues, $number) {
                $years = $issues->map(fn (Issue $i) => $i->publication_date?->year ?? $i->volume->year)->filter()->unique()->sort()->values();

                return [
                    'number' => (int) $number,
                    'years' => $years->count() > 1 ? $years->first().'–'.$years->last() : (string) $years->first(),
                ];
            })
            ->sortByDesc('number')
            ->values();

        $issues = $all
            ->when($volume, fn ($c) => $c->filter(fn (Issue $i) => $i->volume->number === $volume))
            ->when($sort === 'oldest', fn ($c) => $c->reverse())
            ->values();

        $continuous = Journal::query()
            ->where('is_active', true)
            ->where('publication_model', PublicationModel::Continuous)
            ->with(['field', 'metric', 'coverMedia'])
            ->orderBy('title')
            ->get();

        $issueJournals = $all->map(fn (Issue $i) => $i->volume->journal->title)->unique()->values();

        return Inertia::render('Journals', [
            'issues' => IssueCardResource::collection($issues),
            'journals' => $volume ? [] : JournalResource::collection($continuous),
            'volumes' => $volumes,
            'journalTitles' => $issueJournals,
            'filters' => [
                'volume' => $volume,
                'sort' => $sort,
            ],
            'meta' => [
                'title' => 'Journals — '.config('app.name'),
                'description' => $issueJournals->count() === 1
                    ? "Every issue of the {$issueJournals->first()}, volume by volume. Open access."
                    : 'Every issue of our open-access journals, volume by volume.',
            ],
        ]);
    }
}
