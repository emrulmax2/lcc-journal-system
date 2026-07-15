<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ResearchTopic;
use App\Services\Content\MarkdownRenderer;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Research Topics — open calls for papers.
 *
 * These cards were on the homepage and every one of them linked to /journals. A call for
 * papers whose scope and deadline you cannot read is not a call for papers; it is a
 * poster with no text on it. `research_topics.slug` and `.description` existed and were
 * rendered nowhere.
 */
class ResearchTopicController extends Controller
{
    public function index(): Response
    {
        $topics = ResearchTopic::query()
            ->with(['media', 'journal', 'editors'])
            ->orderByDesc('is_open')
            ->orderBy('deadline')
            ->get();

        return Inertia::render('ResearchTopics', [
            'topics' => $topics->map(fn (ResearchTopic $t) => $this->card($t)),
            'meta' => [
                'title' => 'Research Topics',
                'description' => 'Open calls for papers, led by topic editors.',
            ],
        ]);
    }

    public function show(ResearchTopic $topic): Response
    {
        $topic->load(['media', 'journal', 'editors']);

        return Inertia::render('ResearchTopic', [
            'topic' => array_merge($this->card($topic), [
                'bodyHtml' => app(MarkdownRenderer::class)->toHtml($topic->body),
                'submissionEmail' => $topic->submission_email,
                'editors' => $topic->editors->map(fn ($e) => [
                    'name' => $e->fullName(),
                    'affiliation' => $e->affiliation,
                    'orcidUrl' => $e->orcidUrl(),
                ])->values(),
            ]),
            'meta' => [
                'title' => $topic->title,
                'description' => $topic->description
                    ?: app(MarkdownRenderer::class)->toText($topic->body),
            ],
        ])->withViewData(['canonical' => route('topics.show', $topic->slug)]);
    }

    /** @return array<string, mixed> */
    private function card(ResearchTopic $topic): array
    {
        return [
            'slug' => $topic->slug,
            'title' => $topic->title,
            'description' => $topic->description,
            'deadline' => $topic->deadline?->toDateString(),

            // The deadline is the single most important fact on the card, so say plainly
            // whether it has passed rather than leaving the reader to work it out.
            'isOpen' => (bool) $topic->is_open && ($topic->deadline === null || $topic->deadline->isFuture()),
            'hasClosed' => $topic->deadline !== null && $topic->deadline->isPast(),

            'journal' => $topic->journal?->only(['slug', 'title']),
            'editorCount' => $topic->editors->count(),
            'url' => route('topics.show', $topic->slug),
            'image' => $topic->media?->only(['url', 'alt', 'caption', 'credit']),
            'photo' => $topic->photo_key,
        ];
    }
}
