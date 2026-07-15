import { Head } from '@inertiajs/react'
import { AlertTriangle } from 'lucide-react'
import { CardImage } from '@/components/ImageWithFallback'
import { Reveal } from '@/components/Reveal'
import { formatDate } from '@/lib/format'
import type { CmsPage, Meta } from '@/lib/props'

type Props = {
  page: CmsPage
  meta: Meta
}

/**
 * A CMS page: author guidelines, publication ethics, APCs, the peer review policy, privacy,
 * accessibility, contact.
 *
 * These are the pages the navbar and footer used to "link" to with a dead "#" anchor, or a
 * bounce back to the homepage. Two of them are not decoration: DOAJ requires a public peer-review
 * policy and a public APC statement, and an author decides whether to submit here after
 * reading them.
 *
 * `bodyHtml` is set with dangerouslySetInnerHTML, and that is safe HERE and only here
 * because of where it comes from: MarkdownRenderer converts editor-authored Markdown with
 * `html_input: 'escape'` and `allow_unsafe_links: false`, so raw HTML an editor typed is
 * escaped to text at the source and there is no HTML in the string that the renderer did
 * not itself emit. There is nothing to sanitise, because nothing unsafe can get in.
 */
export default function Page({ page, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      {/* An unpublished draft, visible only to a site admin. Unmissable, and never carried
          by colour alone — icon, heading and an explanation of what the reader is looking at. */}
      {page.isPreview && (
        <div className="border-b border-gold-200 bg-gold-50">
          <div className="container-page flex items-start gap-3 py-4">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-gold-600" aria-hidden="true" />
            <div>
              <p className="text-sm font-semibold text-gold-700">Draft — not published</p>
              <p className="mt-1 max-w-prose text-sm text-gold-700">
                You can see this page because you are a site administrator. It is marked{' '}
                <code className="font-mono">noindex</code>, is not linked from anywhere, and
                nobody else can reach it.
              </p>
            </div>
          </div>
        </div>
      )}

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-14">
          <Reveal>
            <h1 className="font-serif text-4xl sm:text-5xl">{page.title}</h1>
            {page.summary && (
              <p className="mt-4 max-w-prose text-lg text-ink-600">{page.summary}</p>
            )}
            {page.updatedAt && (
              <p className="mt-6 text-sm text-ink-600">
                Last updated{' '}
                <time dateTime={page.updatedAt}>{formatDate(page.updatedAt)}</time>
              </p>
            )}
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        {page.heroImage && (
          <Reveal className="mb-10">
            <figure className="max-w-3xl">
              <div className="aspect-[16/7] overflow-hidden rounded-xl">
                <CardImage
                  image={page.heroImage}
                  photo={null}
                  alt={page.heroImage.alt ?? ''}
                  width={1200}
                  height={525}
                  priority
                />
              </div>
              {(page.heroImage.caption || page.heroImage.credit) && (
                <figcaption className="mt-3 text-sm text-ink-600">
                  {page.heroImage.caption}
                  {page.heroImage.caption && page.heroImage.credit ? ' ' : ''}
                  {page.heroImage.credit && (
                    <span className="text-ink-500">{page.heroImage.credit}</span>
                  )}
                </figcaption>
              )}
            </figure>
          </Reveal>
        )}

        <Reveal>
          {/* max-w-prose: 68ch. A policy page is read, not scanned, and a 1,400px line is
              unreadable. The wrapper scrolls a wide fee table sideways rather than the page. */}
          <div
            className="prose-cms max-w-prose overflow-x-auto"
            dangerouslySetInnerHTML={{ __html: page.bodyHtml }}
          />
        </Reveal>
      </div>
    </>
  )
}
