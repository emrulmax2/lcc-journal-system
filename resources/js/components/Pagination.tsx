import { Link } from '@inertiajs/react'
import type { PaginationLink } from '@/lib/props'

/** Laravel sends its labels HTML-escaped. Decode the handful it actually uses. */
function decodeLabel(label: string): string {
  return label
    .replace(/&laquo;/g, '«')
    .replace(/&raquo;/g, '»')
    .replace(/&hellip;/g, '…')
    .replace(/&amp;/g, '&')
    .trim()
}

/**
 * The pager, shared by every paginated list.
 *
 * Takes the LINK ARRAY, not a paginator, because the two paginated payloads in this app
 * are shaped differently and only agree on this: an API-resource collection puts the
 * numbered links at `meta.links`, while a plain `->paginate()` (News) puts them at
 * top-level `links`. The caller knows which it has; this does not need to.
 */
export default function Pagination({ links }: { links: PaginationLink[] }) {
  // One page of results: [prev, 1, next]. Nothing to navigate.
  if (links.length <= 3) return null

  return (
    <nav aria-label="Pagination" className="mt-10 flex flex-wrap items-center justify-center gap-2">
      {links.map((link, i) => {
        const label = decodeLabel(link.label)
        const isPageNumber = /^\d+$/.test(label)
        const base =
          'inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-full border px-4 text-sm font-medium transition-colors duration-200'

        if (link.url === null) {
          return (
            <span
              key={`${label}-${i}`}
              aria-disabled="true"
              className={`${base} cursor-not-allowed border-ink-200 bg-white text-ink-400`}
            >
              {label}
            </span>
          )
        }

        return (
          <Link
            key={`${label}-${i}`}
            href={link.url}
            aria-current={link.active ? 'page' : undefined}
            aria-label={isPageNumber ? `Go to page ${label}` : undefined}
            className={`${base} cursor-pointer ${
              link.active
                ? 'border-brand-700 bg-brand-700 text-white'
                : 'border-ink-300 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-50'
            }`}
          >
            {label}
          </Link>
        )
      })}
    </nav>
  )
}
