import { Link } from 'react-router-dom'
import { Reveal } from '@/components/Reveal'

export default function NotFound() {
  return (
    <section className="container-page flex min-h-[60vh] flex-col items-center justify-center py-24 text-center">
      <Reveal>
        <p className="eyebrow">Error 404</p>
        <h1 className="mt-3 font-serif text-4xl sm:text-5xl">This page has been retracted</h1>
        <p className="mx-auto mt-4 max-w-prose text-ink-600">
          The page you asked for does not exist — or it moved. The archive is still where you left
          it.
        </p>
        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <Link to="/" className="btn-primary">
            Back to the homepage
          </Link>
          <Link to="/articles" className="btn-secondary">
            Search articles
          </Link>
        </div>
      </Reveal>
    </section>
  )
}
