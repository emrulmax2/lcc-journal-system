import { useEffect } from 'react'
import { Route, Routes, useLocation } from 'react-router-dom'
import { AnimatePresence, MotionConfig, motion } from 'framer-motion'
import Navbar from './components/Navbar'
import Footer from './components/Footer'
import Home from './pages/Home'
import Journals from './pages/Journals'
import Articles from './pages/Articles'
import ArticleDetail from './pages/ArticleDetail'
import Submit from './pages/Submit'
import Dashboard from './pages/Dashboard'
import NotFound from './pages/NotFound'
import { pageTransition } from './lib/motion'

/** Reset scroll on route change — a SPA won't do it for you. */
function ScrollToTop() {
  const { pathname } = useLocation()
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'auto' })
  }, [pathname])
  return null
}

function Page({ children }: { children: React.ReactNode }) {
  const { pathname } = useLocation()

  // The navbar is fixed. The homepage hero deliberately runs underneath it (full-bleed
  // photo, transparent bar); every other page needs to clear it.
  const clearsNavbar = pathname !== '/'

  return (
    <motion.main
      id="main"
      className={clearsNavbar ? 'pt-16 lg:pt-[72px]' : undefined}
      variants={pageTransition}
      initial="hidden"
      animate="visible"
      exit="exit"
    >
      {children}
    </motion.main>
  )
}

export default function App() {
  const location = useLocation()

  return (
    // reducedMotion="user" makes every Framer animation below respect the OS setting.
    <MotionConfig reducedMotion="user">
      <a href="#main" className="skip-link">
        Skip to main content
      </a>
      <ScrollToTop />
      <Navbar />

      <AnimatePresence mode="wait">
        <Routes location={location} key={location.pathname}>
          <Route
            path="/"
            element={
              <Page>
                <Home />
              </Page>
            }
          />
          <Route
            path="/journals"
            element={
              <Page>
                <Journals />
              </Page>
            }
          />
          <Route
            path="/articles"
            element={
              <Page>
                <Articles />
              </Page>
            }
          />
          <Route
            path="/articles/:slug"
            element={
              <Page>
                <ArticleDetail />
              </Page>
            }
          />
          <Route
            path="/submit"
            element={
              <Page>
                <Submit />
              </Page>
            }
          />
          <Route
            path="/dashboard"
            element={
              <Page>
                <Dashboard />
              </Page>
            }
          />
          <Route
            path="*"
            element={
              <Page>
                <NotFound />
              </Page>
            }
          />
        </Routes>
      </AnimatePresence>

      <Footer />
    </MotionConfig>
  )
}
