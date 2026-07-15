import { Head, Link } from '@inertiajs/react'
import {
  CheckCircle2,
  ExternalLink,
  FileText,
  Lock,
  PencilLine,
  Plus,
  Clock,
} from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { EmptyState, Panel } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { formatDate } from '@/lib/format'
import { contentHref, type ContentPage, type PageRow } from '@/lib/content'

/**
 * The content pages: author guidelines, publication ethics, APCs, privacy, accessibility.
 *
 * Every one of these was a navbar or footer link that went to "#" or to the homepage. An
 * "Accessibility statement" link that goes nowhere is not a missing feature — it is itself an
 * accessibility failure. "Article processing charges" is the link an author clicks to find out
 * what it costs.
 *
 * A SYSTEM PAGE HAS NO DELETE CONTROL. Not a disabled one: none. The footer links to it
 * structurally, Page::booted() refuses the delete, and offering a button the model will refuse
 * teaches editors that the refusal is a bug.
 */

type Props = ContentPage<{ pages: PageRow[] }>

export default function ContentPages({ pages, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Pages"
        description="Guidelines, policies and legal pages. These are the pages the footer and the mega-menu point at."
        actions={
          <Link href={contentHref.newPage} className="btn-primary">
            <Plus className="h-4 w-4" aria-hidden="true" />
            New page
          </Link>
        }
      >
        {pages.length === 0 ? (
          <EmptyState
            icon={FileText}
            title="No pages yet"
            body="A page is markdown, rendered server-side, at a permanent URL. The footer's legal links point at these."
          >
            <Link href={contentHref.newPage} className="btn-primary">
              <Plus className="h-4 w-4" aria-hidden="true" />
              Create the first page
            </Link>
          </EmptyState>
        ) : (
          <Panel
            title={`${pages.length} ${pages.length === 1 ? 'page' : 'pages'}`}
            description="A page is live only when its status is Published AND it has a publication date in the past. Both, or it is not on the site."
          >
            <RevealGroup as="ul" className="divide-y divide-ink-200" stagger={0.04}>
              {pages.map((page) => (
                <RevealItem as="li" key={page.id} className="flex flex-wrap items-start gap-4 py-4">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-3">
                      <Link
                        href={contentHref.editPage(page.id)}
                        className="cursor-pointer font-serif text-lg text-ink-900 transition-colors duration-200 hover:text-brand-800"
                      >
                        {page.title}
                      </Link>

                      <PageStatusBadge page={page} />

                      {page.isSystem && (
                        <span
                          className="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-semibold text-ink-700"
                          title="The site navigation links to this page. It cannot be deleted, only unpublished."
                        >
                          <Lock className="h-3.5 w-3.5" aria-hidden="true" />
                          System page
                        </span>
                      )}
                    </div>

                    <p className="mt-1 font-mono text-xs text-ink-600">{page.url}</p>

                    {page.summary && (
                      <p className="mt-1.5 max-w-prose text-sm text-ink-600">{page.summary}</p>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
                    {page.isPublished && (
                      <a
                        href={page.url}
                        target="_blank"
                        rel="noreferrer"
                        className="btn-ghost px-3 py-1.5 text-xs"
                      >
                        <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                        View
                      </a>
                    )}

                    <Link
                      href={contentHref.editPage(page.id)}
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

/** Icon AND word. A page that is "published" but has no date is NOT live, and says so. */
function PageStatusBadge({ page }: { page: PageRow }) {
  if (page.isPublished) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-semibold text-success-800">
        <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
        Live
      </span>
    )
  }

  if (page.status === 'published' && page.publishedAt) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[11px] font-semibold text-gold-700">
        <Clock className="h-3.5 w-3.5" aria-hidden="true" />
        Scheduled — {formatDate(page.publishedAt)}
      </span>
    )
  }

  if (page.status === 'published') {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[11px] font-semibold text-gold-700">
        <Clock className="h-3.5 w-3.5" aria-hidden="true" />
        Published, but no date — not live
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
