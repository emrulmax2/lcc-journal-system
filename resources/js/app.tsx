import './index.css'

import { createInertiaApp } from '@inertiajs/react'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createRoot, hydrateRoot } from 'react-dom/client'
import { MotionConfig } from 'framer-motion'
import type { ReactNode } from 'react'
import Layout from './Layout'

const appName = 'JCDMS'

createInertiaApp({
  // Passthrough, NOT an append. The controllers already build full meta titles that
  // include the brand (e.g. "Articles — JCD&MS"), so appending it again here produced a
  // doubled "… — JCD&MS — JCD&MS" in every <title>.
  title: (title) => title || appName,

  resolve: (name) =>
    resolvePageComponent(
      `./pages/${name}.tsx`,
      import.meta.glob('./pages/**/*.tsx'),
    ).then((module: any) => {
      // The persistent layout. Navbar and Footer sit OUTSIDE the page component so they
      // are never unmounted on navigation — otherwise the mega-menu state, the mobile
      // drawer and the scroll listener would all reset on every visit.
      module.default.layout ??= (page: ReactNode) => <Layout>{page}</Layout>
      return module
    }),

  setup({ el, App, props }) {
    const app = (
      // reducedMotion="user" must survive the move off react-router. It is the global
      // switch honouring the OS-level "reduce motion" setting.
      <MotionConfig reducedMotion="user">
        <App {...props} />
      </MotionConfig>
    )

    // hydrateRoot when the server already rendered the markup — the normal path, and the
    // one that matters. createRoot only when SSR produced nothing, which should be an
    // alarm rather than a silent fallback: see `php artisan journal:check-ssr`.
    if (el.hasChildNodes()) {
      hydrateRoot(el, app)
    } else {
      createRoot(el).render(app)
    }
  },

  progress: { color: '#0F766E' }, // brand-700
})
