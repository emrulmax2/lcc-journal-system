import { Head, Link } from '@inertiajs/react'
import { CalendarClock, Compass, DoorClosed, DoorOpen, ExternalLink, PencilLine, Plus } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { EmptyState, Panel } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate } from '@/lib/format'
import { contentHref, type ContentPage } from '@/lib/content'

/**
 * Research Topics — open calls for papers.
 *
 * Every one of these cards used to link to /journals. A call for papers that does not say what
 * it is calling for, and cannot be read, is not a call for papers — which is why the migration
 * added `body` and `submission_email`, and why there is now a detail page for them.
 */

type TopicRow = {
  id: number
  title: string
  slug: string
  description: string | null
  deadline: string | null
  isOpen: boolean
  journal: string | null
  editors: string[]
  image: { url: string; alt: string | null } | null
  url: string
}

type Props = ContentPage<{ topics: TopicRow[] }>

export default function ContentTopics({ topics, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Research Topics"
        description="Curated collections led by topic editors, with a published deadline and a scope an author can read."
        actions={
          <Link href={contentHref.newTopic} className="btn-primary">
            <Plus className="h-4 w-4" aria-hidden="true" />
            New topic
          </Link>
        }
      >
        {topics.length === 0 ? (
          <EmptyState
            icon={Compass}
            title="No Research Topics yet"
            body="A topic is a call for papers: a scope, a deadline, topic editors, and an address to send questions to."
          >
            <Link href={contentHref.newTopic} className="btn-primary">
              <Plus className="h-4 w-4" aria-hidden="true" />
              Open the first call
            </Link>
          </EmptyState>
        ) : (
          <Panel title={`${topics.length} ${topics.length === 1 ? 'topic' : 'topics'}`}>
            <RevealGroup as="ul" className="divide-y divide-ink-200" stagger={0.04}>
              {topics.map((topic) => (
                <RevealItem
                  as="li"
                  key={topic.id}
                  className="flex flex-wrap items-start gap-4 py-4"
                >
                  {topic.image ? (
                    <img
                      src={topic.image.url}
                      alt={topic.image.alt ?? ''}
                      className="h-16 w-24 shrink-0 rounded-lg border border-ink-200 bg-ink-100 object-cover"
                    />
                  ) : (
                    <div className="flex h-16 w-24 shrink-0 items-center justify-center rounded-lg border border-dashed border-ink-300 text-ink-500">
                      <Compass className="h-5 w-5" aria-hidden="true" />
                    </div>
                  )}

                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-3">
                      <Link
                        href={contentHref.editTopic(topic.id)}
                        className="cursor-pointer font-serif text-lg text-ink-900 transition-colors duration-200 hover:text-brand-800"
                      >
                        {topic.title}
                      </Link>

                      {topic.isOpen ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-semibold text-success-800">
                          <DoorOpen className="h-3.5 w-3.5" aria-hidden="true" />
                          Open
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-semibold text-ink-700">
                          <DoorClosed className="h-3.5 w-3.5" aria-hidden="true" />
                          Closed
                        </span>
                      )}

                      {topic.deadline && (
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[11px] font-semibold text-gold-700">
                          <CalendarClock className="h-3.5 w-3.5" aria-hidden="true" />
                          {formatDate(topic.deadline)}
                        </span>
                      )}

                      {topic.journal && (
                        <span className="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-800">
                          {topic.journal}
                        </span>
                      )}
                    </div>

                    <p className="mt-1 font-mono text-xs text-ink-600">{topic.url}</p>

                    {topic.description && (
                      <p className="mt-1.5 max-w-prose text-sm text-ink-600">{topic.description}</p>
                    )}

                    <p className="mt-1 text-xs text-ink-500">
                      {topic.editors.length > 0
                        ? `Topic editors: ${topic.editors.join(', ')}`
                        : 'No topic editors named — a call for papers with nobody leading it.'}
                    </p>
                  </div>

                  <div className="flex items-center gap-2">
                    <a
                      href={topic.url}
                      target="_blank"
                      rel="noreferrer"
                      className="btn-ghost px-3 py-1.5 text-xs"
                    >
                      <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                      View
                    </a>

                    <Link
                      href={contentHref.editTopic(topic.id)}
                      className="btn-secondary px-3 py-1.5 text-xs"
                    >
                      <PencilLine className="h-3.5 w-3.5" aria-hidden="true" />
                      Edit
                    </Link>
                  </div>
                </RevealItem>
              ))}
            </RevealGroup>
          </Panel>
        )}
      </ContentShell>
    </>
  )
}
