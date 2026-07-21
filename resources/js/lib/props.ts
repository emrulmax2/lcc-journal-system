import { usePage } from '@inertiajs/react'
import type {
  ArticleCardData,
  JournalCardData,
  NewsCardData,
  TopicCardData,
} from '@/components/Cards'
import type { PhotoKey } from '@/lib/images'

/**
 * The Inertia page-prop contract for the PUBLIC pages.
 *
 * These types are derived from the PHP — App\Http\Resources\{Article,Journal}Resource and
 * the App\Http\Controllers\Public\* controllers — NOT from the old demo fixture module,
 * which is deleted. Where the PHP can return null (an unpublished article has no date, a
 * journal without a JCR entry has no impact factor, a DOI does not exist until Crossref
 * issues a prefix, an ISSN does not exist until the British Library issues one), the type
 * says so, so that the compiler forces the page to render a fallback rather than `0`,
 * `undefined` or a broken link.
 */

export type Meta = {
  title: string
  description: string
}

/**
 * A real, uploaded asset. Resolved server-side from a media id, so the frontend never
 * builds a storage URL itself.
 *
 * `alt: ''` means "decorative, deliberately". `alt: null` means nobody has said yet —
 * which is why Media::needsAltText() exists and why this is nullable rather than a string.
 */
export type MediaImage = {
  url: string
  alt: string | null
  caption: string | null
  credit: string | null
}

/* -------------------------------------------------------------------------- *
 * The SHARED props — HandleInertiaRequests::share(). On every page.
 * -------------------------------------------------------------------------- */

export type MenuItem = {
  id: number
  label: string
  description: string | null
  /** Always a resolved, real destination. MenuItem::saving() makes "#" impossible. */
  url: string
  external: boolean
  newTab: boolean
  children: MenuItem[]
}

export type Menu = {
  name: string
  location: string
  items: MenuItem[]
}

/** A `media` setting resolves to a MediaImage; everything else is a string or null. */
export type SettingValue = string | MediaImage | null

export type Site = {
  settings: Record<string, SettingValue | undefined>
  menus: Record<string, Menu | undefined>
}

export type AuthUser = {
  id: number
  name: string
  email: string
  /** Holds an editorial role on at least one journal — the /admin gate. Decides the "Editorial admin" link. */
  canAccessAdmin: boolean
  /** May edit site-wide content (the CMS) — the manage-site-content gate. Decides the "Site content" link. */
  canManageSiteContent: boolean
  /** May manage user accounts site-wide — the manage-users gate. Decides the "Accounts" link. */
  canManageAccounts: boolean
}

export type SharedProps = {
  auth: { user: AuthUser | null }
  flash: {
    success?: string | null
    error?: string | null
  }
  /**
   * The SERVER's "now", as an ISO string. Use this and never `new Date()` for anything
   * that reaches the DOM: the SSR process and the browser render at different instants,
   * and a year or a date derived from the client clock disagrees with the server's across
   * a midnight, a timezone boundary or a New Year — which is a hydration mismatch on the
   * copyright line of every page.
   */
  now: string
  site: Site
  errors: Record<string, string>

  /** The locale in force, e.g. 'en'. Set server-side by SetLocale. */
  locale: string
  /** The languages the switcher offers. */
  locales: { code: string; name: string }[]
  /** The full message bag for the current locale — a nested { group: { key: value } } tree. */
  translations: Record<string, unknown>
}

/**
 * The shared props, on any page. An empty `site` is returned rather than throwing, so a
 * component is never the thing that takes the page down when a setting has not been seeded.
 */
export function useShared(): SharedProps {
  const props = usePage().props as unknown as Partial<SharedProps>

  return {
    auth: props.auth ?? { user: null },
    flash: props.flash ?? {},
    now: props.now ?? '',
    site: props.site ?? { settings: {}, menus: {} },
    errors: props.errors ?? {},
    locale: props.locale ?? 'en',
    locales: props.locales ?? [],
    translations: props.translations ?? {},
  }
}

/** A text setting. Empty strings collapse to null so `?? fallback` behaves. */
export function setting(site: Site, key: string): string | null {
  const value = site.settings[key]
  if (typeof value !== 'string') return null
  const trimmed = value.trim()
  return trimmed === '' ? null : trimmed
}

/** A media setting, already resolved to a URL by SiteContent. */
export function mediaSetting(site: Site, key: string): MediaImage | null {
  const value = site.settings[key]
  if (value === null || value === undefined || typeof value === 'string') return null
  return typeof value.url === 'string' && value.url !== '' ? value : null
}

/** A boolean setting. Absent means OFF — a form that mails people does not default to on. */
export function boolSetting(site: Site, key: string): boolean {
  const value = setting(site, key)
  return value === '1' || value === 'true' || value === 'on' || value === 'yes'
}

/** A menu's items. An unseeded menu is an empty list, never a crash. */
export function menuItems(site: Site, key: string): MenuItem[] {
  return site.menus[key]?.items ?? []
}

/* -------------------------------------------------------------------------- *
 * Page props
 * -------------------------------------------------------------------------- */

/** JournalResource. */
export type Journal = {
  slug: string
  title: string
  abbreviation: string | null
  field: string | null
  description: string | null
  /** A real, uploaded cover. Takes precedence over `photo` — see toJournalCard(). */
  coverImage: MediaImage | null
  photo: PhotoKey | null
  openAccess: boolean
  publicationModel: 'continuous' | 'issue_based'

  // Externally sourced (JCR / Scopus). NULL until the journal is indexed — which for a
  // launch journal is the normal state, not an error. Render '—', never 0.
  impactFactor: number | null
  citeScore: number | null
  metricsExternalAsOf: string | null

  // Computed from our own data on a schedule.
  acceptanceRate: number | null
  medianDaysToDecision: number | null
  articles: number
  editors: number
  metricsComputedAt: string | null
}

/** JournalShowController — the journal landing page. */
export type JournalDetail = {
  slug: string
  title: string
  abbreviation: string | null
  field: string | null
  description: string | null
  aimsAndScopeHtml: string
  publisher: string | null
  principalEditor: string | null
  contactEmail: string | null
  /** NULL until the British Library issues one. Say so; never print "0000-0000". */
  issnOnline: string | null
  issnPrint: string | null
  openAccess: boolean
  license: string | null
  publicationModel: 'continuous' | 'issue_based'
  sections: string[]
  coverImage: MediaImage | null
  photo: PhotoKey | null
  metrics: {
    // Externally sourced — JCR / Scopus. Not ours to compute.
    impactFactor: number | null
    citeScore: number | null
    externalAsOf: string | null
    // Computed by us, from our own decision data.
    acceptanceRate: number | null
    medianDaysToDecision: number | null
    articleCount: number
    editorCount: number
    computedAt: string | null
  }
}

/** ArticleResource — the card/list shape. */
export type Article = {
  slug: string
  title: string
  /** Flat display names. A CORPORATE author arrives as a single-element array. */
  authors: string[]
  journal: string
  journalSlug: string
  type: string | null
  /** ISO date, or null while the article is unpublished. */
  date: string | null
  /** NULL until Crossref issues a prefix for the journal. */
  doi: string | null
  doiUrl: string | null
  views: number
  citations: number
  photo: PhotoKey | null
  abstract: string | null
  keywords: string[]
}

export type AuthorDetail = {
  name: string
  affiliation: string | null
  orcid: string | null
  orcidUrl: string | null
  isCorresponding: boolean
}

export type Reference = {
  ordinal: number
  text: string
  doi: string | null
}

/** ArticleResource + the detail-only fields merged in by ArticleController::show(). */
export type ArticleDetail = Article & {
  body: string | null
  pageRange: string | null
  volume: number | null
  issue: number | null
  license: string | null
  licenseHolder: string | null
  hasPdf: boolean
  pdfUrl: string | null
  /** The crawlable HTML full-text page (Blade-rendered). Null when the article has no body. */
  hasHtmlFullText: boolean
  htmlUrl: string | null
  /** True when an editor is previewing an unpublished draft. Never true for the public. */
  isPreview: boolean
  /** EMPTY when the article has a corporate author. */
  authorDetails: AuthorDetail[]
  corporateAuthor: string | null
  references: Reference[]

  /**
   * NOT YET SENT BY ArticleController::show().
   *
   * The page renders a figure only when a real, uploaded asset with its own alt, caption
   * and credit is present. Until the controller sends one, no figure is rendered — the
   * stock photo that used to sit here was captioned "Figure 1. Representative imagery
   * from the study site", which asserted that an Unsplash photograph came from the
   * research it illustrated.
   */
  heroImage?: MediaImage | null

  /**
   * NOT YET SENT either. The open-access badge is a claim about the JOURNAL's licence and
   * must be gated on it. Absent -> no badge, rather than an unconditional one.
   */
  journalOpenAccess?: boolean | null
}

/** CitationFormatter::all() */
export type Citations = {
  harvard: string
  bibtex: string
  ris: string
}

export type CitationFormat = keyof Citations

/** NewsController::card() and HomeController's `news`. */
export type NewsItem = {
  slug: string
  title: string
  category: string
  date: string | null
  excerpt: string | null
  /** Resolved by the controller. Never "#". */
  url: string
  /** A real, uploaded asset. Takes precedence over `photo`. */
  image: MediaImage | null
  /** Legacy Unsplash key. May be null, and may be a key we do not know. */
  photo: PhotoKey | null
}

/** NewsController::show(). */
export type NewsItemDetail = NewsItem & {
  bodyHtml: string
  author: string | null
}

/** ResearchTopicController::card(). HomeController sends a subset (see HomeTopic). */
export type ResearchTopic = {
  slug: string
  title: string
  description: string | null
  deadline: string | null
  /** The deadline is the most important fact on the card, so the server states it plainly. */
  isOpen: boolean
  hasClosed: boolean
  journal: { slug: string; title: string } | null
  editorCount: number
  url: string
  image: MediaImage | null
  photo: PhotoKey | null
}

export type ResearchTopicDetail = ResearchTopic & {
  bodyHtml: string
  submissionEmail: string | null
  editors: { name: string; affiliation: string | null; orcidUrl: string | null }[]
}

/**
 * HomeController sends a LEANER topic than ResearchTopicController: `editors` (a count),
 * and no isOpen/hasClosed. Typed separately rather than pretending the two are the same
 * shape — the homepage card must not read a field the homepage controller never sends.
 */
export type HomeTopic = {
  slug: string
  title: string
  description: string | null
  deadline: string | null
  editors: number
  url: string
  image: MediaImage | null
  photo: PhotoKey | null
}

/** Page — CMS page from PageController::show(). */
export type CmsPage = {
  slug: string
  title: string
  summary: string | null
  /**
   * Finished, SAFE HTML. Rendered server-side by MarkdownRenderer with
   * `html_input: 'escape'`, so raw HTML an editor typed is escaped at source and there is
   * nothing here to sanitise. This is why dangerouslySetInnerHTML is acceptable on it.
   */
  bodyHtml: string
  updatedAt: string | null
  heroImage: MediaImage | null
  /** An unpublished draft, visible only to a site admin, and marked noindex. */
  isPreview: boolean
}

/* -------------------------------------------------------------------------- *
 * Home sections — the CMS-driven editorial copy.
 * -------------------------------------------------------------------------- */

export type HomeSectionItem = {
  /** A Lucide component NAME, from HomeSectionItem::ICONS. Resolve via lib/icons. */
  icon: string | null
  title: string
  body: string | null
  /** NULL for every pipeline step — the four invented medians are gone. Render if present. */
  meta: string | null
  ctaLabel: string | null
  url: string | null
}

export type HomeSection = {
  eyebrow: string | null
  heading: string | null
  blurb: string | null
  image: MediaImage | null
  items: HomeSectionItem[]
}

export type HomeSectionKey =
  | 'author-paths'
  | 'featured-journals'
  | 'how-it-works'
  | 'research-topics'
  | 'impact'
  | 'latest-research'
  | 'newsroom'

/**
 * An editor can hide a section, and a hidden section is simply ABSENT from this map — so
 * every read is optional and a missing section renders nothing at all, rather than an
 * empty band with a heading and no content.
 */
export type HomeSections = Partial<Record<HomeSectionKey, HomeSection>>

export type Stat = {
  label: string
  value: number
  suffix: string
  decimals: number
}

/**
 * Laravel's paginated API-resource envelope.
 *
 * NOTE the shape carefully: with a ResourceCollection, top-level `links` is an OBJECT
 * (first/last/prev/next) and the NUMBERED page links live at `meta.links`. That is not
 * the same as a bare `->paginate()` payload, where `links` is the numbered array.
 */
export type PaginationLink = {
  url: string | null
  label: string
  active: boolean
}

export type Paginated<T> = {
  data: T[]
  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta: {
    current_page: number
    from: number | null
    last_page: number
    links: PaginationLink[]
    path: string
    per_page: number
    to: number | null
    total: number
  }
}

/**
 * `->through()` on a LengthAwarePaginator (NewsController::index) keeps Laravel's OWN
 * paginator shape, not an API-resource envelope: `links` is the NUMBERED array and the
 * counts sit at the top level. Typed separately, because reading `meta.links` off one of
 * these gets you `undefined` and a blank pager with no error anywhere.
 */
export type SimplePaginated<T> = {
  data: T[]
  links: PaginationLink[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

/* -------------------------------------------------------------------------- *
 * Card adapters.
 *
 * The card contracts in components/Cards.tsx are STRICTER than the PHP: they declare
 * `type`, `abstract`, `field`, `description` and `excerpt` as non-nullable, but every one
 * of those columns is nullable in the schema —
 *   articles.journal_section_id  nullable  -> Article.type    can be null
 *   articles.abstract            nullable  -> Article.abstract
 *   journals.field_id            nullable  -> Journal.field
 *   journals.description         nullable  -> Journal.description
 *   news_items.excerpt           nullable  -> NewsItem.excerpt
 *
 * The page types above stay honest to the backend, and the gap is closed HERE, in one
 * place, rather than by lying in six.
 * -------------------------------------------------------------------------- */

export function toArticleCard(article: Article): ArticleCardData {
  return {
    ...article,
    // An article filed under no section is still an article. Never an empty pill.
    type: article.type ?? 'Article',
    abstract: article.abstract ?? '',
  }
}

export function toJournalCard(journal: Journal): JournalCardData {
  return {
    ...journal,
    field: journal.field ?? '—',
    description: journal.description ?? '',
    // The card calls it `image`, the resource calls it `coverImage`. The rename happens
    // here, in the adapter, like every other card. It used to happen NOWHERE, so
    // JournalCard hardcoded `image={null}` and every uploaded cover on /journals and the
    // homepage rendered as the neutral placeholder.
    image: journal.coverImage ?? null,
  }
}

export function toNewsCard(item: NewsItem): NewsCardData {
  return {
    ...item,
    excerpt: item.excerpt ?? '',
  }
}

/** Both topic shapes reduce to the same card: a deadline, an editor count and a real URL. */
export function toTopicCard(topic: HomeTopic | ResearchTopic): TopicCardData {
  return {
    slug: topic.slug,
    title: topic.title,
    url: topic.url,
    deadline: topic.deadline,
    editors: 'editors' in topic ? topic.editors : topic.editorCount,
    isOpen: 'isOpen' in topic ? topic.isOpen : null,
    hasClosed: 'hasClosed' in topic ? topic.hasClosed : null,
    image: topic.image,
    photo: topic.photo,
  }
}
