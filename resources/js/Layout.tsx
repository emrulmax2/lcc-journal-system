import { useEffect, useState, type ReactNode } from 'react'
import { usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { pageTransition } from '@/lib/motion'
import Navbar from '@/components/Navbar'
import Footer from '@/components/Footer'

/**
 * The persistent app shell.
 *
 * PAGE TRANSITIONS. The old App.tsx wrapped <Routes> in <AnimatePresence> keyed on
 * useLocation().pathname. Inertia has no <Routes> to key, so the key moves here, onto
 * the page wrapper, and its source moves from react-router to usePage().url.
 *
 * `mode="wait"` IS used, and it is load-bearing. AnimatePresence renders the exiting and
 * entering pages as SIBLINGS, so without it both are in the document at once during a
 * transition — and because a page is full-height, that reads as the new page STACKED BELOW
 * the old one (reported: clicking "New submission" showed the submit page under the
 * dashboard). `mode="wait"` fully removes the outgoing page before mounting the incoming
 * one, so only one is ever on screen. The `exit` variant is short (200ms), so the cost over
 * the network round-trip Inertia already blocks on is small — and correctness beats it.
 *
 * The `exit="exit"` prop below is what makes the removal ACTUALLY animate and complete;
 * without it AnimatePresence has no exit target and the swap misbehaves.
 *
 * THE `mounted` GATE IS NOT OPTIONAL. pageTransition.hidden is { opacity: 0 }, and
 * framer-motion serialises the `initial` variant straight into the rendered style
 * attribute. So `initial="hidden"` on the server would emit
 * `<main style="opacity:0">` — wrapping the ENTIRE page body, on every page, in exactly
 * the invisibility that made the old site unreadable to Google Scholar. This is the same
 * bug that was in Reveal.tsx, and it is easy to reintroduce here without noticing,
 * because it looks completely fine in a browser.
 *
 *   server render + first client render : initial={false} -> renders VISIBLE, no mismatch
 *   subsequent client navigations       : initial="hidden" -> the transition plays
 *
 * Navbar and Footer sit outside AnimatePresence and never remount, so the mega-menu,
 * the mobile drawer and the scroll listener keep their state across visits.
 */
export default function Layout({ children }: { children: ReactNode }) {
  const { url, component } = usePage()

  const [mounted, setMounted] = useState(false)
  useEffect(() => setMounted(true), [])

  // The navbar floats transparently over the Home hero and is solid everywhere else.
  // Keyed on the COMPONENT name rather than the URL: a URL comparison breaks the moment
  // a query string or a trailing slash appears.
  const isHome = component === 'Home'

  return (
    <>
      <a href="#main" className="skip-link">
        Skip to main content
      </a>

      <Navbar overHero={isHome} />

      <AnimatePresence initial={false} mode="wait">
        <motion.main
          key={url}
          id="main"
          variants={pageTransition}
          initial={mounted ? 'hidden' : false}
          animate="visible"
          exit="exit"
          className={isHome ? '' : 'pt-16 lg:pt-[72px]'}
        >
          {children}
        </motion.main>
      </AnimatePresence>

      <Footer />
    </>
  )
}
