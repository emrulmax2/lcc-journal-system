import { type ReactNode } from 'react'
import { Link, usePage } from '@inertiajs/react'
import {
  ArrowLeft,
  Image,
  LayoutTemplate,
  Link2,
  Mail,
  Newspaper,
  Settings2,
  FileText,
  Compass,
  type LucideIcon,
} from 'lucide-react'
import { Flash } from '@/components/admin/Shell'
import { Reveal } from '@/components/Reveal'
import { contentHref } from '@/lib/content'

/**
 * The chrome for the CMS screens.
 *
 * It is NOT AdminShell, and that is not a copy-paste oversight. AdminShell's tabs are a
 * journal's tabs and its header carries the journal's name — it cannot render without a
 * journal. Site content HAS no journal: the privacy policy, the footer and the navigation
 * belong to the site, and a publisher-admin editing them is not editing JCD&MS. Forcing a
 * journal in here to satisfy a component would be inventing a relationship the data does not
 * have, and the first bug it produces is a site admin with no journals seeing a 404 on the
 * privacy policy.
 *
 * Same product, same tokens, same Lucide icons, same tab shape. Different subject.
 *
 * NO framer `initial` variant wraps any of this. <Reveal> renders VISIBLE on the server and
 * only hides what is below the fold, on the client, after mount.
 */

type Tab = { label: string; href: string; icon: LucideIcon }

const TABS: Tab[] = [
  { label: 'Settings', href: contentHref.settings, icon: Settings2 },
  { label: 'Pages', href: contentHref.pages, icon: FileText },
  { label: 'Navigation', href: contentHref.menus, icon: Link2 },
  { label: 'Homepage', href: contentHref.home, icon: LayoutTemplate },
  { label: 'News', href: contentHref.news, icon: Newspaper },
  { label: 'Research Topics', href: contentHref.topics, icon: Compass },
  { label: 'Media', href: contentHref.media, icon: Image },
  { label: 'Newsletter', href: contentHref.newsletter, icon: Mail },
]

export function ContentShell({
  title,
  description,
  actions,
  children,
}: {
  title: string
  description?: string
  actions?: ReactNode
  children: ReactNode
}) {
  const { url } = usePage()
  const path = url.split('?')[0]

  return (
    <>
      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page pt-10">
          <Reveal className="flex flex-wrap items-end justify-between gap-6">
            <div className="min-w-0">
              <p className="eyebrow">Site content</p>
              <h1 className="mt-3 font-serif text-3xl sm:text-4xl">{title}</h1>
              {description && <p className="mt-3 max-w-prose text-ink-600">{description}</p>}
            </div>
            <div className="flex flex-wrap items-center gap-3">
              {actions}
              <Link href="/admin" className="btn-ghost">
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Editorial admin
              </Link>
            </div>
          </Reveal>

          <nav aria-label="Site content" className="mt-8 flex flex-wrap gap-1 overflow-x-auto">
            {TABS.map((tab) => {
              const active = path.startsWith(tab.href)
              const Icon = tab.icon

              return (
                <Link
                  key={tab.href}
                  href={tab.href}
                  aria-current={active ? 'page' : undefined}
                  className={`inline-flex cursor-pointer items-center gap-2 rounded-t-lg border-b-2 px-4 py-3
                              text-sm font-semibold transition-colors duration-200 ${
                                active
                                  ? 'border-brand-700 text-brand-800'
                                  : 'border-transparent text-ink-600 hover:border-ink-300 hover:text-ink-900'
                              }`}
                >
                  <Icon className="h-4 w-4" aria-hidden="true" />
                  {tab.label}
                </Link>
              )
            })}
          </nav>
        </div>
      </header>

      <div className="container-page py-10">
        <Flash />
        {children}
      </div>
    </>
  )
}
