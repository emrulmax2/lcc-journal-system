import { Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { ArrowUpRight, BookOpen, CalendarClock, CheckCircle2, Quote, Users, XCircle } from 'lucide-react'
import { CardImage } from './ImageWithFallback'
import type { ArticleThumbnail, IssueCard as IssueCardData, MediaImage, PrintIssue } from '@/lib/props'
import type { PhotoKey } from '@/lib/images'
import { formatDate, formatNumber } from '@/lib/format'
import { fadeUp, hoverLift } from '@/lib/motion'

/**
 * The card prop contracts, as the backend now serialises them. They live here, next to the
 * components that consume them, and the pages import them from here — so a change to a
 * card's needs is a change in one file rather than a hunt through six.
 *
 * The metrics are NULLABLE. A journal that has just launched has no impact factor, no
 * CiteScore and no acceptance rate. Every one of them renders an em-dash when absent, and
 * never "0.0": a fabricated zero is a *wrong* impact factor, published on a page that
 * researchers use to judge where to send their work, and that is worse than an honest blank.
 *
 * Every card carries BOTH `image` (a real asset we own, with its own alt text) and `photo`
 * (the legacy Unsplash key). <CardImage> resolves image -> photo -> a neutral placeholder.
 * It never substitutes a random photograph for a missing one.
 */

export type JournalCardData = {
  slug: string
  title: string
  field: string
  description: string
  /** The uploaded cover, mapped from JournalResource's `coverImage` by toJournalCard(). */
  image: MediaImage | null
  photo: PhotoKey | null
  impactFactor: number | null
  citeScore: number | null
  acceptanceRate: number | null
  medianDaysToDecision: number | null
  articles: number
  editors: number
  openAccess: boolean
}

export type ArticleCardData = {
  slug: string
  title: string
  authors: string[]
  journal: string
  journalSlug: string
  type: string
  date: string | null
  /** The printed issue, where known — shown as "Vol 5, No 1". */
  printIssue?: PrintIssue | null
  doi: string | null
  views: number
  /** NULL until the article has a DOI — the card then shows no citation figure at all. */
  citations: number | null
  photo: PhotoKey | null
  thumbnail?: ArticleThumbnail | null
  abstract: string
  keywords: string[]
}

export type NewsCardData = {
  slug: string
  title: string
  category: string
  date: string | null
  excerpt: string
  /** Resolved by NewsController. This card used to be a dead anchor pointing at "#". */
  url: string
  image: MediaImage | null
  photo: PhotoKey | null
}

export type TopicCardData = {
  slug: string
  title: string
  /** Goes to the call for papers itself. Every one of these used to link to /journals. */
  url: string
  deadline: string | null
  editors: number
  /** null on the homepage, which sends a leaner topic than the Research Topics index. */
  isOpen: boolean | null
  hasClosed: boolean | null
  image: MediaImage | null
  photo: PhotoKey | null
}

/** A missing metric is a blank, never a zero. */
function metric(value: number | null | undefined, decimals = 0): string {
  return value == null ? '—' : formatNumber(value, decimals)
}

export function OpenAccessBadge() {
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2.5 py-1 text-[11px] font-semibold text-gold-700">
      <svg viewBox="0 0 24 24" className="h-3 w-3" aria-hidden="true" fill="currentColor">
        <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H9V7a3 3 0 0 1 6 0h2a5 5 0 0 0-5-5Z" />
      </svg>
      Open access
    </span>
  )
}

export function JournalCard({ journal }: { journal: JournalCardData }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      {/* The journal LANDING page — aims and scope, ISSN, editor, contact. This used to go
          to /articles?journal=…, a filtered list, which is why aims_and_scope and the ISSN
          were columns that nothing on the site ever read. */}
      <Link
        href={`/journals/${journal.slug}`}
        className="card group flex h-full cursor-pointer flex-col overflow-hidden hover:shadow-lift"
      >
        <div className="aspect-[16/9] w-full overflow-hidden">
          <CardImage
            image={journal.image}
            photo={journal.photo}
            alt=""
            width={800}
            height={450}
            className="transition-transform duration-500 group-hover:scale-[1.04]"
          />
        </div>

        <div className="flex flex-1 flex-col p-5">
          <div className="flex items-center justify-between gap-2">
            <span className="text-xs font-medium text-brand-700">{journal.field}</span>
            {journal.openAccess && <OpenAccessBadge />}
          </div>

          <h3 className="mt-2 font-serif text-xl leading-snug text-ink-900 transition-colors duration-200 group-hover:text-brand-800">
            {journal.title}
          </h3>
          <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{journal.description}</p>

          <dl className="mt-4 grid grid-cols-3 gap-2 border-t border-ink-200 pt-4">
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500">Impact</dt>
              <dd className="text-sm font-semibold text-ink-900">
                {metric(journal.impactFactor, 1)}
              </dd>
            </div>
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500">Articles</dt>
              <dd className="text-sm font-semibold text-ink-900">
                {formatNumber(journal.articles)}
              </dd>
            </div>
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500">Editors</dt>
              <dd className="text-sm font-semibold text-ink-900">
                {formatNumber(journal.editors)}
              </dd>
            </div>
          </dl>
        </div>
      </Link>
    </motion.article>
  )
}

/**
 * The journal's name set the way the masthead logo sets it:
 *
 *     Journal of                      (small)
 *     Contemporary Development
 *     and Management Studies          ("and" in italic)
 *
 * behind a thin teal rule, echoing the logo's divider. "&" in the stored title is set as an
 * italic "and", as in the logo. A title that is not "Journal of …" is set plainly.
 */
export function JournalNameLockup({ title }: { title: string }) {
  const match = /^Journal of\s+(.+?)\s+(?:&|and)\s+(.+)$/i.exec(title.trim())

  return (
    <span
      className="block border-l-2 border-brand-600/40 pl-3 font-serif leading-[1.2] text-ink-900 transition-colors
                 duration-200 group-hover:border-brand-700 group-hover:text-brand-800"
    >
      {match ? (
        <>
          <span className="block text-xs text-ink-500">Journal of</span>
          <span className="mt-0.5 block text-xl font-medium">{match[1]}</span>
          <span className="block text-xl font-medium">
            <span className="italic">and</span> {match[2]}
          </span>
        </>
      ) : (
        <span className="block text-xl font-medium">{title}</span>
      )}
    </span>
  )
}

/** "Volume 5 · Number 1 · Summer 2017" — how the issue is printed on its cover. */
export function issueLine(issue: Pick<IssueCardData, 'volume' | 'number' | 'season'>): string {
  return [`Volume ${issue.volume}`, `Number ${issue.number}`, issue.season].filter(Boolean).join(' · ')
}

/**
 * The printed cover, shown WHOLE — covers are portrait and carry the volume line in small
 * type, so cropping one into a landscape frame would cut off the very thing that tells
 * issues apart. With no cover it falls back to the journal's own image, never a random one.
 */
export function IssueCoverFrame({ issue, className = '' }: { issue: IssueCardData; className?: string }) {
  return (
    <div
      className={`relative w-full overflow-hidden bg-gradient-to-br from-ink-100 via-ink-50 to-brand-50 ${className}`}
    >
      {issue.cover ? (
        <img
          src={issue.cover.url}
          alt=""
          width={364}
          height={515}
          loading="lazy"
          decoding="async"
          className="absolute left-1/2 top-1/2 h-[84%] w-auto -translate-x-1/2 -translate-y-1/2 rounded-sm
                     shadow-lg ring-1 ring-black/5 transition-transform duration-500 group-hover:scale-[1.04]"
        />
      ) : (
        <CardImage
          image={issue.journal.image}
          photo={issue.journal.photo}
          alt=""
          width={800}
          height={450}
          className="transition-transform duration-500 group-hover:scale-[1.04]"
        />
      )}
    </div>
  )
}

/**
 * One published ISSUE. The title is always the journal's name; the line beneath it says
 * which issue — the way the issue is printed and cited.
 *
 * `Articles` counts THIS issue. Impact and Editors are the journal's, and an impact factor
 * the journal does not have is an em-dash, never a number.
 */
export function IssueCard({ issue }: { issue: IssueCardData }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        href={issue.url}
        className="card group flex h-full cursor-pointer flex-col overflow-hidden hover:shadow-lift"
      >
        <IssueCoverFrame issue={issue} className="aspect-[16/9]" />

        <div className="flex flex-1 flex-col p-5">
          {issue.journal.openAccess && (
            <div className="flex justify-end">
              <OpenAccessBadge />
            </div>
          )}

          <h3 className="mt-2">
            <JournalNameLockup title={issue.journal.title} />
          </h3>
          <p className="mt-3 flex-1 text-sm font-medium text-brand-700">{issueLine(issue)}</p>

          <dl className="mt-4 grid grid-cols-3 gap-2 border-t border-ink-200 pt-4">
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500" title="The journal's impact factor">
                Impact
              </dt>
              <dd className="text-sm font-semibold text-ink-900">
                {metric(issue.journal.impactFactor, 1)}
              </dd>
            </div>
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500">Articles</dt>
              <dd className="text-sm font-semibold text-ink-900">{formatNumber(issue.articles)}</dd>
            </div>
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500" title="The journal's editors">
                Editors
              </dt>
              <dd className="text-sm font-semibold text-ink-900">{formatNumber(issue.journal.editors)}</dd>
            </div>
          </dl>
        </div>
      </Link>
    </motion.article>
  )
}

export function ArticleCard({ article }: { article: ArticleCardData }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        href={`/articles/${article.slug}`}
        className="card group flex h-full cursor-pointer gap-4 overflow-hidden p-4 hover:shadow-lift sm:p-5"
      >
        <div className="hidden h-28 w-28 shrink-0 overflow-hidden rounded-lg border border-ink-200 bg-ink-50 sm:block">
          {article.thumbnail ? (
            // The paper itself: its first page, cropped to the top so the title and authors
            // show — or, failing that, the cover of the issue it appeared in. Decorative
            // (alt=""): the title is right beside it.
            <img
              src={article.thumbnail.url}
              alt=""
              width={224}
              height={224}
              loading="lazy"
              decoding="async"
              className="h-full w-full object-cover object-top transition-transform duration-500 group-hover:scale-[1.06]"
            />
          ) : (
            <CardImage
              image={null}
              photo={article.photo}
              alt=""
              width={320}
              height={320}
              className="transition-transform duration-500 group-hover:scale-[1.06]"
            />
          )}
        </div>

        {/* w-0 + flex-1: the column still fills the card, but contributes NO intrinsic
            width. Without it the one-line (truncated) author list counted at its full
            length, and a long byline pushed whole page columns wider than the screen. */}
        <div className="w-0 min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2 text-xs">
            <span className="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-800">
              {article.type}
            </span>
            {article.printIssue && (
              <span className="font-medium text-ink-700">
                Vol {article.printIssue.volume}, No {article.printIssue.number}
              </span>
            )}
            <span className="text-ink-500">{formatDate(article.date)}</span>
          </div>

          <h3 className="mt-2 font-serif text-lg leading-snug text-ink-900 transition-colors duration-200 group-hover:text-brand-800">
            {article.title}
          </h3>

          <p className="mt-1.5 truncate text-sm text-ink-600">
            {article.authors.join(', ')} · {article.journal}
          </p>

          <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-ink-600">
            <span className="inline-flex items-center gap-1.5">
              <Users className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
              {formatNumber(article.views)} views
            </span>
            {/* Not tracked until the article has a DOI. "0 citations" would claim nobody
                has cited it, which we do not know — so the card simply leaves it out. */}
            {article.citations !== null && (
              <span className="inline-flex items-center gap-1.5">
                <Quote className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
                {formatNumber(article.citations)} citations
              </span>
            )}
          </div>
        </div>
      </Link>
    </motion.article>
  )
}

export function NewsCard({ item }: { item: NewsCardData }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        href={item.url}
        className="card group flex h-full cursor-pointer flex-col overflow-hidden hover:shadow-lift"
      >
        <div className="aspect-[16/10] w-full overflow-hidden">
          <CardImage
            image={item.image}
            photo={item.photo}
            alt=""
            width={700}
            height={440}
            className="transition-transform duration-500 group-hover:scale-[1.04]"
          />
        </div>
        <div className="flex flex-1 flex-col p-5">
          <div className="flex items-center gap-2 text-xs">
            <span className="font-semibold uppercase tracking-wide text-brand-700">
              {item.category}
            </span>
            <span className="text-ink-500">{formatDate(item.date)}</span>
          </div>
          <h3 className="mt-2 font-serif text-lg leading-snug text-ink-900 transition-colors duration-200 group-hover:text-brand-800">
            {item.title}
          </h3>
          <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{item.excerpt}</p>
          <span className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-800">
            Read the story
            <ArrowUpRight
              className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
              aria-hidden="true"
            />
          </span>
        </div>
      </Link>
    </motion.article>
  )
}

/**
 * A call for papers.
 *
 * The old card printed `{topic.articles} articles` — a field the controller has never
 * sent, so it rendered the literal string "undefined articles" on the homepage. A Research
 * Topic is a call for papers, not a container of articles; there is nothing to count. What
 * IS real is the editor count and the deadline, and the deadline is the single most
 * important fact here, so it gets its own line and an icon rather than a colour.
 */
export function TopicCard({ topic }: { topic: TopicCardData }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        href={topic.url}
        className="card group relative flex h-full min-h-[260px] cursor-pointer flex-col justify-end overflow-hidden p-6 text-white hover:shadow-lift"
      >
        <span className="absolute inset-0">
          <CardImage
            image={topic.image}
            photo={topic.photo}
            alt=""
            width={700}
            height={500}
            className="transition-transform duration-700 group-hover:scale-[1.06]"
          />
          {/* Scrim keeps text at 4.5:1 over any photo — and over the placeholder. */}
          <span
            aria-hidden="true"
            className="absolute inset-0 bg-gradient-to-t from-ink-950/90 via-ink-900/70 to-ink-900/40"
          />
        </span>

        <span className="relative">
          <span className="flex flex-wrap items-center gap-2">
            <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold backdrop-blur">
              <BookOpen className="h-3 w-3" aria-hidden="true" />
              Research Topic
            </span>
            <TopicStatus isOpen={topic.isOpen} hasClosed={topic.hasClosed} onDark />
          </span>

          <h3 className="mt-3 font-serif text-xl leading-snug text-white">{topic.title}</h3>

          {/* ink-200/300 on this scrim, never ink-500: the design system's small-meta token
              is only 4.8:1 on WHITE. */}
          <p className="mt-2 text-sm text-ink-200">
            {formatNumber(topic.editors)} {topic.editors === 1 ? 'topic editor' : 'topic editors'}
          </p>

          <p className="mt-1 inline-flex items-center gap-1.5 text-xs text-ink-200">
            <CalendarClock className="h-3.5 w-3.5" aria-hidden="true" />
            {topic.deadline
              ? `Abstract deadline ${formatDate(topic.deadline)}`
              : 'No abstract deadline set'}
          </p>
        </span>
      </Link>
    </motion.article>
  )
}

/**
 * Open / closed, carried by an ICON AND A WORD, never by colour alone.
 *
 * Renders nothing when the caller does not know — the homepage controller sends a leaner
 * topic with no status on it, and a card that guesses "Open" would be asserting that a
 * closed call is still accepting submissions.
 */
export function TopicStatus({
  isOpen,
  hasClosed,
  onDark = false,
}: {
  isOpen: boolean | null
  hasClosed: boolean | null
  onDark?: boolean
}) {
  if (isOpen === null && hasClosed === null) return null

  const base =
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold'

  if (isOpen) {
    return (
      <span
        className={`${base} ${
          onDark ? 'bg-white/15 text-white backdrop-blur' : 'bg-success-50 text-success-800'
        }`}
      >
        <CheckCircle2 className="h-3 w-3" aria-hidden="true" />
        Open for submissions
      </span>
    )
  }

  return (
    <span
      className={`${base} ${
        onDark ? 'bg-ink-950/60 text-ink-200 backdrop-blur' : 'bg-ink-100 text-ink-700'
      }`}
    >
      <XCircle className="h-3 w-3" aria-hidden="true" />
      {hasClosed ? 'Deadline passed' : 'Closed'}
    </span>
  )
}
