import { Head, Link } from '@inertiajs/react'
import { ArrowLeft, ArrowRight, CalendarClock, ExternalLink, Mail } from 'lucide-react'
import { TopicStatus } from '@/components/Cards'
import { CardImage } from '@/components/ImageWithFallback'
import { Reveal } from '@/components/Reveal'
import { daysUntil, formatLongDate } from '@/lib/format'
import { useShared } from '@/lib/props'
import type { Meta, ResearchTopicDetail } from '@/lib/props'

type Props = {
  topic: ResearchTopicDetail
  meta: Meta
}

export default function ResearchTopic({ topic, meta }: Props) {
  const { now } = useShared()

  /**
   * "Closes in N days" is computed against the SERVER's clock, passed down as a prop —
   * never `new Date()`. A countdown derived from the client clock disagrees with the SSR
   * render across a midnight or a timezone, and on a deadline that is the one number a
   * reader will act on.
   */
  const daysLeft =
    topic.deadline && topic.isOpen && now ? daysUntil(topic.deadline, now) : null

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <article>
        <header className="border-b border-ink-200 bg-ink-50">
          <div className="container-page py-12">
            <Link
              href="/topics"
              className="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-ink-600
                         transition-colors duration-200 hover:text-brand-800"
            >
              <ArrowLeft className="h-4 w-4" aria-hidden="true" />
              All Research Topics
            </Link>

            <Reveal className="mt-6 max-w-3xl">
              <div className="flex flex-wrap items-center gap-2">
                <TopicStatus isOpen={topic.isOpen} hasClosed={topic.hasClosed} />
                {topic.journal && (
                  <Link
                    href={`/journals/${topic.journal.slug}`}
                    className="link-underline text-sm font-medium"
                  >
                    {topic.journal.title}
                  </Link>
                )}
              </div>

              <h1 className="mt-4 font-serif text-3xl leading-tight sm:text-4xl lg:text-5xl">
                {topic.title}
              </h1>

              {topic.description && (
                <p className="mt-5 max-w-prose text-lg leading-relaxed text-ink-600">
                  {topic.description}
                </p>
              )}
            </Reveal>
          </div>
        </header>

        <div className="container-page grid gap-12 py-12 lg:grid-cols-[1fr_320px]">
          <div>
            {(topic.image || topic.photo) && (
              <Reveal className="mb-10">
                <div className="aspect-[16/8] overflow-hidden rounded-xl">
                  <CardImage
                    image={topic.image}
                    photo={topic.photo}
                    alt=""
                    width={1200}
                    height={600}
                    priority
                  />
                </div>
              </Reveal>
            )}

            <Reveal>
              {/* Server-rendered, safe HTML from MarkdownRenderer. See Page.tsx. */}
              <div
                className="prose-cms max-w-prose overflow-x-auto"
                dangerouslySetInnerHTML={{ __html: topic.bodyHtml }}
              />
            </Reveal>

            {topic.editors.length > 0 && (
              <Reveal className="mt-12">
                <h2 className="font-serif text-2xl">Topic editors</h2>
                <ul className="mt-4 space-y-4">
                  {topic.editors.map((e) => (
                    <li key={e.name} className="max-w-prose">
                      <p className="font-medium text-ink-900">{e.name}</p>
                      {e.affiliation && (
                        <p className="mt-0.5 text-sm text-ink-600">{e.affiliation}</p>
                      )}
                      {e.orcidUrl && (
                        <a
                          href={e.orcidUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="mt-1 inline-flex cursor-pointer items-center gap-1.5 text-sm text-brand-800
                                     underline decoration-brand-300 underline-offset-4 transition-colors
                                     duration-200 hover:decoration-brand-700"
                        >
                          ORCID record
                          <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                        </a>
                      )}
                    </li>
                  ))}
                </ul>
              </Reveal>
            )}
          </div>

          {/* The facts an author needs before deciding to write: is it open, when does it
              close, and where does the manuscript go. */}
          <aside className="lg:sticky lg:top-24 lg:self-start">
            <div className="card p-6">
              <h2 className="font-serif text-lg">Submit to this topic</h2>

              <dl className="mt-5 space-y-5 text-sm">
                <div>
                  <dt className="flex items-center gap-2 text-xs uppercase tracking-wide text-ink-500">
                    <CalendarClock className="h-3.5 w-3.5" aria-hidden="true" />
                    Abstract deadline
                  </dt>
                  <dd className="mt-1 font-semibold text-ink-900">
                    {topic.deadline ? (
                      <time dateTime={topic.deadline}>{formatLongDate(topic.deadline)}</time>
                    ) : (
                      'Not set'
                    )}
                  </dd>
                  {daysLeft !== null && daysLeft >= 0 && (
                    <p className="mt-1 text-xs text-ink-600">
                      {daysLeft === 0
                        ? 'Closes today'
                        : `Closes in ${daysLeft} ${daysLeft === 1 ? 'day' : 'days'}`}
                    </p>
                  )}
                </div>

                {topic.submissionEmail && (
                  <div>
                    <dt className="flex items-center gap-2 text-xs uppercase tracking-wide text-ink-500">
                      <Mail className="h-3.5 w-3.5" aria-hidden="true" />
                      Topic enquiries
                    </dt>
                    <dd className="mt-1">
                      <a href={`mailto:${topic.submissionEmail}`} className="link-underline">
                        {topic.submissionEmail}
                      </a>
                    </dd>
                  </div>
                )}
              </dl>

              {topic.isOpen ? (
                <Link href="/submit" className="btn-primary mt-6 w-full">
                  Submit to this topic
                  <ArrowRight className="h-4 w-4" aria-hidden="true" />
                </Link>
              ) : (
                <p className="mt-6 rounded-lg bg-ink-100 p-4 text-sm text-ink-700">
                  This call is closed and is no longer accepting submissions. The journal accepts
                  general submissions at any time.
                </p>
              )}

              {!topic.isOpen && (
                <Link href="/submit" className="btn-secondary mt-3 w-full">
                  Submit to the journal
                </Link>
              )}
            </div>
          </aside>
        </div>
      </article>
    </>
  )
}
