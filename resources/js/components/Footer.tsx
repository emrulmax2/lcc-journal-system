import { useState } from 'react'
import { Link, useForm } from '@inertiajs/react'
import { AlertCircle, Check, Loader2 } from 'lucide-react'
import { formatYear } from '@/lib/format'
import { boolSetting, menuItems, setting, useShared } from '@/lib/props'
import type { MenuItem } from '@/lib/props'
import Logo from '@/components/Logo'

/**
 * Social icons render ONLY when their setting holds a real URL.
 *
 * All three are empty by default (see CmsSeeder), and all three used to render regardless,
 * linking to "#". An icon that looks like a live account and goes nowhere is worse than no
 * icon: the reader concludes the account is broken, or that we are careless with the thing
 * they can see, and wonders what we are like with the thing they cannot.
 */
const SOCIALS: { key: string; label: string; path: string }[] = [
  {
    key: 'social_x',
    label: 'X',
    path: 'M18.9 1.2h3.7l-8.1 9.2 9.5 12.6h-7.4l-5.8-7.6-6.7 7.6H.4l8.6-9.9L0 1.2h7.6l5.2 6.9 6.1-6.9Zm-1.3 19.6h2L6.5 3.3H4.3l13.3 17.5Z',
  },
  {
    key: 'social_linkedin',
    label: 'LinkedIn',
    path: 'M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.14 2.07 2.07 0 0 1 0 4.14Zm1.78 13.02H3.55V9h3.57v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0Z',
  },
  {
    key: 'social_youtube',
    label: 'YouTube',
    path: 'M23.5 6.2a3 3 0 0 0-2.12-2.13C19.5 3.55 12 3.55 12 3.55s-7.5 0-9.38.52A3 3 0 0 0 .5 6.2 31.4 31.4 0 0 0 0 12a31.4 31.4 0 0 0 .5 5.8 3 3 0 0 0 2.12 2.13c1.88.52 9.38.52 9.38.52s7.5 0 9.38-.52a3 3 0 0 0 2.12-2.13A31.4 31.4 0 0 0 24 12a31.4 31.4 0 0 0-.5-5.8ZM9.55 15.57V8.43L15.82 12l-6.27 3.57Z',
  },
]

const COLUMN_KEYS = ['footer-guidelines', 'footer-explore', 'footer-about'] as const

export default function Footer() {
  const { site, now } = useShared()

  const siteName = setting(site, 'site_name') ?? 'JCDMS'
  const blurb = setting(site, 'footer_blurb')
  const legal = menuItems(site, 'legal')

  /**
   * THE COPYRIGHT LINE.
   *
   * It used to read: "© 2026 Meridian Open Science. A fictional publisher built as a UI
   * prototype." That sentence was LIVE, on every page, telling readers the publisher was
   * not real. It is gone.
   *
   * The year comes from the SERVER's `now`, not `new Date()`: the SSR process and the
   * browser evaluate at different instants, and on New Year's Eve they would render
   * different years into the same HTML.
   */
  const year = formatYear(now)
  const holder = setting(site, 'footer_copyright')

  const socials = SOCIALS.map((s) => ({ ...s, url: setting(site, s.key) })).filter(
    (s): s is typeof s & { url: string } => s.url !== null,
  )

  return (
    <footer className="mt-24 border-t border-ink-800 bg-ink-900 text-ink-300">
      <div className="container-page py-14">
        <Newsletter />

        <div className="mt-14 grid gap-10 border-t border-ink-800 pt-12 sm:grid-cols-2 lg:grid-cols-4">
          <div>
            {/* The footer sits on a dark band, so the logo uses its light (transparent-bar)
                treatment, with the journal's full name as the subtitle. */}
            <Logo transparent name="always" />

            {blurb && (
              <p className="mt-4 max-w-xs text-sm leading-relaxed text-ink-300">{blurb}</p>
            )}

            {socials.length > 0 && (
              <ul className="mt-6 flex gap-2">
                {socials.map((s) => (
                  <li key={s.key}>
                    <a
                      href={s.url}
                      target="_blank"
                      rel="noreferrer"
                      aria-label={`${siteName} on ${s.label}`}
                      className="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full
                                 border border-ink-700 text-ink-300 transition-colors duration-200
                                 hover:border-brand-500 hover:text-brand-400"
                    >
                      <svg viewBox="0 0 24 24" className="h-[18px] w-[18px]" aria-hidden="true">
                        <path d={s.path} fill="currentColor" />
                      </svg>
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Every column, every link, from the CMS. Nine of these used to point at "#" or
              bounce back to the homepage. MenuItem::url() cannot return "#". */}
          {COLUMN_KEYS.map((key) => (
            <FooterColumn key={key} heading={site.menus[key]?.name ?? ''} items={menuItems(site, key)} />
          ))}
        </div>

        <div className="mt-12 flex flex-col gap-4 border-t border-ink-800 pt-8 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-xs text-ink-300">
            © {[year, holder].filter(Boolean).join(' ')}
          </p>

          {legal.length > 0 && (
            <nav aria-label="Legal">
              <ul className="flex flex-wrap gap-x-6 gap-y-2">
                {legal.map((item) => (
                  <li key={item.id}>
                    <FooterLink
                      item={item}
                      className="cursor-pointer text-xs text-ink-300 transition-colors duration-200 hover:text-white"
                    />
                  </li>
                ))}
              </ul>
            </nav>
          )}
        </div>
      </div>
    </footer>
  )
}

function FooterColumn({ heading, items }: { heading: string; items: MenuItem[] }) {
  if (items.length === 0) return null

  const id = `footer-${heading.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`

  return (
    <nav aria-labelledby={id}>
      <h2
        id={id}
        className="font-sans text-xs font-semibold uppercase tracking-[0.14em] text-white"
      >
        {heading}
      </h2>
      <ul className="mt-4 space-y-2.5">
        {items.map((item) => (
          <li key={item.id}>
            <FooterLink
              item={item}
              className="cursor-pointer text-sm text-ink-300 transition-colors duration-200 hover:text-white"
            />
          </li>
        ))}
      </ul>
    </nav>
  )
}

/** ink-300, not ink-400: on the ink-900 footer, ink-400 falls under 4.5:1. */
function FooterLink({ item, className }: { item: MenuItem; className: string }) {
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
    <Link href={item.url} className={className} target={item.newTab ? '_blank' : undefined}>
      {item.label}
    </Link>
  )
}

/**
 * THE NEWSLETTER FORM. It now subscribes you.
 *
 * What it used to do, in full: validate the address in the browser, run
 * `window.setTimeout(() => setState('done'), 900)` — a fake spinner — throw the address
 * away, and then tell the person "Thanks — check your inbox to confirm your subscription."
 * Nothing was stored. No email was ever sent. It was a false statement made to someone who
 * had just handed over personal data.
 *
 * It now posts to NewsletterController, which stores the address and sends a double opt-in
 * confirmation. The success message is the SAME SENTENCE, and it is now true.
 */
function Newsletter() {
  const { site, flash } = useShared()

  const heading = setting(site, 'newsletter_heading')
  const blurb = setting(site, 'newsletter_blurb')

  const form = useForm({ email: '' })

  /**
   * `flash.success` is SHARED, and every controller in the app writes to it — "Article
   * published.", "Deposit queued.", "You are subscribed." So the footer may only show it
   * when THIS form is what just succeeded. Without this gate, publishing an article would
   * print "Article published." under the newsletter signup, which reads as a confirmation
   * of the wrong thing.
   */
  const [justSubscribed, setJustSubscribed] = useState(false)

  // An editor can switch the whole thing off. Absent means off — a form that sends mail
  // does not default to on because a setting failed to seed.
  if (!boolSetting(site, 'newsletter_enabled')) return null

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post('/newsletter', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset('email')
        setJustSubscribed(true)
      },
    })
  }

  const error = form.errors.email

  return (
    <div className="grid gap-8 lg:grid-cols-2 lg:items-center">
      <div>
        {heading && <h2 className="font-serif text-3xl text-white sm:text-4xl">{heading}</h2>}
        {blurb && (
          <p className="mt-3 max-w-prose text-sm leading-relaxed text-ink-300">{blurb}</p>
        )}
      </div>

      <form onSubmit={submit} noValidate className="lg:w-full lg:max-w-md lg:justify-self-end">
        <label htmlFor="newsletter-email" className="block text-sm font-medium text-white">
          Email address
        </label>
        <div className="mt-2 flex flex-col gap-2 sm:flex-row">
          <input
            id="newsletter-email"
            type="email"
            name="email"
            autoComplete="email"
            value={form.data.email}
            onChange={(e) => {
              form.setData('email', e.target.value)
              setJustSubscribed(false)
            }}
            disabled={form.processing}
            aria-invalid={Boolean(error)}
            aria-describedby={error ? 'newsletter-error' : undefined}
            placeholder="name@university.edu"
            className={`h-12 w-full rounded-full border bg-ink-800 px-5 text-base text-white
                        placeholder:text-ink-400 transition-colors duration-200 disabled:opacity-60
                        ${error ? 'border-danger-600' : 'border-ink-700 focus:border-brand-400'}`}
          />
          <button
            type="submit"
            disabled={form.processing}
            className="btn shrink-0 bg-brand-600 text-white hover:bg-brand-500 sm:w-auto"
          >
            {form.processing && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
            {form.processing ? 'Subscribing' : 'Subscribe'}
          </button>
        </div>

        {/* Announced, and never carried by colour alone — each state has an icon and a word. */}
        <p aria-live="polite" className="mt-2 min-h-[20px] text-sm">
          {error && (
            <span id="newsletter-error" className="inline-flex items-center gap-1.5 text-danger-100">
              <AlertCircle className="h-4 w-4 shrink-0" aria-hidden="true" />
              {error}
            </span>
          )}
          {!error && justSubscribed && flash.success && (
            <span className="inline-flex items-center gap-1.5 text-brand-300">
              <Check className="h-4 w-4 shrink-0" aria-hidden="true" />
              {flash.success}
            </span>
          )}
        </p>
      </form>
    </div>
  )
}
