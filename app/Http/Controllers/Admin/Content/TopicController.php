<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\ResearchTopic;
use App\Models\User;
use App\Services\Content\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Research Topics — open calls for papers.
 *
 * `body` and `submission_email` were added with the media library migration, and the reason is
 * in that migration's docblock: research_topics.description existed but nothing rendered it,
 * because there was no detail page. A call for papers with no scope is not a call for papers —
 * an author cannot tell whether their manuscript is in it.
 *
 * The slug is the URL. Same rule as News: changing it after publication is allowed (it is not a
 * DOI), and the form says what breaks when you do.
 *
 * `journal_id` is NULLABLE and stays so — a cross-journal call for papers is a real thing, and
 * forcing one journal onto it would be a lie about who is running it.
 */
final class TopicController extends Controller
{
    public function index(): Response
    {
        $topics = ResearchTopic::query()
            ->with(['media', 'journal', 'editors'])
            ->orderByDesc('is_open')
            ->orderBy('deadline')
            ->get();

        return Inertia::render('Admin/Content/Topics', [
            'topics' => $topics->map(fn (ResearchTopic $topic): array => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'description' => $topic->description,
                'deadline' => $topic->deadline?->toDateString(),
                'isOpen' => $topic->is_open,
                'journal' => $topic->journal?->abbreviation ?? $topic->journal?->title,
                'editors' => $topic->editors->map(fn (User $u): string => $u->fullName())->all(),
                'image' => $topic->media?->only(['url', 'alt']),
                'url' => '/topics/'.$topic->slug,
            ])->values()->all(),

            'meta' => [
                'title' => 'Research Topics — content',
                'description' => 'Open calls for papers.',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Content/TopicEditor', array_merge($this->formData(), [
            'topic' => null,
            'meta' => [
                'title' => 'New Research Topic — content',
                'description' => 'A new call for papers.',
            ],
        ]));
    }

    public function edit(ResearchTopic $topic): Response
    {
        $topic->load('editors');

        return Inertia::render('Admin/Content/TopicEditor', array_merge($this->formData(), [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'description' => $topic->description,
                'body' => $topic->body,
                'mediaId' => $topic->media_id,
                'deadline' => $topic->deadline?->toDateString(),
                'journalId' => $topic->journal_id,
                'submissionEmail' => $topic->submission_email,
                'isOpen' => $topic->is_open,
                'editorIds' => $topic->editors->pluck('id')->all(),
                'url' => '/topics/'.$topic->slug,
            ],
            'meta' => [
                'title' => $topic->title.' — content',
                'description' => 'Edit this call for papers.',
            ],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $editors = $data['editors'];
        unset($data['editors']);

        $topic = DB::transaction(function () use ($data, $editors): ResearchTopic {
            $topic = ResearchTopic::create($data);
            $topic->editors()->sync($editors);

            return $topic;
        });

        return redirect()
            ->route('admin.content.topics.edit', $topic)
            ->with('success', "“{$topic->title}” created.");
    }

    public function update(Request $request, ResearchTopic $topic): RedirectResponse
    {
        $data = $this->validated($request, $topic);
        $editors = $data['editors'];
        unset($data['editors']);

        DB::transaction(function () use ($topic, $data, $editors): void {
            $topic->update($data);
            $topic->editors()->sync($editors);
        });

        return back()->with('success', "“{$topic->title}” saved.");
    }

    public function destroy(ResearchTopic $topic): RedirectResponse
    {
        $title = $topic->title;
        $topic->delete();

        return redirect()
            ->route('admin.content.topics.index')
            ->with('success', "“{$title}” deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ResearchTopic $topic): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('research_topics', 'slug')->ignore($topic?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:200000'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'deadline' => ['nullable', 'date'],
            'journal_id' => ['nullable', 'integer', 'exists:journals,id'],
            'submission_email' => ['nullable', 'email:rfc', 'max:255'],
            'is_open' => ['boolean'],
            'editors' => ['array'],
            'editors.*' => ['integer', 'exists:users,id'],
        ], [
            'slug.regex' => 'A slug is lowercase letters, numbers and hyphens — it is the URL.',
        ]);

        $data['slug'] = Str::lower($data['slug']);
        $data['is_open'] = $data['is_open'] ?? true;
        $data['editors'] = $data['editors'] ?? [];

        return $data;
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'media' => app(MediaLibrary::class)->options(),

            'journals' => Journal::query()
                ->orderBy('title')
                ->get(['id', 'title', 'abbreviation'])
                ->map(fn (Journal $journal): array => [
                    'id' => $journal->id,
                    'title' => $journal->title,
                    'abbreviation' => $journal->abbreviation,
                ])
                ->all(),

            'users' => User::query()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->fullName(),
                    'email' => $user->email,
                ])
                ->all(),
        ];
    }
}
