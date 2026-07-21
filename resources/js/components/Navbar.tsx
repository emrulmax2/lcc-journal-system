import { useEffect, useRef, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import {
  ChevronDown,
  Gauge,
  LayoutDashboard,
  LayoutTemplate,
  LogOut,
  Menu,
  Search,
  UserCog,
  UserRound,
  X,
  type LucideIcon,
} from 'lucide-react'
import { easeOut } from '@/lib/motion'
import { menuItems, mediaSetting, setting, useShared } from '@/lib/props'
import type { MenuItem } from '@/lib/props'
import { peopleHref } from '@/lib/admin'
import { contentHref } from '@/lib/content'
import Logo from '@/components/Logo'

type MegaKey = 'authors' | null

/** usePage().url is "/articles?q=coral" — the path plus the query. Actives ignore the query. */
function currentPath(url: string): string {
  return url.split('?')[0].split('#')[0] || '/'
}

/**
 * Inertia's <Link> has no `isActive` render-prop, so react-router's <NavLink> pattern is
 * rebuilt here. Deliberately NOT window.location: that does not exist during SSR, and it
 * would not re-render on an Inertia visit even in the browser.
 *
 * "/" only ever matches itself — otherwise it would be a prefix of every route on the
 * site. Everything else also matches its sub-pages ("/articles" stays lit on
 * "/articles/reef-thermal-refugia") but not its lookalikes ("/articles-archive").
 */
function isActivePath(path: string, href: string): boolean {
  // An external URL is never "the page you are on".
  if (/^https?:\/\//i.test(href)) return false
  if (href === '/') return path === '/'
  return path === href || path.startsWith(`${href}/`)
}

export default function Navbar({ overHero }: { overHero: boolean }) {
  const [mega, setMega] = useState<MegaKey>(null)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [scrolled, setScrolled] = useState(false)
  const navRef = useRef<HTMLElement>(null)
  const { url } = usePage()
  const { auth, site } = useShared()

  const path = currentPath(url)

  /**
   * EVERYTHING IN THIS BAR COMES FROM THE CMS.
   *
   * It used to import a fixture module of six FICTIONAL journals carrying FABRICATED
   * impact factors (7.3, 6.1, 5.8, 6.9…) and render them, with those numbers, in a
   * "Most-read journals" panel on EVERY PAGE OF THE SITE. That file is deleted. The
   * navbar asserts no metrics at all now: the Journals page and the journal landing page
   * own that, where the figures are real and attributable.
   */
  const nav = menuItems(site, 'main')
  const authorLinks = menuItems(site, 'authors-mega')
  const siteName = setting(site, 'site_name') ?? 'JCDMS'
  const logo = mediaSetting(site, 'logo')
  const searchPlaceholder = 'Search articles'

  // Close everything on navigation. Keyed on the Inertia URL now that there is no
  // useLocation(); without this the mega menu would survive the visit it triggered.
  useEffect(() => {
    setMega(null)
    setMobileOpen(false)
  }, [url])

  // Escape closes; clicking outside the nav closes.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setMega(null)
        setMobileOpen(false)
      }
    }
    const onClick = (e: MouseEvent) => {
      if (navRef.current && !navRef.current.contains(e.target as Node)) setMega(null)
    }
    // 24px of travel before the bar solidifies — it shouldn't flicker on a trackpad nudge.
    const onScroll = () => setScrolled(window.scrollY > 24)
    onScroll() // a reload part-way down the page must not start transparent

    document.addEventListener('keydown', onKey)
    document.addEventListener('mousedown', onClick)
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      document.removeEventListener('keydown', onKey)
      document.removeEventListener('mousedown', onClick)
      window.removeEventListener('scroll', onScroll)
    }
  }, [])

  // Lock body scroll behind the mobile drawer.
  useEffect(() => {
    document.body.style.overflow = mobileOpen ? 'hidden' : ''
    return () => {
      document.body.style.overflow = ''
    }
  }, [mobileOpen])

  const submitSearch = (e: React.FormEvent) => {
    e.preventDefault()
    router.get('/articles', { q: query.trim() })
  }

  const logout = () => {
    router.post('/logout')
  }

  /**
   * `overHero` is now a prop: Layout knows which page is the Home hero, this component
   * does not (and a URL comparison here would break on a query string or trailing slash).
   *
   * The bar still solidifies once you scroll — and whenever a mega menu is open, since
   * that panel is white and would otherwise hang off a transparent bar. `scrolled` starts
   * false so the server and the first client render agree; the effect above corrects it
   * on mount for a reload part-way down the page.
   */
  const transparent = overHero && !scrolled && !mega

  const navLinkClass = (isActive: boolean) =>
    // whitespace-nowrap: without it, two-word labels ("Research Topics", "For authors")
    // wrapped to a second line and broke the bar's vertical alignment — the reported bug.
    `cursor-pointer whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 ${
      transparent
        ? isActive
          ? 'bg-white/15 text-white'
          : 'text-white/90 hover:bg-white/15 hover:text-white'
        : isActive
          ? 'text-brand-800'
          : 'text-ink-700 hover:bg-ink-100 hover:text-ink-900'
    }`

  return (
    <nav
      ref={navRef}
      aria-label="Main"
      className={`fixed inset-x-0 top-0 z-sticky border-b transition-[background-color,border-color,box-shadow] duration-300 ${
        transparent
          ? 'border-transparent bg-transparent'
          : `border-ink-200 bg-white/95 backdrop-blur ${scrolled ? 'shadow-card' : ''}`
      }`}
      onMouseLeave={() => setMega(null)}
    >
      {/* Scrim under the transparent bar: guarantees the links stay legible whatever
          the hero photo does behind them. */}
      <div
        aria-hidden="true"
        className={`pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-ink-950/70 to-transparent transition-opacity duration-300 ${
          transparent ? 'opacity-100' : 'opacity-0'
        }`}
      />

      <div className="container-page relative flex h-16 items-center gap-2 lg:h-[72px]">
        <Link
          href="/"
          className="flex shrink-0 cursor-pointer items-center"
          aria-label={`${siteName} — home`}
        >
          {/* A logo uploaded through the CMS wins; otherwise the built-in JCD&MS mark. */}
          {logo ? (
            <img
              src={logo.url}
              alt={siteName}
              className="h-9 w-auto"
              width={36}
              height={36}
              decoding="async"
            />
          ) : (
            <Logo transparent={transparent} name="responsive" />
          )}
        </Link>

        {/* Desktop nav — every link from `site.menus.main`. */}
        <div className="ml-10 hidden items-center gap-1 lg:flex xl:ml-14">
          {nav.map((item) => (
            <NavLink key={item.id} item={item} className={navLinkClass(isActivePath(path, item.url))} active={isActivePath(path, item.url)} />
          ))}

          {authorLinks.length > 0 && (
            <MegaButton
              label="For authors"
              open={mega === 'authors'}
              transparent={transparent}
              onToggle={() => setMega(mega === 'authors' ? null : 'authors')}
              onHover={() => setMega('authors')}
            />
          )}
        </div>

        {/* The action cluster never shrinks — the nav links give up space first (they can
            drop to the mobile menu), so the buttons always keep their full, one-line size. */}
        <div className="ml-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
          <form onSubmit={submitSearch} className="hidden md:block" role="search">
            <label htmlFor="nav-search" className="sr-only">
              Search articles
            </label>
            <div className="relative">
              <Search
                className={`pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 transition-colors duration-300 ${
                  transparent ? 'text-white/80' : 'text-ink-500'
                }`}
                aria-hidden="true"
              />
              <input
                id="nav-search"
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                /* Was "Search 2.9M articles". We have single digits, not millions, and the
                   number was never read from anything. */
                placeholder={searchPlaceholder}
                className={`h-11 w-40 rounded-full border pl-10 pr-4 text-sm transition-colors duration-300 lg:w-48 xl:w-56 ${
                  transparent
                    ? 'border-white/40 bg-white/10 text-white placeholder:text-white/75 backdrop-blur focus:border-white focus:bg-white/20'
                    : 'border-ink-300 bg-white text-ink-900 placeholder:text-ink-500 focus:border-brand-600'
                }`}
              />
            </div>
          </form>

          {/*
            AUTH. "Log in" used to link to /dashboard and only worked because the auth
            middleware bounced you to the login form — and once you were signed in there was
            NO WAY TO SIGN OUT anywhere in the UI, despite the POST /logout route existing.

            The name and Log out now collapse into ONE dropdown, so the bar keeps its space
            for the nav links and the Submit button.
          */}
          {auth.user ? (
            <UserMenu
              name={auth.user.name}
              transparent={transparent}
              canAccessAdmin={auth.user.canAccessAdmin}
              canManageSiteContent={auth.user.canManageSiteContent}
              canManageAccounts={auth.user.canManageAccounts}
              onLogout={logout}
            />
          ) : (
            <a
              // A PLAIN ANCHOR, not an Inertia <Link>. /login is a Blade page, outside the
              // Inertia app. An Inertia <Link> XHR-visits it, gets back HTML that is not an
              // Inertia response, and renders it in a MODAL OVERLAY — the "login popup" that
              // was reported. A normal anchor does a full navigation to the login page.
              href="/login"
              className={`btn hidden px-4 sm:inline-flex ${
                transparent
                  ? 'text-white hover:bg-white/15'
                  : 'text-ink-700 hover:bg-ink-100 hover:text-ink-900'
              }`}
            >
              Log in
            </a>
          )}

          <Link
            href="/submit"
            className={`btn hidden px-4 text-white sm:inline-flex ${
              transparent ? 'bg-brand-600 hover:bg-brand-500' : 'bg-brand-700 hover:bg-brand-800'
            }`}
          >
            Submit research
          </Link>

          <button
            type="button"
            onClick={() => setMobileOpen((o) => !o)}
            aria-expanded={mobileOpen}
            aria-controls="mobile-menu"
            aria-label={mobileOpen ? 'Close menu' : 'Open menu'}
            className={`inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full
                        transition-colors duration-200 lg:hidden ${
                          transparent
                            ? 'text-white hover:bg-white/15'
                            : 'text-ink-800 hover:bg-ink-100'
                        }`}
          >
            {mobileOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
          </button>
        </div>
      </div>

      {/* Desktop mega menu — one column of real links, from the `authors-mega` menu. */}
      <AnimatePresence>
        {mega && (
          <motion.div
            key={mega}
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -8 }}
            transition={{ duration: 0.2, ease: easeOut }}
            className="absolute inset-x-0 top-full z-dropdown hidden border-b border-ink-200 bg-white shadow-lift lg:block"
          >
            <div className="container-page py-8">
              <AuthorsMega items={authorLinks} />
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Mobile drawer */}
      <AnimatePresence>
        {mobileOpen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="fixed inset-0 z-overlay bg-ink-900/40 lg:hidden"
              onClick={() => setMobileOpen(false)}
              aria-hidden="true"
            />
            <motion.div
              id="mobile-menu"
              initial={{ x: '100%' }}
              animate={{ x: 0 }}
              exit={{ x: '100%' }}
              transition={{ duration: 0.28, ease: easeOut }}
              className="fixed right-0 top-0 z-modal flex h-full w-[86%] max-w-sm flex-col overflow-y-auto
                         bg-white p-5 shadow-lift lg:hidden"
            >
              <div className="flex items-center justify-between">
                <span className="font-serif text-lg text-ink-900">Menu</span>
                <button
                  type="button"
                  onClick={() => setMobileOpen(false)}
                  aria-label="Close menu"
                  className="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full
                             text-ink-800 transition-colors duration-200 hover:bg-ink-100"
                >
                  <X className="h-6 w-6" />
                </button>
              </div>

              <form onSubmit={submitSearch} role="search" className="mt-5">
                <label htmlFor="mobile-search" className="sr-only">
                  Search articles
                </label>
                <div className="relative">
                  <Search
                    className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500"
                    aria-hidden="true"
                  />
                  <input
                    id="mobile-search"
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder={searchPlaceholder}
                    className="h-12 w-full rounded-full border border-ink-300 pl-11 pr-5 text-base
                               placeholder:text-ink-500 focus:border-brand-600"
                  />
                </div>
              </form>

              <ul className="mt-6 space-y-1">
                {nav.map((item) => (
                  <li key={item.id}>
                    <NavLink
                      item={item}
                      active={isActivePath(path, item.url)}
                      className="flex min-h-[48px] cursor-pointer items-center rounded-lg px-3 text-base
                                 font-medium text-ink-800 transition-colors duration-200 hover:bg-ink-100"
                    />
                  </li>
                ))}
              </ul>

              {/* The "Browse by field" list is gone from both the drawer and the mega menu.
                  The fields it rendered came from the deleted fixture, and the real ones are
                  not in the shared prop — so this is a link to the journal index rather than
                  an invented list of disciplines we may or may not publish in. */}
              {authorLinks.length > 0 && (
                <div className="mt-6 space-y-1 border-t border-ink-200 pt-6">
                  <p className="eyebrow">For authors</p>
                  <ul className="space-y-1 pt-1">
                    {authorLinks.map((item) => (
                      <li key={item.id}>
                        <NavLink
                          item={item}
                          active={isActivePath(path, item.url)}
                          className="flex min-h-[44px] cursor-pointer items-center rounded-lg px-3 text-sm
                                     text-ink-600 transition-colors duration-200 hover:bg-ink-100 hover:text-ink-900"
                        />
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {/* Signed-in account links, role-gated — the same set as the desktop dropdown. */}
              {auth.user && (
                <div className="mt-6 space-y-1 border-t border-ink-200 pt-6">
                  <p className="eyebrow">{auth.user.name}</p>
                  <ul className="space-y-1 pt-1">
                    <li>
                      <Link href="/dashboard" className="flex min-h-[44px] items-center gap-2.5 rounded-lg px-3 text-sm text-ink-700 hover:bg-ink-100 hover:text-ink-900">
                        <LayoutDashboard className="h-4 w-4 text-ink-500" aria-hidden="true" />
                        Dashboard
                      </Link>
                    </li>
                    {auth.user.canAccessAdmin && (
                      <li>
                        <Link href="/admin" className="flex min-h-[44px] items-center gap-2.5 rounded-lg px-3 text-sm text-ink-700 hover:bg-ink-100 hover:text-ink-900">
                          <Gauge className="h-4 w-4 text-ink-500" aria-hidden="true" />
                          Editorial admin
                        </Link>
                      </li>
                    )}
                    {auth.user.canManageSiteContent && (
                      <li>
                        <Link href={contentHref.settings} className="flex min-h-[44px] items-center gap-2.5 rounded-lg px-3 text-sm text-ink-700 hover:bg-ink-100 hover:text-ink-900">
                          <LayoutTemplate className="h-4 w-4 text-ink-500" aria-hidden="true" />
                          Site content
                        </Link>
                      </li>
                    )}
                    {auth.user.canManageAccounts && (
                      <li>
                        <Link href={peopleHref.accounts} className="flex min-h-[44px] items-center gap-2.5 rounded-lg px-3 text-sm text-ink-700 hover:bg-ink-100 hover:text-ink-900">
                          <UserCog className="h-4 w-4 text-ink-500" aria-hidden="true" />
                          Accounts
                        </Link>
                      </li>
                    )}
                  </ul>
                </div>
              )}

              <div className="mt-auto flex flex-col gap-2 pt-8">
                <Link href="/submit" className="btn-primary w-full">
                  Submit research
                </Link>
                {auth.user ? (
                  <button type="button" onClick={logout} className="btn-secondary w-full">
                    <LogOut className="h-4 w-4" aria-hidden="true" />
                    Log out
                  </button>
                ) : (
                  // Plain anchor — /login is a Blade page, not an Inertia one (see above).
                  <a href="/login" className="btn-secondary w-full">
                    Log in
                  </a>
                )}
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </nav>
  )
}

/**
 * A menu item. `external` decides between an Inertia <Link> (a client-side visit into this
 * app) and a plain <a> (a full page load, out of it) — an Inertia visit to another origin
 * fetches HTML that is not an Inertia response and hangs.
 */
function NavLink({
  item,
  className,
  active,
}: {
  item: MenuItem
  className: string
  active: boolean
}) {
  if (item.external) {
    return (
      <a
        href={item.url}
        className={className}
        target={item.newTab ? '_blank' : undefined}
        rel={item.newTab ? 'noreferrer' : undefined}
      >
        {item.label}
      </a>
    )
  }

  return (
    <Link
      href={item.url}
      aria-current={active ? 'page' : undefined}
      className={className}
      target={item.newTab ? '_blank' : undefined}
    >
      {item.label}
    </Link>
  )
}

/**
 * The signed-in user's menu — name + Log out collapsed into one dropdown, so the bar keeps
 * its space. Self-contained: its own click-outside and Escape handling, independent of the
 * mega menu's.
 */
function UserMenu({
  name,
  transparent,
  canAccessAdmin,
  canManageSiteContent,
  canManageAccounts,
  onLogout,
}: {
  name: string
  transparent: boolean
  canAccessAdmin: boolean
  canManageSiteContent: boolean
  canManageAccounts: boolean
  onLogout: () => void
}) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onClick)
      document.removeEventListener('keydown', onKey)
    }
  }, [])

  return (
    <div ref={ref} className="relative hidden lg:block">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        aria-haspopup="menu"
        title={name}
        className={`btn max-w-[12rem] gap-2 px-3 ${
          transparent ? 'text-white hover:bg-white/15' : 'text-ink-700 hover:bg-ink-100 hover:text-ink-900'
        }`}
      >
        <UserRound className="h-5 w-5 shrink-0" aria-hidden="true" />
        <span className="truncate">{name}</span>
        <ChevronDown
          className={`h-4 w-4 shrink-0 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
          aria-hidden="true"
        />
      </button>

      <AnimatePresence>
        {open && (
          <motion.div
            role="menu"
            initial={{ opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            transition={{ duration: 0.15, ease: easeOut }}
            className="absolute right-0 z-dropdown mt-2 w-52 rounded-xl border border-ink-200 bg-white p-1.5 shadow-lift"
          >
            <MenuLink href="/dashboard" icon={LayoutDashboard} label="Dashboard" onNavigate={() => setOpen(false)} />

            {/* Role-gated. Only shown when the destination would actually admit them — the
                server re-checks, so a hidden link is a courtesy, not the control. */}
            {canAccessAdmin && (
              <MenuLink href="/admin" icon={Gauge} label="Editorial admin" onNavigate={() => setOpen(false)} />
            )}
            {canManageSiteContent && (
              <MenuLink href={contentHref.settings} icon={LayoutTemplate} label="Site content" onNavigate={() => setOpen(false)} />
            )}
            {canManageAccounts && (
              <MenuLink href={peopleHref.accounts} icon={UserCog} label="Accounts" onNavigate={() => setOpen(false)} />
            )}

            <div className="my-1.5 border-t border-ink-100" />

            <button
              type="button"
              role="menuitem"
              onClick={() => {
                setOpen(false)
                onLogout()
              }}
              className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium text-danger-700 transition-colors duration-200 hover:bg-danger-50"
            >
              <LogOut className="h-4 w-4" aria-hidden="true" />
              Log out
            </button>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}

/** One row in the user dropdown — an Inertia link with an icon. */
function MenuLink({
  href,
  icon: Icon,
  label,
  onNavigate,
}: {
  href: string
  icon: LucideIcon
  label: string
  onNavigate: () => void
}) {
  return (
    <Link
      href={href}
      role="menuitem"
      onClick={onNavigate}
      className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium text-ink-700 transition-colors duration-200 hover:bg-ink-100 hover:text-ink-900"
    >
      <Icon className="h-4 w-4 text-ink-500" aria-hidden="true" />
      {label}
    </Link>
  )
}

function MegaButton({
  label,
  open,
  transparent,
  onToggle,
  onHover,
}: {
  label: string
  open: boolean
  transparent: boolean
  onToggle: () => void
  onHover: () => void
}) {
  return (
    <button
      type="button"
      onClick={onToggle}
      onMouseEnter={onHover}
      aria-expanded={open}
      className={`inline-flex cursor-pointer items-center gap-1 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium
                  transition-colors duration-200 ${
                    transparent
                      ? 'text-white/90 hover:bg-white/15 hover:text-white'
                      : open
                        ? 'bg-ink-100 text-ink-900'
                        : 'text-ink-700 hover:bg-ink-100 hover:text-ink-900'
                  }`}
    >
      {label}
      <ChevronDown
        className={`h-4 w-4 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
        aria-hidden="true"
      />
    </button>
  )
}

/**
 * The author mega-menu. Label AND description come from the menu item — the description is
 * a CMS field, not a sentence written here.
 *
 * The promo card that used to sit on the right is GONE. It read "Median 51 days to first
 * decision" (a number computed from nothing, which contradicted the four different medians
 * on the homepage) over "Collaborative peer review with named reviewers, published
 * alongside every accepted article" — the exact opposite of what the platform does. Review
 * is SINGLE-BLIND: reviewer identities are withheld from authors and no report is
 * published. See app/Services/../SubmissionPresenter and the seeded peer-review policy.
 */
function AuthorsMega({ items }: { items: MenuItem[] }) {
  if (items.length === 0) return null

  const className =
    'block h-full cursor-pointer rounded-lg border border-transparent p-3 transition-colors duration-200 hover:border-ink-200 hover:bg-ink-50'

  return (
    <div>
      <p className="eyebrow">Publish with us</p>
      <ul className="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        {items.map((item) => {
          const body = (
            <>
              <span className="block text-sm font-semibold text-ink-900">{item.label}</span>
              {item.description && (
                <span className="mt-0.5 block text-xs text-ink-600">{item.description}</span>
              )}
            </>
          )

          return (
            <li key={item.id}>
              {item.external ? (
                <a
                  href={item.url}
                  className={className}
                  target={item.newTab ? '_blank' : undefined}
                  rel={item.newTab ? 'noreferrer' : undefined}
                >
                  {body}
                </a>
              ) : (
                <Link
                  href={item.url}
                  className={className}
                  target={item.newTab ? '_blank' : undefined}
                >
                  {body}
                </Link>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}
