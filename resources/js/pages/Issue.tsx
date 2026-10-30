import { Head, Link } from '@inertiajs/react'
import { ArrowLeft, ArrowRight, ChevronRight, FileDown, FileText } from 'lucide-react'
import { OpenAccessBadge } from '@/components/Cards'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatLongDate } from '@/lib/format'
import type { Meta } from '@/lib/props'

type Neighbour = { label: string; season: string | null; url: string } | null

type Props = {
  journal: {
    slug: string
    title: string
    abbreviation: string | null
    openAccess: boolean
  }
  issue: {
    volume: number
    number: number
    /** "Volume 5, Number 1" */
    label: string
    season: string | null
    publicationDate: string | null
    cover: { url: string; alt: string | null } | null
    /** The whole printed issue; null when no full-issue PDF has been uploaded. */
    fullPdfUrl: string | null
  }
  articles: {
    slug: string
    title: string
    section: string | null
    /** Display names in byline order. A corporate author arrives as one entry. */
    authors: string[]
    pageRange: string | null
    pdfUrl: string | null
  }[]
  previous: Neighbour
  next: Neighbour
  meta: Meta
}

/**
 * One printed issue: its cover, its table of contents, and the whole issue as a PDF.
 *
 * Articles are listed in the issue's running order (their position in print), which is
 * also the order of their page numbers — never re-sorted by date or title.
 */
export default function Issue({ journal, issue, articles, previous, next, meta }: Props) {
  const heading = [issue.label, issue.season].filter(Boolean).join(' · ')

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-10 sm:py-14">
          <nav aria-label="Breadcrumb">
            <ol className="flex flex-wrap items-center gap-1.5 text-sm text-ink-600">
              <li>
                <Link href="/journals" className="link-underline">
                  Journals
                </Link>
              </li>
              <ChevronRight className="h-3.5 w-3.5 text-ink-400" aria-hidden="true" />
              <li>
                <Link href={`/journals/${journal.slug}`} className="link-underline">
                  {journal.abbreviation ?? journal.title}
                </Link>
              </li>
              <ChevronRight className="h-3.5 w-3.5 text-ink-400" aria-hidden="true" />
              <li aria-current="page" className="text-ink-900">
                {issue.label}
              </li>
            </ol>
          </nav>

          <Reveal className="mt-8 flex flex-col gap-8 sm:flex-row sm:items-start">
            {issue.cover && (
              <img
                src={issue.cover.url}
                alt={issue.cover.alt ?? `Cover of ${journal.title}, ${heading}`}
                width={208}
                height={294}
                className="w-40 shrink-0 rounded-md border border-ink-200 bg-white shadow-md sm:w-52"
              />
            )}

            <div className="min-w-0 flex-1">
              <p className="eyebrow">{journal.title}</p>
              <h1 className="mt-3 font-serif text-3xl leading-tight text-ink-900 sm:text-4xl lg:text-5xl">
                {heading}
              </h1>

              <p className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-ink-600">
                <span>
                  {articles.length} {articles.length === 1 ? 'article' : 'articles'}
                </span>
                {issue.publicationDate && (
                  <>
                    <span aria-hidden="true">·</span>
                    <span>Published {formatLongDate(issue.publicationDate)}</span>
                  </>
                )}
                {journal.openAccess && <OpenAccessBadge />}
              </p>

              <div className="mt-6 flex flex-wrap gap-3">
                {issue.fullPdfUrl && (
                  // A file, not a page: a plain anchor, so the browser opens the PDF.
                  <a href={issue.fullPdfUrl} className="btn-primary">
                    <FileDown className="h-4 w-4" aria-hidden="true" />
                    Full issue PDF
                  </a>
                )}
                <Link href={`/journals/${journal.slug}`} className="btn-secondary">
                  About the journal
                </Link>
              </div>
            </div>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        <h2 className="font-serif text-2xl">In this issue</h2>

        {articles.length === 0 ? (
          <p className="mt-6 text-ink-600">
            The articles in this issue are available in the full issue PDF.
          </p>
        ) : (
          <RevealGroup as="ol" className="mt-4 max-w-4xl divide-y divide-ink-200" stagger={0.04}>
            {articles.map((a) => (
              <RevealItem as="li" key={a.slug} className="py-6">
                {a.section && <p className="text-xs font-semibold uppercase tracking-wide text-brand-700">{a.section}</p>}
                <Link
                  href={`/articles/${a.slug}`}
                  className="mt-1.5 block font-serif text-xl leading-snug text-ink-900 transition-colors duration-200 hover:text-brand-800"
                >
                  {a.title}
                </Link>
                {a.authors.length > 0 && <p className="mt-1.5 text-sm text-ink-700">{a.authors.join(', ')}</p>}
                <p className="mt-2 flex flex-wrap items-center gap-x-3 text-sm text-ink-500">
                  {a.pageRange && <span>{a.pageRange.includes('–') ? 'pp.' : 'p.'} {a.pageRange}</span>}
                  {a.pdfUrl && (
                    <a href={a.pdfUrl} className="link-underline inline-flex items-center gap-1 font-medium text-brand-700">
                      <FileText className="h-3.5 w-3.5" aria-hidden="true" />
                      PDF
                    </a>
                  )}
                </p>
              </RevealItem>
            ))}
          </RevealGroup>
        )}

        {(previous || next) && (
          <nav aria-label="Other issues" className="mt-12 grid max-w-4xl gap-4 border-t border-ink-200 pt-8 sm:grid-cols-2">
            {previous ? (
              <Link href={previous.url} className="card group flex items-center gap-3 p-4 hover:shadow-lift">
                <ArrowLeft className="h-4 w-4 shrink-0 text-ink-500 group-hover:text-brand-700" aria-hidden="true" />
                <span>
                  <span className="block text-xs uppercase tracking-wide text-ink-500">Previous issue</span>
                  <span className="font-medium text-ink-900">
                    {[previous.label, previous.season].filter(Boolean).join(' · ')}
                  </span>
                </span>
              </Link>
            ) : (
              <span />
            )}
            {next && (
              <Link
                href={next.url}
                className="card group flex items-center justify-end gap-3 p-4 text-right hover:shadow-lift"
              >
                <span>
                  <span className="block text-xs uppercase tracking-wide text-ink-500">Next issue</span>
                  <span className="font-medium text-ink-900">
                    {[next.label, next.season].filter(Boolean).join(' · ')}
                  </span>
                </span>
                <ArrowRight className="h-4 w-4 shrink-0 text-ink-500 group-hover:text-brand-700" aria-hidden="true" />
              </Link>
            )}
          </nav>
        )}
      </div>
    </>
  )
}
