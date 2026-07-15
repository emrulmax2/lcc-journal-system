import { Head, Link } from '@inertiajs/react'
import { CheckCircle2, Clock, ExternalLink, Newspaper, PencilLine, Plus } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { EmptyState, Panel } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate } from '@/lib/format'
import { contentHref, type ContentPage } from '@/lib/content'

/**
 * News items.
 *
 * `news_items.slug` and `.body` have existed since the first migration, and six news cards
 * rendered on the homepage with a "Read the story" affordance — every one of them href="#".
 * The body column was written for, and read by, nothing.
 *
 * published_at is the switch, and it carries three states, not two: NULL is a draft, a FUTURE
 * date is scheduled and is not on the site yet, a PAST date is live. The badge says which,
 * with an icon and a word.
 */

type NewsRow = {
  id: number
  title: string
  slug: string
  category: string
  excerpt: string | null
  publishedAt: string | null
  isPublished: boolean
  isScheduled: boolean
  author: string | null
  image: { url: string; alt: string | null } | null
  url: string
}

type Props = ContentPage<{ news: NewsRow[] }>

export default function ContentNews({ news, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="News"
        description="Editorial announcements, calls for papers and research highlights. Drafts first."
        actions={
          <Link href={contentHref.newNews} className="btn-primary">
            <Plus className="h-4 w-4" aria-hidden="true" />
            New story
          </Link>
        }
      >
        {news.length === 0 ? (
          <EmptyState
            icon={Newspaper}
            title="No stories yet"
            body="A news item has a title, a category, a body in markdown and a publication date. Until that date passes, it is not on the site."
          >
            <Link href={contentHref.newNews} className="btn-primary">
              <Plus className="h-4 w-4" aria-hidden="true" />
              Write the first story
            </Link>
          </EmptyState>
        ) : (
          <Panel title={`${news.length} ${news.length === 1 ? 'story' : 'stories'}`}>
            <RevealGroup as="ul" className="divide-y divide-ink-200" stagger={0.04}>
              {news.map((item) => (
                <RevealItem as="li" key={item.id} className="flex flex-wrap items-start gap-4 py-4">
                  {item.image ? (
                    <img
                      src={item.image.url}
                      alt={item.image.alt ?? ''}
                      className="h-16 w-24 shrink-0 rounded-lg border border-ink-200 bg-ink-100 object-cover"
                    />
                  ) : (
                    <div className="flex h-16 w-24 shrink-0 items-center justify-center rounded-lg border border-dashed border-ink-300 text-ink-500">
                      <Newspaper className="h-5 w-5" aria-hidden="true" />
                    </div>
                  )}

                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-3">
                      <Link
                        href={contentHref.editNews(item.id)}
                        className="cursor-pointer font-serif text-lg text-ink-900 transition-colors duration-200 hover:text-brand-800"
                      >
                        {item.title}
                      </Link>

                      <NewsStatus item={item} />

                      <span className="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-800">
                        {item.category}
                      </span>
                    </div>

                    <p className="mt-1 font-mono text-xs text-ink-600">{item.url}</p>

                    {item.excerpt && (
                      <p className="mt-1.5 max-w-prose text-sm text-ink-600">{item.excerpt}</p>
                    )}

                    {item.author && (
                      <p className="mt-1 text-xs text-ink-500">By {item.author}</p>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
                    {item.isPublished && (
                      <a
                        href={item.url}
                        target="_blank"
                        rel="noreferrer"
                        className="btn-ghost px-3 py-1.5 text-xs"
                      >
                        <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                        View
                      </a>
                    )}

                    <Link
                      href={contentHref.editNews(item.id)}
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

function NewsStatus({ item }: { item: NewsRow }) {
  if (item.isPublished) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-semibold text-success-800">
        <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
        Live — {formatDate(item.publishedAt)}
      </span>
    )
  }

  if (item.isScheduled) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[11px] font-semibold text-gold-700">
        <Clock className="h-3.5 w-3.5" aria-hidden="true" />
        Scheduled — {formatDate(item.publishedAt)}
      </span>
    )
  }

  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-semibold text-ink-700">
      <PencilLine className="h-3.5 w-3.5" aria-hidden="true" />
      Draft
    </span>
  )
}
