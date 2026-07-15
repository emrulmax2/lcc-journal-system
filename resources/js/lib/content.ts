import type { Meta } from '@/lib/props'

/**
 * The Inertia page-prop contract for the CMS ADMIN (App\Http\Controllers\Admin\Content\*).
 *
 * Two shapes here are load-bearing, and the types say so:
 *
 *  1. `MediaItem.alt` is `string | null`, and the two are DIFFERENT STATEMENTS.
 *     '' means "decorative — a screen reader should skip this", said deliberately by a human.
 *     null means nobody has said, and that must never reach a public page. A `string` type
 *     would let the compiler wave through the one that is an accessibility failure.
 *
 *  2. `MenuItemRow.url` is always a real, resolved URL — never "#". The whole menus table
 *     exists because seventeen footer links were hardcoded and nine of them went nowhere.
 */

export type ContentPage<T> = T & { meta: Meta }

/* ---------------------------------- Media ---------------------------------- */

export type MediaItem = {
  id: number
  url: string
  name: string
  /** '' = decorative, deliberately. null = nobody has said. NOT the same thing. */
  alt: string | null
  caption: string | null
  credit: string | null
  width: number | null
  height: number | null
  sizeBytes: number
  mimeType: string
  /** alt IS NULL on an image. It must not go on a public page until someone says something. */
  needsAltText: boolean
  isDecorative: boolean
  uploadedAt: string | null
}

/* --------------------------------- Settings -------------------------------- */

export type SettingType =
  | 'text'
  | 'textarea'
  | 'markdown'
  | 'url'
  | 'email'
  | 'media'
  | 'boolean'

export type SettingRow = {
  key: string
  type: SettingType
  label: string
  /** The warnings on footer_copyright and footer_blurb are real. Render them. */
  help: string | null
}

export type SettingGroup = {
  key: string
  label: string
  description: string | null
  settings: SettingRow[]
}

/* ---------------------------------- Pages ---------------------------------- */

export type PageStatus = 'draft' | 'published'

export type PageRow = {
  id: number
  title: string
  slug: string
  summary: string | null
  status: PageStatus
  publishedAt: string | null
  /** status = published AND published_at set AND in the past. All three, or it is not live. */
  isPublished: boolean
  /** The footer links to it structurally. It cannot be deleted, only unpublished. */
  isSystem: boolean
  url: string
  updatedAt: string | null
}

/* ---------------------------------- Menus ---------------------------------- */

export type Destination = 'page' | 'route' | 'external'

export type MenuItemRow = {
  id: number
  label: string
  description: string | null
  destination: Destination
  pageId: number | null
  routeName: string | null
  externalUrl: string | null
  parentId: number | null
  opensInNewTab: boolean
  isActive: boolean
  sequence: number
  /** WHERE IT ACTUALLY GOES. Resolved on the server. Never "#". */
  url: string
  /** A live menu link into a draft page is a 404 for every reader. */
  pointsAtDraft: boolean
}

export type MenuRow = {
  id: number
  key: string
  name: string
  location: string
  items: MenuItemRow[]
}

export type RouteOption = { name: string; path: string }
export type PageOption = { id: number; title: string; url: string }

/* ------------------------------- Home sections ------------------------------ */

export type HomeSectionItemRow = {
  id: number
  icon: string | null
  title: string
  body: string | null
  /** A figure an editor OWNS. The prototype invented four of them. */
  meta: string | null
  ctaLabel: string | null
  routeName: string | null
  externalUrl: string | null
  url: string | null
}

export type HomeSectionRow = {
  id: number
  key: string
  name: string
  eyebrow: string | null
  heading: string | null
  blurb: string | null
  mediaId: number | null
  isVisible: boolean
  sequence: number
  items: HomeSectionItemRow[]
}

/* ---------------------------------- People ---------------------------------- */

export type UserOption = { id: number; name: string; email: string }

/* ---------------------------------- Hrefs ----------------------------------- */

export const contentHref = {
  settings: '/admin/content/settings',
  pages: '/admin/content/pages',
  newPage: '/admin/content/pages/create',
  editPage: (id: number) => `/admin/content/pages/${id}/edit`,
  menus: '/admin/content/menus',
  home: '/admin/content/home',
  news: '/admin/content/news',
  newNews: '/admin/content/news/create',
  editNews: (id: number) => `/admin/content/news/${id}/edit`,
  topics: '/admin/content/topics',
  newTopic: '/admin/content/topics/create',
  editTopic: (id: number) => `/admin/content/topics/${id}/edit`,
  media: '/admin/content/media',
  newsletter: '/admin/content/newsletter',
  newsletterExport: '/admin/content/newsletter/export',
  preview: '/admin/content/preview',
} as const

/** "A title" -> "a-title". Suggests; never overrides what an editor typed. */
export function slugify(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '') // strip the combining marks NFD just split off
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 255)
}
