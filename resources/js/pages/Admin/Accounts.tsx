import { useEffect, useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { CircleSlash, Pencil, Search, ShieldCheck, UserPlus, Users as UsersIcon } from 'lucide-react'
import { PeopleShell } from '@/components/admin/PeopleShell'
import { EmptyState, INPUT } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { peopleHref } from '@/lib/admin'
import type { Meta } from '@/lib/props'

type Assignment = { journal: string; roles: string[] }

type Account = {
  id: number
  name: string
  email: string
  affiliation: string | null
  isActive: boolean
  isSiteAdmin: boolean
  /** Every journal this person holds a role on. Absent journals are absent, not empty. */
  roles: Assignment[]
}

type Props = {
  users: { data: Account[]; links: { url: string | null; label: string; active: boolean }[]; total: number }
  filters: { q: string }
  can: { grantSiteAdmin: boolean; manageRoles: boolean }
  meta: Meta
}

/**
 * Every account on the platform.
 *
 * The per-journal People screen answers "who edits JCD&MS". This one answers "who has an
 * account, and what can they reach" — the question that had no screen at all, which is why
 * creating a user meant editing a seeder and granting site admin meant a SQL client.
 *
 * The roles column shows EVERY journal a person works on, because that is the whole point
 * of a site-wide view: per-journal screens structurally cannot show it, and "reviewer here,
 * editor there" is the normal case this system was built for.
 */
export default function Accounts({ users, filters, can, meta }: Props) {
  const [q, setQ] = useState(filters.q)

  // Debounced search. `replace` so a search does not stack twenty entries on the back
  // button, `preserveState` so the input does not lose focus mid-keystroke.
  useEffect(() => {
    if (q === filters.q) return

    const id = setTimeout(() => {
      router.get(peopleHref.accounts, q ? { q } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      })
    }, 300)

    return () => clearTimeout(id)
  }, [q, filters.q])

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <PeopleShell
        title="Accounts"
        description="Everyone with a login. Roles are granted per journal — someone edits one and reviews for another."
        canManageRoles={can.manageRoles}
        actions={
          <Link href={peopleHref.newAccount} className="btn-primary">
            <UserPlus className="h-4 w-4" aria-hidden="true" />
            New account
          </Link>
        }
      >
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
          <div className="relative w-full max-w-sm">
            <Search
              className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500"
              aria-hidden="true"
            />
            <label htmlFor="account-search" className="sr-only">
              Search accounts by name or email
            </label>
            <input
              id="account-search"
              type="search"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Name, email or affiliation…"
              className={`${INPUT} mt-0 pl-9`}
            />
          </div>

          <p className="text-sm text-ink-600" aria-live="polite">
            {users.total} {users.total === 1 ? 'account' : 'accounts'}
            {filters.q && ' matching'}
          </p>
        </div>

        {users.data.length === 0 ? (
          <EmptyState
            icon={UsersIcon}
            title={filters.q ? 'Nobody matches that' : 'No accounts yet'}
            body={
              filters.q
                ? 'Try a different name, email or affiliation.'
                : 'Create the first account to get someone into the editorial office.'
            }
          />
        ) : (
          <RevealGroup className="space-y-3" stagger={0.04}>
            {users.data.map((account) => (
              <RevealItem key={account.id}>
                <AccountRow account={account} />
              </RevealItem>
            ))}
          </RevealGroup>
        )}

        {users.links.length > 3 && (
          <nav aria-label="Pagination" className="mt-8 flex flex-wrap justify-center gap-1">
            {users.links.map((link, i) =>
              link.url ? (
                <Link
                  key={i}
                  href={link.url}
                  aria-current={link.active ? 'page' : undefined}
                  preserveScroll
                  className={`min-w-9 rounded-lg px-3 py-2 text-center text-sm font-semibold transition-colors
                              duration-200 ${
                                link.active
                                  ? 'bg-brand-700 text-white'
                                  : 'text-ink-600 hover:bg-ink-50 hover:text-ink-900'
                              }`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ) : (
                <span
                  key={i}
                  className="min-w-9 px-3 py-2 text-center text-sm text-ink-400"
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ),
            )}
          </nav>
        )}
      </PeopleShell>
    </>
  )
}

function AccountRow({ account }: { account: Account }) {
  return (
    <div className="card flex flex-wrap items-start justify-between gap-4 p-5">
      <div className="min-w-0 flex-1">
        <p className="flex flex-wrap items-center gap-2 font-semibold text-ink-900">
          {account.name}

          {account.isSiteAdmin && (
            <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800">
              <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
              Site admin
            </span>
          )}

          {/* Status carries an icon AND a label. Colour is never the only carrier. */}
          {!account.isActive && (
            <span className="inline-flex items-center gap-1 rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-700">
              <CircleSlash className="h-3.5 w-3.5" aria-hidden="true" />
              Deactivated
            </span>
          )}
        </p>

        <p className="mt-0.5 truncate text-sm text-ink-600">
          {account.email}
          {account.affiliation && <span className="text-ink-500"> · {account.affiliation}</span>}
        </p>

        {account.roles.length === 0 ? (
          <p className="mt-3 text-xs text-ink-500">
            {account.isSiteAdmin
              ? 'No per-journal roles — the site admin flag already grants everything.'
              : 'No roles on any journal.'}
          </p>
        ) : (
          <ul className="mt-3 flex flex-wrap gap-2">
            {account.roles.map((assignment) => (
              <li
                key={assignment.journal}
                className="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-2.5 py-1 text-xs"
              >
                <span className="font-semibold text-ink-900">{assignment.journal}</span>
                <span className="font-mono text-[11px] text-ink-600">
                  {assignment.roles.join(', ')}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      <Link href={peopleHref.editAccount(account.id)} className="btn-ghost shrink-0">
        <Pencil className="h-4 w-4" aria-hidden="true" />
        Edit
      </Link>
    </div>
  )
}
