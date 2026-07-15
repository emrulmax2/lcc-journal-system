import { Head, Link } from '@inertiajs/react'
import { ArrowLeft } from 'lucide-react'
import { NewsCard } from '@/components/Cards'
import { CardImage } from '@/components/ImageWithFallback'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { formatLongDate } from '@/lib/format'
import { toNewsCard } from '@/lib/props'
import type { Meta, NewsItem as NewsItemData, NewsItemDetail } from '@/lib/props'

type Props = {
  item: NewsItemDetail
  related: NewsItemData[]
  meta: Meta
}

/**
 * A news story. `bodyHtml` is finished, safe HTML from MarkdownRenderer — see Page.tsx for
 * why dangerouslySetInnerHTML is the correct call on it rather than a risk taken.
 */
export default function NewsItem({ item, related, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <article>
        <header className="border-b border-ink-200 bg-ink-50">
          <div className="container-page py-12">
            <Link
              href="/news"
              className="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-ink-600
                         transition-colors duration-200 hover:text-brand-800"
            >
              <ArrowLeft className="h-4 w-4" aria-hidden="true" />
              All news
            </Link>

            <Reveal className="mt-6 max-w-3xl">
              <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-800">
                  {item.category}
                </span>
                {item.date && (
                  <time dateTime={item.date} className="text-ink-600">
                    {formatLongDate(item.date)}
                  </time>
                )}
              </div>

              <h1 className="mt-4 font-serif text-3xl leading-tight sm:text-4xl lg:text-5xl">
                {item.title}
              </h1>

              {item.excerpt && (
                <p className="mt-5 max-w-prose text-lg leading-relaxed text-ink-600">
                  {item.excerpt}
                </p>
              )}

              {/* The author is nullable — a story written by the editorial office has none,
                  and an empty "By" line reads as a bug. */}
              {item.author && <p className="mt-5 text-sm text-ink-700">By {item.author}</p>}
            </Reveal>
          </div>
        </header>

        <div className="container-page py-12">
          {(item.image || item.photo) && (
            <Reveal className="mb-10">
              <figure className="max-w-3xl">
                <div className="aspect-[16/8] overflow-hidden rounded-xl">
                  <CardImage
                    image={item.image}
                    photo={item.photo}
                    alt=""
                    width={1200}
                    height={600}
                    priority
                  />
                </div>
                {item.image && (item.image.caption || item.image.credit) && (
                  <figcaption className="mt-3 text-sm text-ink-600">
                    {item.image.caption}
                    {item.image.caption && item.image.credit ? ' ' : ''}
                    {item.image.credit && (
                      <span className="text-ink-500">{item.image.credit}</span>
                    )}
                  </figcaption>
                )}
              </figure>
            </Reveal>
          )}

          <Reveal>
            <div
              className="prose-cms max-w-prose overflow-x-auto"
              dangerouslySetInnerHTML={{ __html: item.bodyHtml }}
            />
          </Reveal>
        </div>

        {related.length > 0 && (
          <section className="bg-ink-50 py-16">
            <div className="container-page">
              <Reveal>
                <h2 className="font-serif text-2xl">More from the newsroom</h2>
              </Reveal>

              <RevealGroup className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {related.map((r) => (
                  <RevealItem key={r.slug} className="h-full">
                    <NewsCard item={toNewsCard(r)} />
                  </RevealItem>
                ))}
              </RevealGroup>
            </div>
          </section>
        )}
      </article>
    </>
  )
}
