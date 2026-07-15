<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalResource;
use App\Models\Field;
use App\Models\Journal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = $request->string('sort', 'impact')->toString();

        $journals = Journal::query()
            ->where('is_active', true)
            ->with(['field', 'metric', 'coverMedia'])
            ->when($request->filled('field'), fn ($q) => $q->whereHas(
                'field',
                fn ($f) => $f->where('name', $request->string('field'))
            ))
            ->leftJoin('journal_metrics', 'journal_metrics.journal_id', '=', 'journals.id')
            ->select('journals.*')
            ->orderBy(...$this->ordering($sort))
            ->get();

        return Inertia::render('Journals', [
            'journals' => JournalResource::collection($journals),
            'fields' => Field::orderBy('sequence')->pluck('name'),
            'filters' => [
                // `sort` now round-trips through the URL. It previously lived in local
                // component state, so a sorted view could not be shared or bookmarked.
                'field' => $request->string('field')->toString() ?: null,
                'sort' => $sort,
            ],
            'meta' => [
                'title' => 'Journals — '.config('app.name'),
                'description' => 'Open-access journals across every field of research.',
            ],
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function ordering(string $sort): array
    {
        return match ($sort) {
            'articles' => ['journal_metrics.article_count', 'desc'],
            // "speed" sorts ASCENDING — fewer days to a decision is better. Getting this
            // backwards would present the slowest journals as the fastest.
            'speed' => ['journal_metrics.median_days_to_decision', 'asc'],
            default => ['journal_metrics.impact_factor', 'desc'],
        };
    }
}
