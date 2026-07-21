<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Does this person hold {role} on ANY journal?"
 *
 * The pivot is queried DIRECTLY rather than through $user->roles(), because with teams
 * enabled that relation is scoped to the CURRENT team id — which in a request that names
 * no journal is NULL, so it would answer "no roles" for everybody. This is the same trap
 * JournalPolicy::hasOnJournal and Admin\UserController::withTeam work around from the
 * other direction; here there is no journal to set, so the relation is bypassed entirely.
 *
 * This exists because SITE-WIDE gates need it and per-journal policies do not. A gate like
 * `manage-users` has no journal to scope to, but "runs the publishing operation somewhere"
 * is still a meaningful answer to "may you create an account".
 */
final class GlobalRoles
{
    public static function holdsAnywhere(User $user, string $role): bool
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        return DB::table($tables['model_has_roles'])
            ->join($tables['roles'], "{$tables['roles']}.id", '=', "{$tables['model_has_roles']}.role_id")
            ->where("{$tables['model_has_roles']}.{$columns['model_morph_key']}", $user->getKey())
            ->where("{$tables['model_has_roles']}.model_type", $user->getMorphClass())
            ->where("{$tables['roles']}.name", $role)
            ->exists();
    }
}
