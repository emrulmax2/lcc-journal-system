import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Check, Loader2 } from 'lucide-react'

const COLUMNS = [
  {
    heading: 'Guidelines',
    links: [
      { label: 'Author guidelines', to: '/submit' },
      { label: 'Editor guidelines', to: '/dashboard' },
      { label: 'Reviewer guidelines', to: '/dashboard' },
      { label: 'Policies and publication ethics', to: '/submit' },
      { label: 'Fee policy', to: '/submit' },
    ],
  },
  {
    heading: 'Explore',
    links: [
      { label: 'Articles', to: '/articles' },
      { label: 'Research Topics', to: '/journals' },
      { label: 'Journals', to: '/journals' },
      { label: 'How we publish', to: '/' },
    ],
  },
  {
    heading: 'Outreach',
    links: [
      { label: 'Meridian Forum', to: '/' },
      { label: 'Policy Labs', to: '/' },
      { label: 'Young Minds', to: '/' },
      { label: 'Planet Prize', to: '/' },
    ],
  },
  {
    heading: 'Connect',
    links: [
      { label: 'Help centre', to: '/' },
      { label: 'Contact us', to: '/' },
      { label: 'Careers', to: '/' },
      { label: 'Press office', to: '/' },
    ],
  },
]

const SOCIALS = [
  {
    label: 'Meridian on X',
    path: 'M18.9 1.2h3.7l-8.1 9.2 9.5 12.6h-7.4l-5.8-7.6-6.7 7.6H.4l8.6-9.9L0 1.2h7.6l5.2 6.9 6.1-6.9Zm-1.3 19.6h2L6.5 3.3H4.3l13.3 17.5Z',
  },
  {
    label: 'Meridian on LinkedIn',
    path: 'M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14Zm1.78 13.02H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0Z',
  },
  {
    label: 'Meridian on YouTube',
    path: 'M23.5 6.2a3 3 0 0 0-2.12-2.13C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.52A3 3 0 0 0 .5 6.2 31.4 31.4 0 0 0 0 12a31.4 31.4 0 0 0 .5 5.8 3 3 0 0 0 2.12 2.13c1.88.52 9.38.52 9.38.52s7.5 0 9.38-.52a3 3 0 0 0 2.12-2.13A31.4 31.4 0 0 0 24 12a31.4 31.4 0 0 0-.5-5.8ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z',
  },
]

export default function Footer() {
  return (
    <footer className="mt-24 border-t border-ink-800 bg-ink-900 text-ink-300">
      <div className="container-page py-14">
        <Newsletter />

        <div className="mt-14 grid gap-10 border-t border-ink-800 pt-12 sm:grid-cols-2 lg:grid-cols-5">
          <div className="lg:col-span-1">
            <div className="flex items-center gap-2.5">
              <svg viewBox="0 0 24 24" className="h-8 w-8 text-brand-400" aria-hidden="true">
                <circle cx="12" cy="12" r="10" fill="currentColor" opacity="0.16" />
                <path
                  d="M12 2v20M2 12h20M12 2c3.2 2.6 4.8 6 4.8 10S15.2 19.4 12 22c-3.2-2.6-4.8-6-4.8-10S8.8 4.6 12 2Z"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.6"
                  strokeLinecap="round"
                />
              </svg>
              <span className="font-serif text-lg font-semibold text-white">Meridian</span>
            </div>
            <p className="mt-4 max-w-xs text-sm leading-relaxed text-ink-400">
              Open-access publishing and end-to-end journal management. Every article free to read,
              every review open by default.
            </p>
            <ul className="mt-6 flex gap-2">
              {SOCIALS.map((s) => (
                <li key={s.label}>
                  <a
                    href="#"
                    aria-label={s.label}
                    className="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full
                               border border-ink-700 text-ink-400 transition-colors duration-200
                               hover:border-brand-500 hover:text-brand-400"
                  >
                    <svg viewBox="0 0 24 24" className="h-[18px] w-[18px]" aria-hidden="true">
                      <path d={s.path} fill="currentColor" />
                    </svg>
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {COLUMNS.map((col) => (
            <nav key={col.heading} aria-labelledby={`footer-${col.heading}`}>
              <h2
                id={`footer-${col.heading}`}
                className="font-sans text-xs font-semibold uppercase tracking-[0.14em] text-white"
              >
                {col.heading}
              </h2>
              <ul className="mt-4 space-y-2.5">
                {col.links.map((l) => (
                  <li key={l.label}>
                    <Link
                      to={l.to}
                      className="cursor-pointer text-sm text-ink-400 transition-colors duration-200 hover:text-white"
                    >
                      {l.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </nav>
          ))}
        </div>

        <div className="mt-12 flex flex-col gap-4 border-t border-ink-800 pt-8 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-ink-400">
            © 2026 Meridian Open Science. A fictional publisher built as a UI prototype.
          </p>
          <ul className="flex flex-wrap gap-x-6 gap-y-2">
            {['Privacy policy', 'Terms and conditions', 'Accessibility statement'].map((l) => (
              <li key={l}>
                <a
                  href="#"
                  className="cursor-pointer text-xs text-ink-400 transition-colors duration-200 hover:text-white"
                >
                  {l}
                </a>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </footer>
  )
}

function Newsletter() {
  const [email, setEmail] = useState('')
  const [state, setState] = useState<'idle' | 'loading' | 'done'>('idle')
  const [error, setError] = useState('')

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError('Enter a valid email address, for example name@university.edu')
      return
    }
    setError('')
    setState('loading')
    // Stand-in for the real subscribe call.
    window.setTimeout(() => setState('done'), 900)
  }

  return (
    <div className="grid gap-8 lg:grid-cols-2 lg:items-center">
      <div>
        <h2 className="font-serif text-3xl text-white sm:text-4xl">
          Research that reaches further
        </h2>
        <p className="mt-3 max-w-prose text-sm leading-relaxed text-ink-400">
          Monthly digest of the most-read open-access research, editorial calls and Research Topic
          deadlines. No spam, unsubscribe in one click.
        </p>
      </div>

      <form onSubmit={submit} noValidate className="lg:justify-self-end lg:w-full lg:max-w-md">
        <label htmlFor="newsletter-email" className="block text-sm font-medium text-white">
          Email address
        </label>
        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
          <input
            id="newsletter-email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            disabled={state !== 'idle'}
            aria-invalid={Boolean(error)}
            aria-describedby={error ? 'newsletter-error' : undefined}
            placeholder="name@university.edu"
            className={`h-12 w-full rounded-full border bg-ink-800 px-5 text-base text-white
                        placeholder:text-ink-400 transition-colors duration-200 disabled:opacity-60
                        ${error ? 'border-red-400' : 'border-ink-700 focus:border-brand-400'}`}
          />
          <button
            type="submit"
            disabled={state !== 'idle'}
            className="btn shrink-0 bg-brand-600 text-white hover:bg-brand-500 sm:w-auto"
          >
            {state === 'loading' && (
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
            )}
            {state === 'done' && <Check className="h-4 w-4" aria-hidden="true" />}
            {state === 'idle' ? 'Subscribe' : state === 'loading' ? 'Subscribing' : 'Subscribed'}
          </button>
        </div>

        {/* Errors and confirmations are announced, and never colour-only. */}
        <p aria-live="polite" className="mt-2 min-h-[20px] text-sm">
          {error && (
            <span id="newsletter-error" className="text-red-300">
              {error}
            </span>
          )}
          {state === 'done' && (
            <span className="text-brand-300">
              Thanks — check your inbox to confirm your subscription.
            </span>
          )}
        </p>
      </form>
    </div>
  )
}
