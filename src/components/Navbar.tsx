import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import { ChevronDown, Menu, Search, X } from 'lucide-react'
import { FIELDS, JOURNALS } from '@/lib/data'
import { easeOut } from '@/lib/motion'

type MegaKey = 'journals' | 'authors' | null

const AUTHOR_LINKS = [
  { label: 'Submit your research', to: '/submit', desc: 'Start a submission in any journal' },
  { label: 'Author guidelines', to: '/submit', desc: 'Formatting, ethics and data policy' },
  { label: 'Article processing charges', to: '/submit', desc: 'Fees, waivers and funder deals' },
  { label: 'Track your manuscript', to: '/dashboard', desc: 'Live status and reviewer reports' },
]

export default function Navbar() {
  const [mega, setMega] = useState<MegaKey>(null)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [scrolled, setScrolled] = useState(false)
  const navRef = useRef<HTMLElement>(null)
  const navigate = useNavigate()
  const { pathname } = useLocation()

  // Close everything on navigation.
  useEffect(() => {
    setMega(null)
    setMobileOpen(false)
  }, [pathname])

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
    navigate(`/articles?q=${encodeURIComponent(query.trim())}`)
  }

  /**
   * On the homepage the bar floats over the hero photo so the image runs full-bleed.
   * It solidifies once you scroll — and also whenever a mega menu is open, since that
   * panel is white and would otherwise hang off a transparent bar.
   */
  const overHero = pathname === '/' && !scrolled && !mega

  const navLinkClass = ({ isActive }: { isActive: boolean }) =>
    `cursor-pointer rounded-md px-3 py-2 text-sm font-medium transition-colors duration-200 ${
      overHero
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
        overHero
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
          overHero ? 'opacity-100' : 'opacity-0'
        }`}
      />

      <div className="container-page relative flex h-16 items-center gap-2 lg:h-[72px]">
        <Link
          to="/"
          className="flex shrink-0 cursor-pointer items-center gap-2.5"
          aria-label="Meridian — home"
        >
          <svg
            viewBox="0 0 24 24"
            className={`h-8 w-8 transition-colors duration-300 ${
              overHero ? 'text-brand-300' : 'text-brand-700'
            }`}
            aria-hidden="true"
          >
            <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.12" />
            <path
              d="M12 2v20M2 12h20M12 2c3.2 2.6 4.8 6 4.8 10S15.2 19.4 12 22c-3.2-2.6-4.8-6-4.8-10S8.8 4.6 12 2Z"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.6"
              strokeLinecap="round"
            />
          </svg>
          <span
            className={`font-serif text-lg font-semibold leading-tight tracking-tight transition-colors duration-300 ${
              overHero ? 'text-white' : 'text-ink-900'
            }`}
          >
            Meridian
          </span>
        </Link>

        {/* Desktop nav — the gap carries the separation now that the wordmark is short. */}
        <div className="ml-10 hidden items-center gap-1 lg:flex xl:ml-14">
          <MegaButton
            label="Journals"
            open={mega === 'journals'}
            overHero={overHero}
            onToggle={() => setMega(mega === 'journals' ? null : 'journals')}
            onHover={() => setMega('journals')}
          />
          <NavLink to="/articles" className={navLinkClass}>
            Articles
          </NavLink>
          <MegaButton
            label="For authors"
            open={mega === 'authors'}
            overHero={overHero}
            onToggle={() => setMega(mega === 'authors' ? null : 'authors')}
            onHover={() => setMega('authors')}
          />
          <NavLink to="/dashboard" className={navLinkClass}>
            Editorial office
          </NavLink>
        </div>

        <div className="ml-auto flex items-center gap-2">
          <form onSubmit={submitSearch} className="hidden md:block" role="search">
            <label htmlFor="nav-search" className="sr-only">
              Search articles, journals and topics
            </label>
            <div className="relative">
              <Search
                className={`pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 transition-colors duration-300 ${
                  overHero ? 'text-white/80' : 'text-ink-500'
                }`}
                aria-hidden="true"
              />
              <input
                id="nav-search"
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Search 2.9M articles"
                className={`h-11 w-52 rounded-full border pl-10 pr-5 text-sm transition-colors duration-300 xl:w-64 ${
                  overHero
                    ? 'border-white/40 bg-white/10 text-white placeholder:text-white/75 backdrop-blur focus:border-white focus:bg-white/20'
                    : 'border-ink-300 bg-white text-ink-900 placeholder:text-ink-500 focus:border-brand-600'
                }`}
              />
            </div>
          </form>

          <Link
            to="/dashboard"
            className={`btn hidden sm:inline-flex ${
              overHero
                ? 'text-white hover:bg-white/15'
                : 'text-ink-700 hover:bg-ink-100 hover:text-ink-900'
            }`}
          >
            Log in
          </Link>
          <Link
            to="/submit"
            className={`btn hidden text-white sm:inline-flex ${
              overHero ? 'bg-brand-600 hover:bg-brand-500' : 'bg-brand-700 hover:bg-brand-800'
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
                          overHero
                            ? 'text-white hover:bg-white/15'
                            : 'text-ink-800 hover:bg-ink-100'
                        }`}
          >
            {mobileOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
          </button>
        </div>
      </div>

      {/* Desktop mega menu */}
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
              {mega === 'journals' ? <JournalsMega /> : <AuthorsMega />}
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
                    placeholder="Search articles"
                    className="h-12 w-full rounded-full border border-ink-300 pl-11 pr-5 text-base
                               placeholder:text-ink-500 focus:border-brand-600"
                  />
                </div>
              </form>

              <ul className="mt-6 space-y-1">
                {[
                  { to: '/journals', label: 'All journals' },
                  { to: '/articles', label: 'All articles' },
                  { to: '/submit', label: 'Submit your research' },
                  { to: '/dashboard', label: 'Editorial office' },
                ].map((item) => (
                  <li key={item.to}>
                    <NavLink
                      to={item.to}
                      className="flex min-h-[48px] cursor-pointer items-center rounded-lg px-3 text-base
                                 font-medium text-ink-800 transition-colors duration-200 hover:bg-ink-100"
                    >
                      {item.label}
                    </NavLink>
                  </li>
                ))}
              </ul>

              <div className="mt-6 space-y-2 border-t border-ink-200 pt-6">
                <p className="eyebrow">Browse by field</p>
                <ul className="space-y-1 pt-1">
                  {FIELDS.map((f) => (
                    <li key={f}>
                      <Link
                        to={`/journals?field=${encodeURIComponent(f)}`}
                        className="flex min-h-[44px] cursor-pointer items-center rounded-lg px-3 text-sm
                                   text-ink-600 transition-colors duration-200 hover:bg-ink-100 hover:text-ink-900"
                      >
                        {f}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="mt-auto flex flex-col gap-2 pt-8">
                <Link to="/submit" className="btn-primary w-full">
                  Submit research
                </Link>
                <Link to="/dashboard" className="btn-secondary w-full">
                  Log in
                </Link>
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </nav>
  )
}

function MegaButton({
  label,
  open,
  overHero,
  onToggle,
  onHover,
}: {
  label: string
  open: boolean
  overHero: boolean
  onToggle: () => void
  onHover: () => void
}) {
  return (
    <button
      type="button"
      onClick={onToggle}
      onMouseEnter={onHover}
      aria-expanded={open}
      className={`inline-flex cursor-pointer items-center gap-1 rounded-md px-3 py-2 text-sm font-medium
                  transition-colors duration-200 ${
                    overHero
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

function JournalsMega() {
  return (
    <div className="grid grid-cols-12 gap-8">
      <div className="col-span-4">
        <p className="eyebrow">Browse by field</p>
        <ul className="mt-3 space-y-1">
          {FIELDS.map((f) => (
            <li key={f}>
              <Link
                to={`/journals?field=${encodeURIComponent(f)}`}
                className="block cursor-pointer rounded-md px-2 py-2 text-sm text-ink-700
                           transition-colors duration-200 hover:bg-brand-50 hover:text-brand-800"
              >
                {f}
              </Link>
            </li>
          ))}
        </ul>
      </div>
      <div className="col-span-8">
        <p className="eyebrow">Most-read journals</p>
        <ul className="mt-3 grid grid-cols-2 gap-2">
          {JOURNALS.slice(0, 4).map((j) => (
            <li key={j.slug}>
              <Link
                to={`/journals?field=${encodeURIComponent(j.field)}`}
                className="block cursor-pointer rounded-lg border border-transparent p-3
                           transition-colors duration-200 hover:border-ink-200 hover:bg-ink-50"
              >
                <span className="block text-sm font-semibold text-ink-900">{j.title}</span>
                <span className="mt-0.5 block text-xs text-ink-600">
                  Impact factor {j.impactFactor.toFixed(1)} · {j.articles.toLocaleString()} articles
                </span>
              </Link>
            </li>
          ))}
        </ul>
        <Link to="/journals" className="link-underline mt-4 inline-block text-sm font-semibold">
          View all {JOURNALS.length} journals
        </Link>
      </div>
    </div>
  )
}

function AuthorsMega() {
  return (
    <div className="grid grid-cols-12 gap-8">
      <div className="col-span-7">
        <p className="eyebrow">Publish with us</p>
        <ul className="mt-3 grid grid-cols-2 gap-2">
          {AUTHOR_LINKS.map((l) => (
            <li key={l.label}>
              <Link
                to={l.to}
                className="block cursor-pointer rounded-lg border border-transparent p-3
                           transition-colors duration-200 hover:border-ink-200 hover:bg-ink-50"
              >
                <span className="block text-sm font-semibold text-ink-900">{l.label}</span>
                <span className="mt-0.5 block text-xs text-ink-600">{l.desc}</span>
              </Link>
            </li>
          ))}
        </ul>
      </div>
      <div className="col-span-5 rounded-xl bg-brand-50 p-5">
        <p className="font-serif text-lg text-ink-900">Median 51 days to first decision</p>
        <p className="mt-2 text-sm leading-relaxed text-ink-600">
          Collaborative peer review with named reviewers, published alongside every accepted
          article.
        </p>
        <Link to="/submit" className="btn-primary mt-4">
          Start a submission
        </Link>
      </div>
    </div>
  )
}
