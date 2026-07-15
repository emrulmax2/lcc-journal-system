import { Head, Link } from '@inertiajs/react'
import { ArrowLeft, ArrowRight, Info, Mail } from 'lucide-react'
import { ArticleCard, OpenAccessBadge } from '@/components/Cards'
import { CardImage } from '@/components/ImageWithFallback'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate, formatNumber } from '@/lib/format'
import { toArticleCard } from '@/lib/props'
import type { Article, JournalDetail, Meta } from '@/lib/props'

type Props = {
  journal: JournalDetail
  latestArticles: Article[]
  meta: Meta
}

/**
 * The journal landing page. It did not exist.
 *
 * Every journal card, every mega-menu entry and every article's journal link went to
 * `/articles?journal={slug}` — a filtered article list — so `aims_and_scope`, `issn_online`,
 * `issn_print`, `principal_editor`, `contact_email` and `publisher` were columns read by
 * NOTHING. **DOAJ will not accept a journal with no public aims-and-scope page**, and an
 * ISSN nobody can see is not much use to the person trying to verify the journal is real.
 */
export default function Journal({ journal, latestArticles, meta }: Props) {
  const m = journal.metrics

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="relative isolate overflow-hidden bg-ink-900">
        <div className="absolute inset-0 -z-10">
          <CardImage
            image={journal.coverImage}
            photo={journal.photo}
            alt=""
            width={1920}
            height={800}
          />
        </div>
        <div
          aria-hidden="true"
          className="absolute inset-0 -z-10 bg-gradient-to-br from-ink-950/92 via-ink-900/85 to-brand-900/70"
        />

        <div className="container-page py-16">
          <Link
            href="/journals"
            className="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-ink-200
                       transition-colors duration-200 hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            All journals
          </Link>

          <Reveal className="mt-6 max-w-3xl">
            <div className="flex flex-wrap items-center gap-2 text-xs">
              {journal.field && (
                <span className="rounded-full bg-white/15 px-2.5 py-1 font-semibold text-white backdrop-blur">
                  {journal.field}
                </span>
              )}
              {journal.openAccess && <OpenAccessBadge />}
              <span className="rounded-full bg-white/15 px-2.5 py-1 font-semibold text-white backdrop-blur">
                {journal.publicationModel === 'issue_based'
                  ? 'Published in issues'
                  : 'Continuous publication'}
              </span>
            </div>

            <h1 className="mt-4 font-serif text-3xl leading-tight text-white sm:text-4xl lg:text-5xl">
              {journal.title}
            </h1>

            {journal.abbreviation && (
              <p className="mt-2 text-sm text-ink-200">{journal.abbreviation}</p>
            )}

            {journal.description && (
              <p className="mt-5 max-w-prose text-lg leading-relaxed text-ink-200">
                {journal.description}
              </p>
            )}

            <div className="mt-8 flex flex-wrap gap-3">
              <Link href="/submit" className="btn-primary">
                Submit to this journal
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Link>
              <Link href={`/articles?journal=${journal.slug}`} className="btn-outline-light">
                Browse all articles
              </Link>
            </div>
          </Reveal>
        </div>
      </header>

      <div className="container-page grid gap-12 py-12 lg:grid-cols-[1fr_320px]">
        <div>
          <Reveal>
            <h2 className="font-serif text-2xl">Aims and scope</h2>
            {journal.aimsAndScopeHtml ? (
              <div
                className="prose-cms mt-4 max-w-prose overflow-x-auto"
                dangerouslySetInnerHTML={{ __html: journal.aimsAndScopeHtml }}
              />
            ) : (
              <p className="mt-4 max-w-prose text-ink-600">
                The aims and scope for this journal have not been published yet.
              </p>
            )}
          </Reveal>

          {journal.sections.length > 0 && (
            <Reveal className="mt-12">
              <h2 className="font-serif text-2xl">Sections</h2>
              <p className="mt-2 max-w-prose text-sm text-ink-600">
                The article types this journal accepts.
              </p>
              <ul className="mt-4 flex flex-wrap gap-2">
                {journal.sections.map((s) => (
                  <li key={s}>
                    <span className="inline-flex min-h-[36px] items-center rounded-full border border-ink-300 px-3 text-sm text-ink-700">
                      {s}
                    </span>
                  </li>
                ))}
              </ul>
            </Reveal>
          )}

          {latestArticles.length > 0 && (
            <section className="mt-12">
              <Reveal className="flex flex-wrap items-end justify-between gap-4">
                <h2 className="font-serif text-2xl">Latest articles</h2>
                <Link href={`/articles?journal=${journal.slug}`} className="btn-secondary">
                  All articles
                  <ArrowRight className="h-4 w-4" aria-hidden="true" />
                </Link>
              </Reveal>

              <RevealGroup className="mt-6 space-y-5" stagger={0.06}>
                {latestArticles.map((a) => (
                  <RevealItem key={a.slug}>
                    <ArticleCard article={toArticleCard(a)} />
                  </RevealItem>
                ))}
              </RevealGroup>
            </section>
          )}
        </div>

        <aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
          {/*
            METRICS, SPLIT BY WHO PRODUCED THEM.

            The Impact Factor and CiteScore are issued by Clarivate (JCR) and Elsevier
            (Scopus). We do not compute them, we cannot compute them, and a launch journal
            has neither — that is the normal state, not an error. The acceptance rate and the
            median time to decision ARE ours, computed from our own decision data.

            Presenting the four together, unlabelled, tells an author that we assert all of
            them. Every null renders "—" and never "0": a fabricated zero is a *wrong* impact
            factor on a page researchers use to choose where to send their work.
          */}
          <div className="card p-6">
            <h2 className="font-serif text-lg">Metrics</h2>

            <div className="mt-5">
              <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-ink-500">
                Indexed by others
                <Info className="h-3.5 w-3.5" aria-hidden="true" />
              </p>
              <p className="mt-1 text-xs leading-relaxed text-ink-600">
                Issued by Clarivate (JCR) and Elsevier (Scopus). Not computed by us.
              </p>
              <dl className="mt-3 grid grid-cols-2 gap-4">
                <Metric label="Impact Factor" value={m.impactFactor} decimals={1} />
                <Metric label="CiteScore" value={m.citeScore} decimals={1} />
              </dl>
              {m.externalAsOf && (
                <p className="mt-2 text-xs text-ink-500">As of {formatDate(m.externalAsOf)}</p>
              )}
            </div>

            <div className="mt-6 border-t border-ink-200 pt-5">
              <p className="text-xs font-semibold uppercase tracking-wide text-ink-500">
                Computed by us
              </p>
              <p className="mt-1 text-xs leading-relaxed text-ink-600">
                From this journal's own submission and decision record.
              </p>
              <dl className="mt-3 grid grid-cols-2 gap-4">
                <Metric label="Acceptance rate" value={m.acceptanceRate} suffix="%" />
                <Metric label="Days to decision" value={m.medianDaysToDecision} note="median" />
                <Metric label="Articles" value={m.articleCount} />
                <Metric label="Editors" value={m.editorCount} />
              </dl>
              {m.computedAt && (
                <p className="mt-2 text-xs text-ink-500">Last computed {formatDate(m.computedAt)}</p>
              )}
            </div>
          </div>

          <div className="card p-6">
            <h2 className="font-serif text-lg">About this journal</h2>
            <dl className="mt-5 space-y-4 text-sm">
              <Fact label="Publisher" value={journal.publisher} />
              <Fact label="Principal editor" value={journal.principalEditor} />

              {/*
                ISSN. NULL until the British Library issues one, and it says so in words.

                The prototype printed "0000-0000". A placeholder ISSN looks like a real ISSN
                to everybody except a librarian — and a wrong one, quoted in a citation or a
                DOAJ application, is worse than an absent one.
              */}
              <Issn label="ISSN (online)" value={journal.issnOnline} />
              <Issn label="ISSN (print)" value={journal.issnPrint} />

              <Fact label="Licence" value={journal.license} />

              {journal.contactEmail && (
                <div>
                  <dt className="text-xs uppercase tracking-wide text-ink-500">Contact</dt>
                  <dd className="mt-1">
                    <a
                      href={`mailto:${journal.contactEmail}`}
                      className="link-underline inline-flex items-center gap-1.5"
                    >
                      <Mail className="h-3.5 w-3.5" aria-hidden="true" />
                      {journal.contactEmail}
                    </a>
                  </dd>
                </div>
              )}
            </dl>
          </div>
        </aside>
      </div>
    </>
  )
}

/** A missing metric is an em-dash. It is never a zero, and it is never blank. */
function Metric({
  label,
  value,
  decimals = 0,
  suffix = '',
  note,
}: {
  label: string
  value: number | null
  decimals?: number
  suffix?: string
  note?: string
}) {
  return (
    <div>
      <dt className="text-[11px] uppercase tracking-wide text-ink-500">{label}</dt>
      <dd className="mt-0.5 font-serif text-2xl text-ink-900">
        {value == null ? (
          <span aria-label="Not available">—</span>
        ) : (
          <>
            {formatNumber(value, decimals)}
            {suffix}
          </>
        )}
      </dd>
      {note && <p className="text-[11px] text-ink-500">{note}</p>}
    </div>
  )
}

function Fact({ label, value }: { label: string; value: string | null }) {
  return (
    <div>
      <dt className="text-xs uppercase tracking-wide text-ink-500">{label}</dt>
      <dd className={`mt-1 ${value ? 'text-ink-800' : 'text-ink-500'}`}>{value ?? 'Not set'}</dd>
    </div>
  )
}

function Issn({ label, value }: { label: string; value: string | null }) {
  return (
    <div>
      <dt className="text-xs uppercase tracking-wide text-ink-500">{label}</dt>
      <dd className={`mt-1 font-mono ${value ? 'text-ink-800' : 'font-sans text-ink-500'}`}>
        {value ?? 'Not yet issued'}
      </dd>
    </div>
  )
}
