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
import type { Article, ArticleDetail as ArticleDetailData, Citations, CitationFormat, Meta } from '@/lib/props'

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
                    {article.issue !== null
                      ? `Vol ${article.volume}, No ${article.issue}`
                      : `Vol ${article.volume}`}
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

        <div className="container-page grid gap-12 py-12 lg:grid-cols-[1fr_300px]">
          <div>
            {figure && (
              <Reveal>
                <figure>
                  <div className="aspect-[16/8] overflow-hidden rounded-xl">
                    <CardImage
                      image={figure}
                      photo={null}
                      alt={figure.alt ?? ''}
                      width={1400}
                      height={700}
                      priority
                    />
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
              </Reveal>
            )}

            <Reveal className={figure ? 'mt-10' : ''}>
              {article.abstract && (
                <>
                  <h2 className="font-serif text-2xl">Abstract</h2>
                  {/* Drop cap + measure-limited column: editorial long-form typography. */}
                  <p
                    className="mt-4 max-w-prose text-lg leading-[1.75] text-ink-800
                               first-letter:float-left first-letter:mr-3 first-letter:mt-1
                               first-letter:font-serif first-letter:text-6xl first-letter:leading-[0.85]
                               first-letter:text-brand-800"
                  >
                    {article.abstract}
                  </p>
                </>
              )}

              {article.body && (
                <>
                  <h2 className="mt-10 font-serif text-2xl">Full text</h2>
                  {article.body
                    .split(/\n{2,}/)
                    .map((para) => para.trim())
                    .filter(Boolean)
                    .map((para, i) => (
                      <p key={i} className="mt-4 max-w-prose leading-[1.75] text-ink-800">
                        {para}
                      </p>
                    ))}
                </>
              )}

              {/*
                THE PULL-QUOTE IS GONE, and it was not a matter of taste.

                It read: "The reviewers' reports and the authors' responses are published in
                full alongside this article." — over a footer reading "Open peer review —
                {journal}". None of that is true. Review here is SINGLE-BLIND: reviewer
                identities are withheld from authors, no report is published anywhere on this
                site, and nothing in the codebase renders one. It was a factual claim about
                the editorial process, printed on the permanent record of every article, that
                the system does not honour — and it is the kind of claim DOAJ checks.

                The pull-quote STYLING stays in the design system for when there is real
                content to put in it. There is not one here, so there is no pull-quote.
              */}

              {article.keywords.length > 0 && (
                <>
                  <h2 className="mt-10 font-serif text-2xl">Keywords</h2>
                  <ul className="mt-4 flex flex-wrap gap-2">
                    {article.keywords.map((k) => (
                      <li key={k}>
                        <Link
                          href={`/articles?q=${encodeURIComponent(k)}`}
                          className="inline-flex min-h-[36px] cursor-pointer items-center rounded-full border
                                     border-ink-300 px-3 text-sm text-ink-700 transition-colors duration-200
                                     hover:border-brand-600 hover:bg-brand-50 hover:text-brand-800"
                        >
                          {k}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </>
              )}
            </Reveal>

            {/* --------------------------- Authors ---------------------------- */}
            {/* An article by a research centre has NO rows here. Rendering an empty
                "Authors" heading over nothing reads as a bug, so the whole block only
                exists when there is something to put in it. */}
            {hasAuthorDetails ? (
              <Reveal className="mt-12">
                <h2 className="font-serif text-2xl">Authors</h2>
                <ul className="mt-4 space-y-4">
                  {article.authorDetails.map((a) => (
                    <li key={`${a.name}-${a.orcid ?? ''}`} className="max-w-prose">
                      <p className="flex flex-wrap items-center gap-2 font-medium text-ink-900">
                        {a.name}
                        {a.isCorresponding && (
                          <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800">
                            <Mail className="h-3 w-3" aria-hidden="true" />
                            Corresponding author
                          </span>
                        )}
                      </p>
                      {a.affiliation && (
                        <p className="mt-0.5 text-sm text-ink-600">{a.affiliation}</p>
                      )}
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
              </Reveal>
            ) : article.corporateAuthor ? (
              <Reveal className="mt-12">
                <h2 className="font-serif text-2xl">Author</h2>
                <p className="mt-4 max-w-prose font-medium text-ink-900">
                  {article.corporateAuthor}
                </p>
              </Reveal>
            ) : null}

            {/* -------------------------- References -------------------------- */}
            {article.references.length > 0 && (
              <Reveal className="mt-12">
                <h2 className="font-serif text-2xl">References</h2>
                <ol className="mt-4 space-y-3">
                  {article.references.map((r) => (
                    <li key={r.ordinal} className="max-w-prose text-sm leading-relaxed text-ink-700">
                      <span className="mr-2 font-semibold text-ink-500">{r.ordinal}.</span>
                      {r.text}
                      {r.doi && (
                        <>
                          {' '}
                          <a
                            href={`https://doi.org/${r.doi}`}
                            target="_blank"
                            rel="noreferrer"
                            className="link-underline"
                          >
                            https://doi.org/{r.doi}
                          </a>
                        </>
                      )}
                    </li>
                  ))}
                </ol>
              </Reveal>
            )}
          </div>

          {/* Metrics rail */}
          <aside className="lg:sticky lg:top-24 lg:self-start">
            <div className="card p-6">
              <h2 className="font-serif text-lg">Article metrics</h2>
              <dl className="mt-5 space-y-5">
                <div>
                  <dt className="flex items-center gap-2 text-xs uppercase tracking-wide text-ink-500">
                    <Users className="h-3.5 w-3.5" aria-hidden="true" />
                    Views &amp; downloads
                  </dt>
                  <dd className="mt-1 font-serif text-3xl text-ink-900">
                    {formatNumber(article.views)}
                  </dd>
                </div>
                <div>
                  <dt className="flex items-center gap-2 text-xs uppercase tracking-wide text-ink-500">
                    <Quote className="h-3.5 w-3.5" aria-hidden="true" />
                    Citations
                  </dt>
                  <dd className="mt-1 font-serif text-3xl text-ink-900">
                    {formatNumber(article.citations)}
                  </dd>
                </div>
              </dl>

              <div className="mt-6 border-t border-ink-200 pt-5">
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

            {related.length > 0 && (
              <div className="card mt-6 p-6">
                <h2 className="font-serif text-lg">More from this journal</h2>
                <ul className="mt-4 space-y-4">
                  {related.map((r) => (
                    <li key={r.slug}>
                      <Link
                        href={`/articles/${r.slug}`}
                        className="group block cursor-pointer text-sm font-medium leading-snug text-ink-800
                                   transition-colors duration-200 hover:text-brand-800"
                      >
                        {r.title}
                        <span className="mt-1 block text-xs font-normal text-ink-500">
                          {formatDate(r.date)}
                        </span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </aside>
        </div>
      </article>
    </>
  )
}
