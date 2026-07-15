import { createInertiaApp } from '@inertiajs/react'
import createServer from '@inertiajs/react/server'
import { renderToString } from 'react-dom/server'
import { MotionConfig } from 'framer-motion'
import Layout from './Layout'

/**
 * The Inertia SSR entry point. Runs in Node, with no DOM.
 *
 * This process is what makes article landing pages readable by Google Scholar, Crossref
 * and DOAJ, none of which execute JavaScript. It is also the most fragile part of the
 * deployment — see docs/DEPLOYMENT.md for keeping it alive under cPanel.
 *
 * IMPORTANT: if this process is down, Inertia does NOT fail. It falls back to
 * client-side rendering, and the site keeps working for humans while going blank for
 * every machine that reads it. The `citation_*` meta tags are therefore emitted by
 * Blade, in PHP (see resources/views/app.blade.php), so that the DOI-critical metadata
 * survives this process dying. Everything here is best-effort on top of that floor.
 *
 * Nothing in this graph may touch `window`, `document` or `IntersectionObserver` at
 * module scope or during render. Reveal, Counter and ImageWithFallback are all written
 * so that their server output is the correct, fully-visible, final state.
 */
createServer((page) =>
  createInertiaApp({
    page,
    render: renderToString,

    // Passthrough — the controllers' meta titles already carry the brand. Must match
    // app.tsx, or the SSR title and the client title would disagree on hydration.
    title: (title) => title || 'JCDMS',

    resolve: (name) => {
      const pages = import.meta.glob('./pages/**/*.tsx', { eager: true })
      const module = pages[`./pages/${name}.tsx`] as any

      if (!module) {
        throw new Error(`Inertia SSR: page component "./pages/${name}.tsx" not found.`)
      }

      module.default.layout ??= (pageEl: React.ReactNode) => <Layout>{pageEl}</Layout>

      return module
    },

    setup: ({ App, props }) => (
      <MotionConfig reducedMotion="user">
        <App {...props} />
      </MotionConfig>
    ),
  }),
)
