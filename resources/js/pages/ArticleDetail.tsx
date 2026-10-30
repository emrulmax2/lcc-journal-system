import { useEffect, useRef, useState } from 'react'
import { Head, Link } from '@inertiajs/react'
import { motion, useScroll, useSpring } from 'framer-motion'
import {
  AlertTriangle,
  ArrowLeft,
  Check,
  Copy,
  Download,
  ExternalLink,
  FileText,
  Mail,
  Quote,
  Share2,
  Users,
} from 'lucide-react'
import { CardImage } from '@/components/ImageWithFallback'
import { useTranslations } from '@/lib/i18n'
import { OpenAccessBadge } from '@/components/Cards'
import { Reveal } from '@/components/Reveal'
import { formatDate, formatNumber } from '@/lib/format'
import type { Article, ArticleDetail as ArticleDetailData, Citations, CitationFormat, Meta, TocEntry } from '@/lib/props'

type Props = {
  article: ArticleDetailData
  citations: Citations
  related: Article[]
  meta: Meta
}

const FORMATS: { id: CitationFormat; label: string }[] = [
  { id: 'harvard', label: 'Harvard' },
  { id: 'bibtex', label: 'BibTeX' },
  { id: 'ris', label: 'RIS' },
]

export default function ArticleDetail({ article, citations, related, meta }: Props) {
  const { t } = useTranslations()

  // Reading-progress bar. Spring-smoothed so it glides rather than snaps. useScroll is
  // SSR-safe: on the server it resolves to a MotionValue(0) and reads no DOM.
  const { scrollYProgress } = useScroll()
  const progress = useSpring(scrollYProgress, { stiffness: 120, damping: 30, restDelta: 0.001 })

  const [showCite, setShowCite] = useState(false)
  const [format, setFormat] = useState<CitationFormat>('harvard')
  const [copied, setCopied] = useState<'citation' | 'link' | null>(null)
  const copiedTimer = useRef<number | null>(null)

  useEffect(
    () => () => {
      if (copiedTimer.current !== null) window.clearTimeout(copiedTimer.current)
    },
    [],
  )

  /**
   * Clipboard access, only ever from inside an event handler — `navigator` does not
   * exist in the SSR process, and touching it during render would take the whole page
   * down in Node rather than in a browser where someone would notice.
   */
  const copy = async (text: string, what: 'citation' | 'link') => {
    try {
      await navigator.clipboard.writeText(text)
      setCopied(what)
      if (copiedTimer.current !== null) window.clearTimeout(copiedTimer.current)
      copiedTimer.current = window.setTimeout(() => setCopied(null), 2000)
    } catch {
      // Denied, or no secure context. The citation is on screen and selectable, so there
      // is nothing to recover from — just don't claim it was copied.
    }
  }

  const share = async () => {
    const url = window.location.href

    if (typeof navigator.share === 'function') {
      try {
        await navigator.share({ title: article.title, url })
        return
      } catch {
        // The reader dismissed the share sheet. Fall through to copying the link.
      }
    }

    void copy(url, 'link')
  }

  /**
   * THE FIGURE. It renders ONLY for a real, uploaded asset — one with its own alt text,
   * caption and credit.
   *
   * What was here before: `PHOTO[article.photo]`, an Unsplash stock photograph, under the
   * caption "Figure 1. Representative imagery from the study site. Photo: Unsplash." That
   * is a picture of somewhere else, captioned as though it came from the research it sits
   * inside, on a page that is the permanent, citable record of that research. If the
   * article has no figure, the honest page has no figure.
   *
   * NOTE: ArticleController::show() does not send `heroImage` yet, so today this is always
   * absent and no figure is rendered anywhere.
   */
  const figure = article.heroImage ?? null

  const hasAuthorDetails = article.authorDetails.length > 0

  // "On this page": the abstract, the full text's own sections (from the server, with ids
  // already on the headings), then the authors and references sections of this page.
  const sections: TocEntry[] = [
    ...(article.abstract || article.keywords.length > 0 ? [{ id: 'abstract', text: 'Abstract', level: 2 }] : []),
    ...article.toc,
    ...(hasAuthorDetails || article.corporateAuthor
      ? [{ id: 'authors', text: article.authorDetails.length > 1 ? 'Authors' : 'Author', level: 2 }]
      : []),
    ...(article.references.length > 0 ? [{ id: 'references', text: 'References', level: 2 }] : []),
  ]
  const activeId = useActiveSection(sections.map((s) => s.id))
  const railRef = useRef<HTMLElement>(null)
  const railTop = useStickyTop(railRef)

  const publishedLabel = article.date
    ? `Published ${formatDate(article.date)}`
    : article.isPreview
      ? 'Not yet published'
      : 'Publication date pending'

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <motion.div
        style={{ scaleX: progress }}
        className="fixed inset-x-0 top-0 z-sticky h-1 origin-left bg-brand-600"
        aria-hidden="true"
      />

      <article>
        {/* ---------------------------- Preview banner ---------------------------- */}
        {article.isPreview && (
          <div className="border-b border-gold-200 bg-gold-50">
            <div className="container-page flex items-start gap-3 py-4">
              <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-gold-600" aria-hidden="true" />
              <div>
                <p className="text-sm font-semibold text-gold-700">
                  Unpublished draft — editor preview
                </p>
                <p className="mt-1 text-sm text-gold-700">
                  This article is not published. You can see it because you have editorial rights
                  on this journal. It is marked <code className="font-mono">noindex</code>, has no
                  citable DOI yet, and is not visible to anyone else.
                </p>
              </div>
            </div>
          </div>
        )}

        <header className="border-b border-ink-200 bg-ink-50">
          <div className="container-page py-12">
            <Link
              href="/articles"
              className="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-ink-600
                         transition-colors duration-200 hover:text-brand-800"
            >
              <ArrowLeft className="h-4 w-4" aria-hidden="true" />
              All articles
            </Link>

            <Reveal className="mt-6 max-w-4xl">
              <div className="flex flex-wrap items-center gap-2 text-xs">
                {article.type && (
                  <span className="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-800">
                    {article.type}
                  </span>
                )}
                {/* Gated on the JOURNAL's real licence. It used to render unconditionally,
                    which is a licensing claim about an article made without consulting
                    anything. ArticleController::show() does not send the journal's
                    `openAccess` flag yet, so the badge is currently never shown — a missing
                    badge is recoverable; a wrong one on a paywalled article is not. */}
                {article.journalOpenAccess === true && <OpenAccessBadge />}
                <span className="text-ink-500">{publishedLabel}</span>
              </div>

              <h1 className="mt-4 font-serif text-3xl leading-tight sm:text-4xl lg:text-5xl">
                {article.title}
              </h1>

              {/* A corporate author arrives as a single-element list and belongs in the
                  byline exactly as it is written. */}
              {article.authors.length > 0 && (
                <p className="mt-5 text-ink-700">{article.authors.join(', ')}</p>
              )}

              <p className="mt-1 text-sm text-ink-600">
                {/* The journal's LANDING page — aims and scope, ISSN, editor, contact.
                    This used to go to a filtered article list. */}
                <Link href={`/journals/${article.journalSlug}`} className="link-underline font-medium">
                  {article.journal}
                </Link>
                {article.volume !== null && (
                  <>
                    {' · '}
                    {article.issueUrl ? (
                      <Link href={article.issueUrl} className="link-underline">
                        Vol {article.volume}, No {article.issue}
                      </Link>
                    ) : article.issue !== null ? (
                      `Vol ${article.volume}, No ${article.issue}`
                    ) : (
                      `Vol ${article.volume}`
                    )}
                  </>
                )}
                {article.pageRange && <>{' · '}pp. {article.pageRange}</>}
                {/* A DOI printed as bare text cannot be followed, and a DOI that does not
                    exist yet must not be printed as a link to nowhere. */}
                {article.doi && article.doiUrl ? (
                  <>
                    {' · '}
                    <a
                      href={article.doiUrl}
                      className="link-underline font-medium"
                      target="_blank"
                      rel="noreferrer"
                    >
                      https://doi.org/{article.doi}
                    </a>
                  </>
                ) : (
                  <>{' · '}<span className="text-ink-500">DOI pending</span></>
                )}
              </p>

              <div className="mt-7 flex flex-wrap gap-3">
                {/* Never advertise a PDF that does not exist. */}
                {article.hasPdf && article.pdfUrl && (
                  <a href={article.pdfUrl} className="btn-primary">
                    <Download className="h-4 w-4" aria-hidden="true" />
                    {t('article.download_pdf', 'Download PDF')}
                  </a>
                )}

                {/* The crawlable HTML full text — a real, server-rendered page, not this SPA view. */}
                {article.hasHtmlFullText && article.htmlUrl && (
                  <a href={article.htmlUrl} className="btn-secondary">
                    <FileText className="h-4 w-4" aria-hidden="true" />
                    {t('article.read_full_text', 'Read full text')}
                  </a>
                )}

                <button
                  type="button"
                  onClick={() => setShowCite((v) => !v)}
                  aria-expanded={showCite}
                  aria-controls="cite-panel"
                  className="btn-secondary"
                >
                  <Quote className="h-4 w-4" aria-hidden="true" />
                  {t('article.cite', 'Cite')}
                </button>

                <button type="button" onClick={share} className="btn-secondary">
                  <Share2 className="h-4 w-4" aria-hidden="true" />
                  {copied === 'link' ? 'Link copied' : t('article.share', 'Share')}
                </button>
              </div>

              {showCite && (
                <div id="cite-panel" className="card mt-5 max-w-3xl p-5">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-1" role="group" aria-label="Citation format">
                      {FORMATS.map((f) => (
                        <button
                          key={f.id}
                          type="button"
                          onClick={() => setFormat(f.id)}
                          aria-pressed={format === f.id}
                          className={`inline-flex min-h-[36px] cursor-pointer items-center rounded-full border px-4
                                      text-sm font-medium transition-colors duration-200 ${
                                        format === f.id
                                          ? 'border-brand-700 bg-brand-700 text-white'
                                          : 'border-ink-300 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-50'
                                      }`}
                        >
                          {f.label}
                        </button>
                      ))}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                      <button
                        type="button"
                        onClick={() => copy(citations[format], 'citation')}
                        className="btn-ghost h-10 min-h-[40px] px-4 text-sm"
                      >
                        {copied === 'citation' ? (
                          <Check className="h-4 w-4" aria-hidden="true" />
                        ) : (
                          <Copy className="h-4 w-4" aria-hidden="true" />
                        )}
                        {copied === 'citation' ? 'Copied' : 'Copy'}
                      </button>

                      {/* Hands a .bib / .ris straight to Zotero. Drafts have no citation
                          endpoint — the route 404s until the article is published. */}
                      {!article.isPreview && (
                        <a
                          href={`/articles/${article.slug}/cite/${format}`}
                          className="btn-ghost h-10 min-h-[40px] px-4 text-sm"
                        >
                          <Download className="h-4 w-4" aria-hidden="true" />
                          Download
                        </a>
                      )}
                    </div>
                  </div>

                  <pre
                    aria-live="polite"
                    className="mt-4 max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-lg
                               bg-ink-50 p-4 text-left font-mono text-xs leading-relaxed text-ink-800"
                  >
                    {citations[format]}
                  </pre>
                </div>
              )}
            </Reveal>
          </div>
        </header>

        <div className="container-page grid grid-cols-1 gap-12 py-12 lg:grid-cols-[minmax(0,48rem)_288px]">
          <div className="min-w-0">
            {/* Small screens: the contents fold up above the text (the rail is beside it on large). */}
            {sections.length > 2 && (
              <details className="card mb-8 p-4 lg:hidden">
                <summary className="cursor-pointer text-sm font-semibold uppercase tracking-wide text-brand-800">
                  On this page
                </summary>
                <div className="mt-3">
                  <TocList sections={sections} activeId={activeId} />
                </div>
              </details>
            )}

            {figure && (
              <figure className="mb-10">
                <div className="aspect-[16/8] overflow-hidden rounded-xl">
                  <CardImage image={figure} photo={null} alt={figure.alt ?? ''} width={1400} height={700} priority />
                </div>
                {/* The asset's OWN caption and credit. Neither is written here, because
                    neither can be known here. */}
                {(figure.caption || figure.credit) && (
                  <figcaption className="mt-3 text-sm text-ink-600">
                    {figure.caption}
                    {figure.caption && figure.credit ? ' ' : ''}
                    {figure.credit && <span className="text-ink-500">{figure.credit}</span>}
                  </figcaption>
                )}
              </figure>
            )}

            {/* ---------------------------- Abstract ---------------------------- */}
            {(article.abstract || article.keywords.length > 0) && (
              <section
                id="abstract"
                className="scroll-mt-28 rounded-r-xl border-l-4 border-brand-700 bg-brand-50/70 px-6 py-6 sm:px-7"
              >
                {article.abstract && (
                  <>
                    <h2 className="font-serif text-2xl text-ink-900">Abstract</h2>
                    <p className="mt-3 whitespace-pre-line text-[1.0625rem] leading-[1.8] text-ink-800">
                      {article.abstract}
                    </p>
                  </>
                )}

                {article.keywords.length > 0 && (
                  <>
                    <h3 className={`${article.abstract ? 'mt-6' : ''} text-sm font-semibold text-ink-900`}>Keywords</h3>
                    <ul className="mt-2 flex flex-wrap gap-2">
                      {article.keywords.map((k) => (
                        <li key={k}>
                          <Link
                            href={`/articles?q=${encodeURIComponent(k)}`}
                            className="inline-flex min-h-[32px] cursor-pointer items-center rounded-full border border-ink-300
                                       bg-white px-3 text-[13px] text-ink-700 transition-colors duration-200
                                       hover:border-brand-600 hover:text-brand-800"
                          >
                            {k}
                          </Link>
                        </li>
                      ))}
                    </ul>
                  </>
                )}
              </section>
            )}

            {/*
              THE PULL-QUOTE IS GONE, and it was not a matter of taste.

              It read: "The reviewers' reports and the authors' responses are published in
              full alongside this article." None of that is true: review here is single-blind
              and no report is published anywhere. A false claim about the editorial process,
              printed on every article, is the kind of thing DOAJ checks.
            */}

            {/* --------------------------- Full text ---------------------------- */}
            {/* Rendered to HTML on the SERVER (ArticleBody — CommonMark, raw HTML escaped),
                so it is safe to set, and it is in the page Google Scholar reads. */}
            {article.bodyHtml && (
              <div className="prose-article mt-4" dangerouslySetInnerHTML={{ __html: article.bodyHtml }} />
            )}

            {/* --------------------------- Authors ---------------------------- */}
            {/* An article by a research centre has NO rows here. Rendering an empty
                "Authors" heading over nothing reads as a bug, so the whole block only
                exists when there is something to put in it. */}
            {hasAuthorDetails ? (
              <section id="authors" className="mt-12 scroll-mt-28 border-t border-ink-200 pt-8">
                <h2 className="font-serif text-[1.65rem] text-ink-900">
                  {article.authorDetails.length === 1 ? 'Author' : 'Authors'}
                </h2>
                <ul className="mt-4 space-y-4">
                  {article.authorDetails.map((a) => (
                    <li key={`${a.name}-${a.orcid ?? ''}`}>
                      <p className="flex flex-wrap items-center gap-2 font-medium text-ink-900">
                        {a.name}
                        {a.isCorresponding && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800">
                            <Mail className="h-3 w-3" aria-hidden="true" />
                            Corresponding author
                          </span>
                        )}
                      </p>
                      {a.affiliation && <p className="mt-0.5 text-sm text-ink-600">{a.affiliation}</p>}
                      {a.orcidUrl && a.orcid && (
                        <a
                          href={a.orcidUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="mt-1 inline-flex cursor-pointer items-center gap-1.5 text-sm text-brand-800
                                     underline decoration-brand-300 underline-offset-4 transition-colors
                                     duration-200 hover:decoration-brand-700"
                        >
                          ORCID {a.orcid}
                          <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                        </a>
                      )}
                    </li>
                  ))}
                </ul>
              </section>
            ) : article.corporateAuthor ? (
              <section id="authors" className="mt-12 scroll-mt-28 border-t border-ink-200 pt-8">
                <h2 className="font-serif text-[1.65rem] text-ink-900">Author</h2>
                <p className="mt-4 font-medium text-ink-900">{article.corporateAuthor}</p>
              </section>
            ) : null}

            {/* -------------------------- References -------------------------- */}
            {article.references.length > 0 && (
              <section id="references" className="mt-12 scroll-mt-28 border-t border-ink-200 pt-8">
                <h2 className="font-serif text-[1.65rem] text-ink-900">References</h2>
                <ol className="mt-5 list-decimal space-y-3 pl-6 marker:text-ink-500">
                  {article.references.map((r) => (
                    <li key={r.ordinal} className="break-words pl-1 text-[0.9375rem] leading-relaxed text-ink-700">
                      {r.text}
                      {r.doi && (
                        <>
                          {' '}
                          <a href={`https://doi.org/${r.doi}`} target="_blank" rel="noreferrer" className="link-underline">
                            https://doi.org/{r.doi}
                          </a>
                        </>
                      )}
                    </li>
                  ))}
                </ol>
              </section>
            )}
            {related.length > 0 && (
              <div className="card mt-12 p-6">
                <h2 className="font-serif text-lg">More from this journal</h2>
                <ul className="mt-4 grid gap-4 sm:grid-cols-2">
                  {related.map((r) => (
                    <li key={r.slug}>
                      <Link
                        href={`/articles/${r.slug}`}
                        className="group block cursor-pointer text-sm font-medium leading-snug text-ink-800
                                   transition-colors duration-200 hover:text-brand-800"
                      >
                        {r.title}
                        <span className="mt-1 block text-xs font-normal text-ink-500">{formatDate(r.date)}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          {/* -------------------- Rail: contents, then metrics -------------------- */}
          {/* No inner scrollbar. The rail is as tall as its content; useStickyTop pins it at
              the top when it fits the window, and otherwise lets it scroll with the page
              until its bottom (the metrics) is in view, then pins it there. */}
          <aside ref={railRef} style={{ top: railTop }} className="space-y-6 lg:sticky lg:self-start">
            {sections.length > 2 && (
              <nav aria-label="On this page" className="hidden lg:block">
                <p className="text-xs font-semibold uppercase tracking-[0.08em] text-brand-800">On this page</p>
                <div className="mt-3">
                  <TocList sections={sections} activeId={activeId} />
                </div>
              </nav>
            )}

            <div id="metrics" className="card p-5">
              <h2 className="font-serif text-lg">Article metrics</h2>
              <dl className="mt-4 grid grid-cols-2 gap-4">
                <div>
                  <dt className="flex items-center gap-2 text-xs uppercase tracking-wide text-ink-500">
                    <Users className="h-3.5 w-3.5" aria-hidden="true" />
                    Views &amp; downloads
                  </dt>
                  <dd className="mt-1 font-serif text-2xl text-ink-900">{formatNumber(article.views)}</dd>
                </div>
                <div>
                  <dt className="flex items-center gap-2 text-xs uppercase tracking-wide text-ink-500">
                    <Quote className="h-3.5 w-3.5" aria-hidden="true" />
                    Citations
                  </dt>
                  {article.citations === null ? (
                    <dd className="mt-1">
                      <span className="font-serif text-2xl text-ink-900" aria-label="Not yet tracked">
                        —
                      </span>
                      <span className="mt-0.5 block text-[11px] leading-snug text-ink-500">
                        Tracked once the article has a DOI
                      </span>
                    </dd>
                  ) : (
                    <dd className="mt-1 font-serif text-2xl text-ink-900">{formatNumber(article.citations)}</dd>
                  )}
                </div>
              </dl>

              <div className="mt-4 border-t border-ink-200 pt-4">
                <p className="text-xs uppercase tracking-wide text-ink-500">Licence</p>
                {article.license ? (
                  <p className="mt-1 text-sm text-ink-700">
                    {article.license}
                    {article.licenseHolder ? ` — © ${article.licenseHolder}` : ''}
                  </p>
                ) : (
                  <p className="mt-1 text-sm text-ink-700">All rights reserved.</p>
                )}
              </div>
            </div>

          </aside>
        </div>
      </article>
    </>
  )
}

/* ------------------------------ On this page ------------------------------ */

/**
 * The section the reader is in: the last heading that has scrolled past the top band of
 * the window. Read on scroll (one per animation frame), never during render — there is no
 * window in the SSR process, so the server renders with nothing highlighted.
 */
function useActiveSection(ids: string[]): string | null {
  const [active, setActive] = useState<string | null>(null)
  const key = ids.join('|')

  useEffect(() => {
    if (ids.length === 0) return
    let frame = 0

    const update = () => {
      frame = 0
      let current: string | null = null
      for (const id of ids) {
        const el = document.getElementById(id)
        if (el && el.getBoundingClientRect().top <= 180) current = id
      }
      setActive(current ?? ids[0])
    }
    const onScroll = () => {
      if (frame === 0) frame = window.requestAnimationFrame(update)
    }

    update()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)
    return () => {
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
      if (frame !== 0) window.cancelAnimationFrame(frame)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key])

  return active
}

/** Clear of the sticky navbar — the same 6rem the rail used as `top-24`. */
const RAIL_TOP = 96
/** Breathing room below a rail taller than the window. */
const RAIL_BOTTOM_GAP = 24

/**
 * The `top` for a sticky side rail WITHOUT an inner scrollbar.
 *
 * A rail that fits the window sticks at RAIL_TOP. A taller one gets a NEGATIVE top — the
 * window height minus its own height — so it scrolls along with the page until its bottom
 * is on screen, and sticks there: every part of it is reachable by scrolling the page,
 * and nothing needs a scrollbar of its own. Re-measured whenever the rail or the window
 * changes size. SSR renders RAIL_TOP; the browser corrects it on mount.
 */
function useStickyTop(ref: React.RefObject<HTMLElement | null>): number {
  const [top, setTop] = useState(RAIL_TOP)

  useEffect(() => {
    const el = ref.current
    if (!el) return

    const update = () => setTop(Math.min(RAIL_TOP, window.innerHeight - el.offsetHeight - RAIL_BOTTOM_GAP))
    update()

    const observer = new ResizeObserver(update)
    observer.observe(el)
    window.addEventListener('resize', update)
    return () => {
      observer.disconnect()
      window.removeEventListener('resize', update)
    }
  }, [ref])

  return top
}

/** The contents list: top-level sections, with their sub-sections indented beneath. */
function TocList({ sections, activeId }: { sections: TocEntry[]; activeId: string | null }) {
  return (
    <ol className="space-y-0.5 border-l border-ink-200">
      {sections.map((s) => {
        const active = s.id === activeId
        return (
          <li key={s.id}>
            <a
              href={`#${s.id}`}
              aria-current={active ? 'location' : undefined}
              className={`-ml-px block border-l-2 py-[3px] leading-snug transition-colors duration-200 ${
                s.level >= 3 ? 'pl-6 text-[13px]' : 'pl-3 text-sm'
              } ${
                active
                  ? 'border-brand-700 font-medium text-brand-800'
                  : 'border-transparent text-ink-600 hover:border-ink-300 hover:text-ink-900'
              }`}
            >
              {s.text}
            </a>
          </li>
        )
      })}
    </ol>
  )
}
