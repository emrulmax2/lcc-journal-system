import { Head, Link } from '@inertiajs/react'
import { motion } from 'framer-motion'
import { ArrowRight, CalendarClock, Users } from 'lucide-react'
import { TopicStatus } from '@/components/Cards'
import { CardImage } from '@/components/ImageWithFallback'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatLongDate, formatNumber } from '@/lib/format'
import { fadeUp, hoverLift } from '@/lib/motion'
import type { Meta, ResearchTopic } from '@/lib/props'

type Props = {
  topics: ResearchTopic[]
  meta: Meta
}

/**
 * Research Topics — open calls for papers.
 *
 * These cards were on the homepage and every single one of them linked to /journals. A call
 * for papers whose scope and deadline you cannot read is not a call for papers.
 *
 * THE DEADLINE IS THE MOST IMPORTANT FACT ON THE CARD, so it is stated in words on its own
 * line, and whether the call is open is stated in words too — an icon and a label, never a
 * colour on its own. Someone who cannot distinguish the green pill from the grey one must
 * still be able to tell that a call has closed before they spend a month writing for it.
 */
export default function ResearchTopics({ topics, meta }: Props) {
  const open = topics.filter((t) => t.isOpen)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-14">
          <Reveal>
            <p className="eyebrow">Research Topics</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">Open calls for papers</h1>
            <p className="mt-4 max-w-prose text-ink-600">
              Curated collections led by topic editors. Contribute an article before the abstract
              deadline.
            </p>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        {topics.length > 0 ? (
          <>
            <p aria-live="polite" className="text-sm text-ink-600">
              {formatNumber(topics.length)} {topics.length === 1 ? 'topic' : 'topics'} ·{' '}
              {formatNumber(open.length)} open for submissions
            </p>

            <RevealGroup className="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3" stagger={0.07}>
              {topics.map((topic) => (
                <RevealItem key={topic.slug} className="h-full">
                  <TopicListCard topic={topic} />
                </RevealItem>
              ))}
            </RevealGroup>
          </>
        ) : (
          <div className="card p-12 text-center">
            <p className="font-serif text-xl text-ink-900">No Research Topics yet</p>
            <p className="mx-auto mt-2 max-w-prose text-sm text-ink-600">
              When an editor opens a call for papers, it will be listed here with its scope and its
              abstract deadline.
            </p>
          </div>
        )}
      </div>
    </>
  )
}

function TopicListCard({ topic }: { topic: ResearchTopic }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        href={topic.url}
        className="card group flex h-full cursor-pointer flex-col overflow-hidden hover:shadow-lift"
      >
        <div className="aspect-[16/9] w-full overflow-hidden">
          <CardImage
            image={topic.image}
            photo={topic.photo}
            alt=""
            width={800}
            height={450}
            className="transition-transform duration-500 group-hover:scale-[1.04]"
          />
        </div>

        <div className="flex flex-1 flex-col p-5">
          <div className="flex flex-wrap items-center gap-2">
            <TopicStatus isOpen={topic.isOpen} hasClosed={topic.hasClosed} />
            {topic.journal && (
              <span className="text-xs font-medium text-brand-700">{topic.journal.title}</span>
            )}
          </div>

          <h2 className="mt-3 font-serif text-xl leading-snug text-ink-900 transition-colors duration-200 group-hover:text-brand-800">
            {topic.title}
          </h2>

          {topic.description && (
            <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{topic.description}</p>
          )}

          <dl className="mt-4 space-y-2 border-t border-ink-200 pt-4 text-sm">
            <div className="flex items-center gap-2">
              <dt className="inline-flex items-center gap-1.5 text-ink-600">
                <CalendarClock className="h-4 w-4 text-ink-500" aria-hidden="true" />
                Abstract deadline
              </dt>
              <dd className="font-semibold text-ink-900">
                {topic.deadline ? formatLongDate(topic.deadline) : 'Not set'}
              </dd>
            </div>
            <div className="flex items-center gap-2">
              <dt className="inline-flex items-center gap-1.5 text-ink-600">
                <Users className="h-4 w-4 text-ink-500" aria-hidden="true" />
                Topic editors
              </dt>
              <dd className="font-semibold text-ink-900">{formatNumber(topic.editorCount)}</dd>
            </div>
          </dl>

          <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-800">
            Read the call
            <ArrowRight
              className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1"
              aria-hidden="true"
            />
          </span>
        </div>
      </Link>
    </motion.article>
  )
}
