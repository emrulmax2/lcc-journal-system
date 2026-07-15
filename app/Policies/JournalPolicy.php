<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every check here is SCOPED TO A JOURNAL.
 *
 * This is the whole reason for Spatie's teams feature. `$user->can('journal.publish')`
 * with no journal context is a meaningless question — the honest answer is "on which
 * journal?". So every method sets the team context to the journal being checked before
 * asking, which is what stops an editor of Journal A from touching Journal B.
 */
class JournalPolicy
{
    public function view(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'journal.view');
    }

    public function manageSettings(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'journal.settings.manage');
    }

    public function manageIssues(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'journal.issue.manage');
    }

    public function manageArticles(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'journal.article.manage');
    }

    /**
     * The high-privilege gate. Publishing makes URLs permanent and, downstream, spends
     * money at Crossref minting identifiers that can never be withdrawn. `production`
     * deliberately does not have it, despite being able to edit everything else.
     */
    public function publish(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'journal.publish');
    }

    public function depositDois(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'journal.doi.deposit');
    }

    public function manageUsers(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'journal.users.manage');
    }

    public function viewAllSubmissions(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'submission.view.all');
    }

    public function assignReviewers(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'review.assign');
    }

    public function recordDecision(User $user, Journal $journal): bool
    {
        return $this->hasOnJournal($user, $journal, 'decision.record');
    }

    /**
     * Ask the permission question IN THE CONTEXT OF THIS JOURNAL.
     *
     * The team id is restored afterwards. Without that, a single authorisation check
     * would leave the registrar pointing at whichever journal it last examined, and the
     * NEXT check in the same request — on a different journal — would silently answer
     * for the wrong one. That is a cross-tenant authorisation bug, and it would only
     * show up under a request that touches two journals.
     */
    private function hasOnJournal(User $user, Journal $journal, string $permission): bool
    {
        $registrar = App::make(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($journal->id);

            /*
             * unsetRelation IS THE FIX FOR A REAL CROSS-TENANT LEAK. Do not remove it.
             *
             * Setting the team id changes which roles Spatie WOULD load — but Eloquent
             * has already cached `roles` on this model instance from the previous check.
             * Without unsetting it, the second authorisation question in a request is
             * answered using the FIRST journal's roles.
             *
             * Concretely: an editor of Journal A asks "can I publish on A?" (yes, roles
             * now cached), then "can I publish on B?" — and gets YES, because the cached
             * relation still holds their Journal A roles. They can publish someone else's
             * journal and spend that journal's Crossref credits.
             *
             * This is covered by "it does not leak the team context between two checks in
             * one request" in JournalAuthorizationTest, which failed loudly before this
             * line existed.
             */
            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $user->hasPermissionTo($permission, 'web');
        } catch (PermissionDoesNotExist) {
            return false;
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
