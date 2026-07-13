import { Link, useParams } from 'react-router-dom'
import { motion, useScroll, useSpring } from 'framer-motion'
import { ArrowLeft, Download, Quote, Share2, Users } from 'lucide-react'
import ImageWithFallback from '@/components/ImageWithFallback'
import { OpenAccessBadge, formatDate } from '@/components/Cards'
import { Reveal } from '@/components/Reveal'
import NotFound from './NotFound'
import { ARTICLES } from '@/lib/data'
import { PHOTO, unsplash } from '@/lib/images'

export default function ArticleDetail() {
  const { slug } = useParams()
  const article = ARTICLES.find((a) => a.slug === slug)

  // Reading-progress bar. Spring-smoothed so it glides rather than snaps.
  const { scrollYProgress } = useScroll()
  const progress = useSpring(scrollYProgress, { stiffness: 120, damping: 30, restDelta: 0.001 })

  if (!article) return <NotFound />

  const related = ARTICLES.filter(
    (a) => a.slug !== article.slug && a.journalSlug === article.journalSlug,
  ).slice(0, 2)

  return (
    <>
      <motion.div
        style={{ scaleX: progress }}
        className="fixed inset-x-0 top-0 z-sticky h-1 origin-left bg-brand-600"
        aria-hidden="true"
      />

      <article>
        <header className="border-b border-ink-200 bg-ink-50">
          <div className="container-page py-12">
            <Link
              to="/articles"
              className="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-ink-600
                         transition-colors duration-200 hover:text-brand-800"
            >
              <ArrowLeft className="h-4 w-4" aria-hidden="true" />
              All articles
            </Link>

            <Reveal className="mt-6 max-w-4xl">
              <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-800">
                  {article.type}
                </span>
                <OpenAccessBadge />
                <span className="text-ink-500">Published {formatDate(article.date)}</span>
              </div>

              <h1 className="mt-4 font-serif text-3xl leading-tight sm:text-4xl lg:text-5xl">
                {article.title}
              </h1>

              <p className="mt-5 text-ink-700">
                {article.authors.join(', ')}
              </p>
              <p className="mt-1 text-sm text-ink-600">
                <Link
                  to={`/articles?journal=${article.journalSlug}`}
                  className="link-underline font-medium"
                >
                  {article.journal}
                </Link>{' '}
                · DOI {article.doi}
              </p>

              <div className="mt-7 flex flex-wrap gap-3">
                <button type="button" className="btn-primary">
                  <Download className="h-4 w-4" aria-hidden="true" />
                  Download PDF
                </button>
                <button type="button" className="btn-secondary">
                  <Quote className="h-4 w-4" aria-hidden="true" />
                  Cite
                </button>
                <button type="button" className="btn-secondary">
                  <Share2 className="h-4 w-4" aria-hidden="true" />
                  Share
                </button>
              </div>
            </Reveal>
          </div>
        </header>

        <div className="container-page grid gap-12 py-12 lg:grid-cols-[1fr_300px]">
          <div>
            <Reveal className="overflow-hidden rounded-xl">
              <div className="aspect-[16/8]">
                <ImageWithFallback
                  src={unsplash(PHOTO[article.photo], 1400, 700)}
                  seed={article.slug}
                  alt={`Figure 1 illustrating ${article.title.toLowerCase()}`}
                  width={1400}
                  height={700}
                  priority
                />
              </div>
              <figcaption className="mt-3 text-sm text-ink-600">
                Figure 1. Representative imagery from the study site. Photo: Unsplash.
              </figcaption>
            </Reveal>

            <Reveal className="mt-10">
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

              <h2 className="mt-10 font-serif text-2xl">Introduction</h2>
              <p className="mt-4 max-w-prose leading-[1.75] text-ink-800">
                This prototype ships with a single representative section rather than a full text.
                In production, the article body renders from JATS XML with figures, tables,
                equations and inline citations resolved against the reference list below.
              </p>

              <blockquote className="my-8 max-w-prose border-l-4 border-brand-600 bg-brand-50 py-5 pl-6 pr-5">
                <p className="font-serif text-xl leading-snug text-ink-900">
                  “The reviewers' reports and the authors' responses are published in full alongside
                  this article.”
                </p>
                <footer className="mt-3 text-sm text-ink-600">
                  Open peer review — {article.journal}
                </footer>
              </blockquote>

              <h2 className="mt-10 font-serif text-2xl">Keywords</h2>
              <ul className="mt-4 flex flex-wrap gap-2">
                {article.keywords.map((k) => (
                  <li key={k}>
                    <Link
                      to={`/articles?q=${encodeURIComponent(k)}`}
                      className="inline-flex min-h-[36px] cursor-pointer items-center rounded-full border
                                 border-ink-300 px-3 text-sm text-ink-700 transition-colors duration-200
                                 hover:border-brand-600 hover:bg-brand-50 hover:text-brand-800"
                    >
                      {k}
                    </Link>
                  </li>
                ))}
              </ul>
            </Reveal>
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
                    {article.views.toLocaleString()}
                  </dd>
                </div>
                <div>
                  <dt className="flex items-center gap-2 text-xs uppercase tracking-wide text-ink-500">
                    <Quote className="h-3.5 w-3.5" aria-hidden="true" />
                    Citations
                  </dt>
                  <dd className="mt-1 font-serif text-3xl text-ink-900">{article.citations}</dd>
                </div>
              </dl>

              <div className="mt-6 border-t border-ink-200 pt-5">
                <p className="text-xs uppercase tracking-wide text-ink-500">Licence</p>
                <p className="mt-1 text-sm text-ink-700">
                  CC BY 4.0 — free to share and adapt with attribution.
                </p>
              </div>
            </div>

            {related.length > 0 && (
              <div className="card mt-6 p-6">
                <h2 className="font-serif text-lg">More from this journal</h2>
                <ul className="mt-4 space-y-4">
                  {related.map((r) => (
                    <li key={r.slug}>
                      <Link
                        to={`/articles/${r.slug}`}
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
