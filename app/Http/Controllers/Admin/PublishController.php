<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\PublishArticleAction;
use App\Actions\PublishIssueAction;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * THE PUBLISH GATE. The highest-risk endpoint in the system, and deliberately the thinnest.
 *
 * It authorises, calls the EXISTING action, and translates the refusal. It re-implements
 * none of the pre-flight: PublishArticleAction and PublishIssueAction already collect every
 * failure and throw them together, and a second copy of those rules in a controller is a
 * second copy that drifts.
 *
 * WHY THE ValidationException IS CAUGHT RATHER THAN LET FLY:
 *
 * Inertia's shared `errors` prop maps each field to its FIRST message only. The actions
 * deliberately return several messages under one key — 'pages' can hold three overlapping
 * ranges, 'articles' one line per article in the issue — and showing an editor the first of
 * those, letting them fix it, then showing the second, is precisely the failure mode the
 * "every problem at once" rule exists to prevent. So the complete, flattened list is flashed
 * separately, and the UI renders all of it.
 *
 * `production` cannot reach any of this: JournalPolicy::publish is the gate, and production
 * does not carry `journal.publish`. It can prepare everything and make nothing permanent.
 */
final class PublishController extends Controller
{
    public function article(Article $article, PublishArticleAction $publish): RedirectResponse
    {
        $article->loadMissing('journal');

        $this->authorize('publish', $article);

        try {
            $publish->execute($article);
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        return back()->with('success', $this->confirmation($article->fresh()));
    }

    public function issue(Issue $issue, PublishIssueAction $publish): RedirectResponse
    {
        $issue->loadMissing('volume.journal');

        // IssuePolicy::publish is false on an already-published issue as well as for anyone
        // without journal.publish.
        $this->authorize('publish', $issue);

        try {
            $published = $publish->execute($issue);
        } catch (ValidationException $e) {
            return $this->refuse($e);
        }

        $count = $published->articles()->count();

        return back()->with('success', $count === 1
            ? 'The issue is published. Its article is live and its URL is permanent.'
            : "The issue is published. All {$count} articles are live and their URLs are permanent.");
    }

    /**
     * EVERY failure, flattened, in one flash. Not the first one.
     */
    private function refuse(ValidationException $e): RedirectResponse
    {
        return back()
            ->withErrors($e->errors())
            ->with('publishErrors', collect($e->errors())->flatten()->values()->all());
    }

    private function confirmation(Article $article): string
    {
        $doi = $article->doi();

        if ($doi !== null) {
            return "Published. The DOI {$doi} is being registered with Crossref — watch the deposit log.";
        }

        // Publishing without a prefix is legitimate and normal for a launch journal. The URL
        // is permanent from now on; the DOI is registered later, when Crossref issues one.
        return 'Published. The URL is now permanent. Crossref has not issued this journal a '
            .'prefix, so no DOI can be registered yet — it will be deposited once one exists.';
    }
}
