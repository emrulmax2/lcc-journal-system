import { Head } from '@inertiajs/react'
import { NewsCard } from '@/components/Cards'
import Pagination from '@/components/Pagination'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatNumber } from '@/lib/format'
import { toNewsCard } from '@/lib/props'
import type { Meta, NewsItem, SimplePaginated } from '@/lib/props'

type Props = {
  /**
   * NOTE THE SHAPE: NewsController::index uses `$news->through(...)`, which keeps Laravel's
   * own paginator envelope — `links` is the NUMBERED array at the top level, and the counts
   * sit beside it. That is NOT the API-resource envelope the Articles page receives, where
   * the numbered links live at `meta.links`. Reading `meta.links` off this one silently
   * yields undefined and a pager that renders nothing.
   */
  news: SimplePaginated<NewsItem>
  meta: Meta
}

/**
 * The news index. It did not exist.
 *
 * Six news cards rendered on the homepage, each with a "Read the story →" affordance, and
 * every one of them was a dead anchor pointing at "#". `news_items.slug` and `.body` have
 * been in the schema since the first migration and were read by nothing.
 */
export default function News({ news, meta }: Props) {
  const items = news.data

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-14">
          <Reveal>
            <p className="eyebrow">Newsroom</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">News</h1>
            <p className="mt-4 max-w-prose text-ink-600">{meta.description}</p>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        {items.length > 0 ? (
          <>
            <p aria-live="polite" className="text-sm text-ink-600">
              {formatNumber(news.total)} {news.total === 1 ? 'story' : 'stories'}
            </p>

            <RevealGroup
              key={news.current_page}
              className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
              stagger={0.07}
            >
              {items.map((item) => (
                <RevealItem key={item.slug} className="h-full">
                  <NewsCard item={toNewsCard(item)} />
                </RevealItem>
              ))}
            </RevealGroup>

            <Pagination links={news.links} />
          </>
        ) : (
          <div className="card p-12 text-center">
            <p className="font-serif text-xl text-ink-900">No news yet</p>
            <p className="mx-auto mt-2 max-w-prose text-sm text-ink-600">
              Announcements, calls for papers and research highlights will appear here.
            </p>
          </div>
        )}
      </div>
    </>
  )
}
