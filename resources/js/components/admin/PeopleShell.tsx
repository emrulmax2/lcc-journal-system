import { type ReactNode } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { ArrowLeft, ShieldCheck, Users, type LucideIcon } from 'lucide-react'
import { Flash } from '@/components/admin/Shell'
import { Reveal } from '@/components/Reveal'
import { peopleHref } from '@/lib/admin'

/**
 * The chrome for the SITE-WIDE people screens: accounts, and what the roles mean.
 *
 * It is not AdminShell, for the same reason ContentShell is not — AdminShell's tabs are a
 * journal's tabs and its header carries a journal's name, so it cannot render without one.
 * An ACCOUNT has no journal. A person exists, and then they hold roles on journals; the
 * order matters, and it is why "create a user" was never expressible on the per-journal
 * People screen. Passing a journal in here to satisfy a component would invent a
 * relationship the data does not have.
 *
 * Same product, same tokens, same Lucide icons, same tab shape. Different subject.
 *
 * NO framer `initial` variant wraps any of this. <Reveal> renders VISIBLE on the server and
 * only hides what is below the fold, on the client, after mount — an `initial={{opacity:0}}`
 * would serialise the whole screen invisible into the SSR HTML.
 */

type Tab = { label: string; href: string; icon: LucideIcon; show: boolean }

export function PeopleShell({
  title,
  description,
  actions,
  canManageRoles,
  children,
}: {
  title: string
  description?: string
  actions?: ReactNode
  /** Site admin only. A tab nobody may use is ABSENT, never disabled-and-taunting. */
  canManageRoles: boolean
  children: ReactNode
}) {
  const { url } = usePage()
  const path = url.split('?')[0]

  const tabs: Tab[] = [
    { label: 'Accounts', href: peopleHref.accounts, icon: Users, show: true },
    { label: 'Roles & permissions', href: peopleHref.roles, icon: ShieldCheck, show: canManageRoles },
  ]

  return (
    <>
      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page pt-10">
          <Reveal className="flex flex-wrap items-end justify-between gap-6">
            <div className="min-w-0">
              <p className="eyebrow">People</p>
              <h1 className="mt-3 font-serif text-3xl sm:text-4xl">{title}</h1>
              {description && <p className="mt-3 max-w-prose text-ink-600">{description}</p>}
            </div>
            <div className="flex flex-wrap items-center gap-3">
              {actions}
              <Link href="/admin" className="btn-ghost">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Editorial admin
              </Link>
            </div>
          </Reveal>

          <nav aria-label="People" className="mt-8 flex flex-wrap gap-1 overflow-x-auto">
            {tabs
              .filter((t) => t.show)
              .map((tab) => {
                const active = path.startsWith(tab.href)
                const Icon = tab.icon

                return (
                  <Link
                    key={tab.href}
                    href={tab.href}
                    aria-current={active ? 'page' : undefined}
                    className={`inline-flex cursor-pointer items-center gap-2 rounded-t-lg border-b-2 px-4 py-3
                                text-sm font-semibold transition-colors duration-200 ${
                                  active
                                    ? 'border-brand-700 text-brand-800'
                                    : 'border-transparent text-ink-600 hover:border-ink-300 hover:text-ink-900'
                                }`}
                  >
                    <Icon className="h-4 w-4" aria-hidden="true" />
                    {tab.label}
                  </Link>
                )
              })}
          </nav>
        </div>
      </header>

      <div className="container-page py-10">
        <Flash />
        {children}
      </div>
    </>
  )
}
