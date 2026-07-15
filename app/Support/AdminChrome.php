<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The chrome every admin screen shares: which journal we are in, which journals this
 * person may switch to, and what they are allowed to do here.
 *
 * The abilities are asked of the POLICIES, per journal, and shipped to React as booleans.
 * The frontend uses them to decide what to RENDER; it is never the thing that decides what
 * is ALLOWED — every mutation is authorised again on the server. A hidden button is a
 * courtesy, not a control.
 *
 * `production` is the case that matters: it may edit every field of an article and may not
 * publish one. So the Publish button is absent for them, and the endpoint refuses them too.
 */
final class AdminChrome
{
    /** The editorial capabilities. Someone with none of these has no business in /admin. */
    private const EDITORIAL = ['manageArticles', 'manageIssues', 'manageSettings', 'manageUsers', 'publish'];

    /** @return array<string, mixed> */
    public static function for(User $user, Journal $journal): array
    {
        return [
            'journal' => [
                'id' => $journal->id,
                'slug' => $journal->slug,
                'title' => $journal->title,
                'abbreviation' => $journal->abbreviation,
                'publicationModel' => $journal->publication_model->value,

                // NULL is the honest answer until Crossref issues a prefix. Every screen
                // that could mint or deposit a DOI says so in words rather than failing.
                'doiPrefix' => $journal->doi_prefix,
                'canMintDois' => $journal->canMintDois(),
            ],

            'can' => [
                'manageArticles' => $user->can('manageArticles', $journal),
                'manageIssues' => $user->can('manageIssues', $journal),
                'manageSettings' => $user->can('manageSettings', $journal),
                'manageUsers' => $user->can('manageUsers', $journal),
                'depositDois' => $user->can('depositDois', $journal),
                'publish' => $user->can('publish', $journal),

                /*
                 * THE ONE ABILITY IN HERE THAT IS NOT ABOUT THIS JOURNAL.
                 *
                 * Site content — the footer, the privacy policy, the navigation — belongs to
                 * the site, not to a journal, so it is a Gate with no model rather than a
                 * JournalPolicy method. It rides along in `can` because it decides whether the
                 * "Content" tab renders, and the tab is on every admin screen.
                 */
                'manageSiteContent' => $user->can('manage-site-content'),
            ],

            'journals' => self::editorialJournals($user)
                ->map(fn (Journal $j): array => [
                    'id' => $j->id,
                    'title' => $j->title,
                    'abbreviation' => $j->abbreviation,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Journals this person can actually do editorial work on.
     *
     * NOT "journals they can view" — `author` and `reviewer` both carry journal.view, and
     * neither belongs in the editorial admin. The question is asked of the policy, journal
     * by journal, because a role means nothing without a journal to mean it on.
     *
     * @return Collection<int, Journal>
     */
    public static function editorialJournals(User $user): Collection
    {
        return Journal::query()
            ->orderBy('title')
            ->get()
            ->filter(fn (Journal $journal): bool => collect(self::EDITORIAL)
                ->contains(fn (string $ability): bool => $user->can($ability, $journal)))
            ->values();
    }
}
