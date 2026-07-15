import { Head, Link } from '@inertiajs/react'
import {
  AlertTriangle,
  BookOpen,
  FileText,
  Inbox,
  Info,
  LayoutTemplate,
  PencilLine,
  Plus,
  Settings,
  Users,
  XCircle,
} from 'lucide-react'
import { Flash } from '@/components/admin/Shell'
import { ArticleBadge } from '@/components/admin/Status'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate, formatNumber } from '@/lib/format'
import {
  depositsHref,
  editArticleHref,
  issuesHref,
  newArticleHref,
  settingsHref,
  usersHref,
  type AdminAbilities,
  type ArticleStatus,
  type PublicationModel,
} from '@/lib/admin'
import { contentHref } from '@/lib/content'
import type { Meta } from '@/lib/props'

type JournalOverview = {
  id: number
  slug: string
  title: string
  abbreviation: string | null
  publicationModel: PublicationModel
  usesIssues: boolean
  counts: {
    draftArticles: number
    publishedArticles: number
    openSubmissions: number
    failedDeposits: number
    draftIssues: number
  }
  doi: {
    /** NULL until Crossref issues one. Said in words below — not left as an empty cell. */
    prefix: string | null
    canMintDois: boolean
    suffixPattern: string
  }
  recentArticles: {
    id: number
    title: string
    status: ArticleStatus
    statusLabel: string
    isPublished: boolean
    updatedAt: string | null
  }[]
  can: AdminAbilities
}

type Props = {
  journals: JournalOverview[]
  /** SITE-WIDE, so it hangs off the page rather than off a journal card. */
  canManageSiteContent: boolean
  meta: Meta
}

export default function AdminDashboard({ journals, canManageSiteContent, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-12">
          <Reveal className="flex flex-wrap items-end justify-between gap-6">
            <div className="min-w-0">
              <p className="eyebrow">Editorial admin</p>
              <h1 className="mt-3 font-serif text-4xl sm:text-5xl">Journals</h1>
              <p className="mt-4 max-w-prose text-ink-600">
                What is in production, what is waiting to be published, and what Crossref has
                actually registered.
              </p>
            </div>

            {/* Not a journal's tab. The footer, the policy pages and the navigation belong to
                the site, and the gate that guards them is not journal-scoped. */}
            {canManageSiteContent && (
              <Link href={contentHref.settings} className="btn-secondary">
                <LayoutTemplate className="h-4 w-4" aria-hidden="true" />
                Site content
              </Link>
            )}
          </Reveal>
        </div>
      </header>

      <div className="container-page py-10">
        <Flash />

        <RevealGroup className="grid gap-6 lg:grid-cols-2" stagger={0.08}>
          {journals.map((journal) => (
            <RevealItem key={journal.id}>
              <JournalCard journal={journal} />
            </RevealItem>
          ))}
        </RevealGroup>
      </div>
    </>
  )
}

function JournalCard({ journal }: { journal: JournalOverview }) {
  const { counts, doi, can } = journal

  return (
    /* Hover lifts with translateY, never scale — scale nudges the neighbouring card. */
    <div className="card h-full p-6 transition-transform duration-200 hover:-translate-y-1 hover:shadow-lift">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="eyebrow">{journal.abbreviation ?? 'Journal'}</p>
          <h2 className="mt-2 font-serif text-2xl leading-snug text-ink-900">{journal.title}</h2>
          <p className="mt-1 text-sm text-ink-600">
            {journal.usesIssues ? 'Volumes and issues' : 'Continuous publication'}
          </p>
        </div>
      </div>

      <dl className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Stat icon={PencilLine} label="Draft articles" value={counts.draftArticles} />
        <Stat icon={FileText} label="Published" value={counts.publishedArticles} />
        <Stat icon={Inbox} label="Open submissions" value={counts.openSubmissions} />
        <Stat
          icon={XCircle}
          label="Failed deposits"
          value={counts.failedDeposits}
          tone={counts.failedDeposits > 0 ? 'danger' : 'neutral'}
        />
      </dl>

      {/* THE DELIBERATE NULL, said plainly. Not an error, not a misconfiguration: the true
          state of a journal Crossref has not registered yet. */}
      <div
        className={`mt-6 flex items-start gap-3 rounded-lg border p-4 text-sm ${
          doi.canMintDois
            ? 'border-brand-300 bg-brand-50'
            : 'border-gold-500/40 bg-gold-50'
        }`}
      >
        {doi.canMintDois ? (
          <Info className="mt-0.5 h-4 w-4 shrink-0 text-brand-700" aria-hidden="true" />
        ) : (
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-gold-700" aria-hidden="true" />
        )}
        <p className="min-w-0 text-ink-800">
          {doi.canMintDois ? (
            <>
              DOIs are minted under{' '}
              <span className="font-mono text-xs text-ink-900">{doi.prefix}</span> as{' '}
              <span className="font-mono text-xs text-ink-900">{doi.suffixPattern}</span>.
            </>
          ) : (
            <>
              <span className="font-semibold">
                Crossref has not issued a prefix; DOIs cannot be registered yet.
              </span>{' '}
              Articles still publish and their URLs are still permanent — the DOI is deposited
              once a prefix exists.
            </>
          )}
        </p>
      </div>

      {journal.recentArticles.length > 0 && (
        <div className="mt-6">
          <h3 className="text-xs font-semibold uppercase tracking-wider text-ink-500">
            Recently edited
          </h3>
          <ul className="mt-3 space-y-2">
            {journal.recentArticles.map((article) => (
              <li key={article.id}>
                <Link
                  href={editArticleHref(article.id)}
                  className="flex cursor-pointer items-center gap-3 rounded-lg border border-ink-200 p-3
                             transition-colors duration-200 hover:border-ink-300 hover:bg-ink-50"
                >
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-ink-900">
                      {article.title}
                    </span>
                    <span className="mt-0.5 block text-xs text-ink-500">
                      updated {formatDate(article.updatedAt)}
                    </span>
                  </span>
                  <ArticleBadge status={article.status} label={article.statusLabel} />
                </Link>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="mt-6 flex flex-wrap gap-2">
        {/* The Issues section is ABSENT for a continuous journal — not a disabled tab. */}
        {journal.usesIssues && can.manageIssues && (
          <Link href={issuesHref(journal.id)} className="btn-secondary">
            <BookOpen className="h-4 w-4" aria-hidden="true" />
            Issues
            {counts.draftIssues > 0 && (
              <span className="rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-700">
                {formatNumber(counts.draftIssues)} draft
              </span>
            )}
          </Link>
        )}

        {can.manageArticles && (
          <Link href={newArticleHref(journal.id)} className="btn-secondary">
            <Plus className="h-4 w-4" aria-hidden="true" />
            New article
          </Link>
        )}

        {can.depositDois && (
          <Link href={depositsHref(journal.id)} className="btn-secondary">
            <FileText className="h-4 w-4" aria-hidden="true" />
            DOI registration
          </Link>
        )}

        {can.manageSettings && (
          <Link href={settingsHref(journal.id)} className="btn-ghost">
            <Settings className="h-4 w-4" aria-hidden="true" />
            Settings
          </Link>
        )}

        {can.manageUsers && (
          <Link href={usersHref(journal.id)} className="btn-ghost">
            <Users className="h-4 w-4" aria-hidden="true" />
            People
          </Link>
        )}
      </div>
    </div>
  )
}

function Stat({
  icon: Icon,
  label,
  value,
  tone = 'neutral',
}: {
  icon: typeof Inbox
  label: string
  value: number
  tone?: 'neutral' | 'danger'
}) {
  return (
    <div className="rounded-lg border border-ink-200 p-3">
      <dt className="flex items-center gap-1.5 text-xs text-ink-600">
        <Icon
          className={`h-3.5 w-3.5 ${tone === 'danger' && value > 0 ? 'text-danger-700' : 'text-ink-500'}`}
          aria-hidden="true"
        />
        {label}
      </dt>
      <dd
        className={`mt-1 font-serif text-2xl ${
          tone === 'danger' && value > 0 ? 'text-danger-800' : 'text-ink-900'
        }`}
      >
        {formatNumber(value)}
      </dd>
    </div>
  )
}
