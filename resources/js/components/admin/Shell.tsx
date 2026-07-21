import { type ReactNode } from 'react'
import { Link, usePage } from '@inertiajs/react'
import {
  AlertTriangle,
  BookOpen,
  CheckCircle2,
  FileText,
  Inbox,
  Info,
  LayoutDashboard,
  LayoutTemplate,
  Settings,
  UserCog,
  Users,
  type LucideIcon,
} from 'lucide-react'
import { Reveal } from '@/components/Reveal'
import {
  depositsHref,
  issuesHref,
  peopleHref,
  settingsHref,
  submissionsHref,
  usersHref,
  type AdminChrome,
  type AdminShared,
} from '@/lib/admin'
import { contentHref } from '@/lib/content'

/**
 * The admin chrome. Same product as the public site, and it must look it: Newsreader
 * headings, Inter UI, the same brand/ink tokens, the same pill buttons, Lucide icons.
 *
 * NO framer `initial` variant wraps any content here. <Reveal> is the SSR-safe primitive —
 * it renders VISIBLE on the server and only hides what is below the fold, on the client,
 * after mount. An `initial={{ opacity: 0 }}` on a wrapper would serialise opacity:0 into
 * the server HTML and ship the whole admin invisible, which looks perfectly fine in a
 * browser and is how this bug survives review.
 */

type Tab = {
  label: string
  href: string
  icon: LucideIcon
  /** Rendered only when true. A tab nobody may use is ABSENT, never disabled-and-taunting. */
  show: boolean
  /** The path prefix that marks this tab current. Defaults to `href`. */
  match?: string
}

export function AdminShell({
  chrome,
  eyebrow,
  title,
  description,
  actions,
  children,
}: {
  chrome: AdminChrome
  eyebrow: string
  title: string
  description?: string
  actions?: ReactNode
  children: ReactNode
}) {
  const { journal, can } = chrome
  const { url } = usePage()
  const path = url.split('?')[0]

  const tabs: Tab[] = [
    { label: 'Overview', href: '/admin', icon: LayoutDashboard, show: true },

    // The daily editorial work: manuscripts in review. Shown to anyone who may read the
    // journal's submission queue. A submission detail page (/admin/submissions/{id}) does not
    // highlight this tab — the same limitation the Issues tab has for /admin/issues/{id} — and
    // the match stays on the journal-scoped queue path.
    {
      label: 'Submissions',
      href: submissionsHref(journal.id),
      icon: Inbox,
      show: can.viewAllSubmissions,
    },
    {
      label: 'Issues',
      href: issuesHref(journal.id),
      icon: BookOpen,
      // A CONTINUOUS journal has no issues. The section is not rendered at all — not a
      // disabled tab, which would teach editors that the publication model is a setting
      // they could turn on rather than a fact about the journal.
      show: journal.publicationModel === 'issue_based' && can.manageIssues,
    },
    {
      label: 'DOI registration',
      href: depositsHref(journal.id),
      icon: FileText,
      show: can.depositDois,
    },
    { label: 'Settings', href: settingsHref(journal.id), icon: Settings, show: can.manageSettings },
    { label: 'People', href: usersHref(journal.id), icon: Users, show: can.manageUsers },

    /*
     * SITE-WIDE, and NOT the same tab as "People" above. The two sit side by side because
     * they are two different jobs and both are real:
     *
     *   People   — roles ON THIS JOURNAL. Per-journal, a policy question.
     *   Accounts — the person exists at all: create, email, password, deactivate, site admin.
     *
     * A person is not of a journal, so "create a user" was never expressible on the
     * per-journal screen — which is why, before this tab, there was no way to make one
     * outside a seeder.
     */
    {
      label: 'Accounts',
      href: peopleHref.accounts,
      icon: UserCog,
      show: can.manageAccounts,
      // Current for the roles screen too — both live under the People shell.
      match: '/admin/users',
    },

    // SITE-WIDE, not this journal's. The tab sits alongside the journal tabs because that is
    // where an editor looks for it, but everything behind it — the footer, the privacy policy,
    // the navigation — belongs to the site, and the gate that guards it is not journal-scoped.
    {
      label: 'Content',
      href: contentHref.settings,
      icon: LayoutTemplate,
      show: can.manageSiteContent,
      // Current for every CMS screen, not only the one the tab happens to land on.
      match: '/admin/content',
    },
  ]

  return (
    <>
      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page pt-10">
          <Reveal className="flex flex-wrap items-end justify-between gap-6">
            <div className="min-w-0">
              <p className="eyebrow">{eyebrow}</p>
              <h1 className="mt-3 font-serif text-3xl sm:text-4xl">{title}</h1>
              {description && <p className="mt-3 max-w-prose text-ink-600">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap items-center gap-3">{actions}</div>}
          </Reveal>

          <nav aria-label="Editorial admin" className="mt-8 flex flex-wrap gap-1 overflow-x-auto">
            {tabs
              .filter((t) => t.show)
              .map((tab) => {
                const active =
                  tab.href === '/admin'
                    ? path === '/admin'
                    : path.startsWith(tab.match ?? tab.href)
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

/* ------------------------------ Journal switcher --------------------------- */

/** Only rendered when this person actually works on more than one journal. */
export function JournalSwitcher({ chrome }: { chrome: AdminChrome }) {
  if (chrome.journals.length <= 1) return null

  return (
    <div className="flex items-center gap-2">
      <label htmlFor="journal-switch" className="text-sm font-medium text-ink-700">
        Journal
      </label>
      <select
        id="journal-switch"
        value={chrome.journal.id}
        onChange={(e) => {
          window.location.href = `/admin/journals/${e.target.value}/issues`
        }}
        className="h-11 cursor-pointer rounded-full border border-ink-300 bg-white px-4 text-sm
                   text-ink-900 transition-colors duration-200 hover:border-ink-900 focus:border-brand-600"
      >
        {chrome.journals.map((j) => (
          <option key={j.id} value={j.id}>
            {j.abbreviation ?? j.title}
          </option>
        ))}
      </select>
    </div>
  )
}

/* ---------------------------------- Flash ---------------------------------- */

/**
 * The flash band, including the publish gate's COMPLETE refusal list.
 *
 * `publishErrors` carries every pre-flight failure at once. Rendering the first and hiding
 * the rest is the exact failure the actions were written to prevent: an editor fixing a
 * publication one error at a time, against a deadline, is how a half-complete article goes
 * live.
 */
export function Flash() {
  const { flash } = usePage<AdminShared>().props

  if (!flash?.success && !flash?.error && !flash?.publishErrors?.length) return null

  return (
    <div className="mb-8 space-y-4">
      {flash.success && (
        <Banner tone="success" icon={CheckCircle2} title={flash.success} />
      )}

      {flash.error && <Banner tone="danger" icon={AlertTriangle} title={flash.error} />}

      {flash.publishErrors && flash.publishErrors.length > 0 && (
        <div
          role="alert"
          className="rounded-xl border border-danger-600/40 bg-danger-50 p-5"
        >
          <div className="flex items-start gap-3">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-danger-700" aria-hidden="true" />
            <div className="min-w-0">
              <p className="font-serif text-lg text-ink-900">
                Not published — {flash.publishErrors.length}{' '}
                {flash.publishErrors.length === 1 ? 'problem' : 'problems'} must be fixed first
              </p>
              <p className="mt-1 text-sm text-ink-700">
                Every one of them, so you can fix them in one pass. Nothing was changed.
              </p>
              <ul className="mt-3 list-disc space-y-1.5 pl-5 text-sm text-ink-800">
                {flash.publishErrors.map((message, i) => (
                  <li key={`${i}-${message}`}>{message}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

export function Banner({
  tone,
  icon: Icon,
  title,
  children,
}: {
  tone: 'success' | 'danger' | 'gold' | 'info'
  icon: LucideIcon
  title: string
  children?: ReactNode
}) {
  // success-*/danger-* are design-system tokens. Stock emerald/red sit outside it and could
  // never be restyled centrally.
  const tones: Record<string, string> = {
    success: 'border-success-600/40 bg-success-50 text-success-800',
    danger: 'border-danger-600/40 bg-danger-50 text-danger-800',
    gold: 'border-gold-500/40 bg-gold-50 text-gold-700',
    info: 'border-brand-300 bg-brand-50 text-brand-800',
  }

  return (
    <div role="status" className={`flex items-start gap-3 rounded-xl border p-4 ${tones[tone]}`}>
      <Icon className="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
      <div className="min-w-0 text-sm">
        <p className="font-semibold text-ink-900">{title}</p>
        {children && <div className="mt-1 text-ink-700">{children}</div>}
      </div>
    </div>
  )
}

/* --------------------------------- Layout ---------------------------------- */

export function Panel({
  title,
  description,
  actions,
  children,
}: {
  title: string
  description?: string
  actions?: ReactNode
  children: ReactNode
}) {
  return (
    <section className="card p-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h2 className="font-serif text-xl text-ink-900">{title}</h2>
          {description && <p className="mt-1 max-w-prose text-sm text-ink-600">{description}</p>}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
      </div>
      <div className="mt-6">{children}</div>
    </section>
  )
}

export function Field({
  label,
  htmlFor,
  hint,
  error,
  children,
  className,
}: {
  label: string
  htmlFor: string
  hint?: ReactNode
  error?: string
  children: ReactNode
  className?: string
}) {
  return (
    <div className={className}>
      <label htmlFor={htmlFor} className="block text-sm font-medium text-ink-800">
        {label}
      </label>
      {children}
      {/* The error carries an icon as well as a colour — colour is never the only carrier. */}
      {error ? (
        <p id={`${htmlFor}-error`} className="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">
          <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          {error}
        </p>
      ) : (
        hint && (
          <p id={`${htmlFor}-hint`} className="mt-1.5 flex items-start gap-1.5 text-sm text-ink-600">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
            <span>{hint}</span>
          </p>
        )
      )}
    </div>
  )
}

/** The house text input, squared off — a pill is wrong for a dense editorial form. */
export const INPUT =
  'mt-1.5 w-full rounded-lg border border-ink-300 bg-white px-3.5 py-2.5 text-sm text-ink-900 ' +
  'placeholder:text-ink-500 transition-colors duration-200 hover:border-ink-400 focus:border-brand-600 ' +
  'disabled:cursor-not-allowed disabled:bg-ink-50 disabled:text-ink-500'

export const SELECT = `${INPUT} cursor-pointer`

export function EmptyState({
  icon: Icon,
  title,
  body,
  children,
}: {
  icon: LucideIcon
  title: string
  body: string
  children?: ReactNode
}) {
  return (
    <div className="rounded-xl border border-dashed border-ink-300 p-10 text-center">
      <span className="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-500">
        <Icon className="h-6 w-6" aria-hidden="true" />
      </span>
      <p className="mt-4 font-serif text-lg text-ink-900">{title}</p>
      <p className="mx-auto mt-2 max-w-prose text-sm text-ink-600">{body}</p>
      {children && <div className="mt-6">{children}</div>}
    </div>
  )
}

/** Spinner only — no decorative animation anywhere in this system. */
export function Spinner({ className = 'h-4 w-4' }: { className?: string }) {
  return (
    <svg className={`animate-spin ${className}`} viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" className="opacity-25" />
      <path
        d="M22 12a10 10 0 0 1-10 10"
        stroke="currentColor"
        strokeWidth="3"
        strokeLinecap="round"
      />
    </svg>
  )
}
