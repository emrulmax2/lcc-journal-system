/**
 * Shared, hydration-safe formatters.
 *
 * Two bugs live here if you do this the obvious way, and both are invisible in
 * development on a UK laptop:
 *
 * 1. TIMEZONE. `new Date('2026-06-28')` parses as UTC midnight. Formatting it without
 *    an explicit timeZone uses the RUNTIME's zone — so a Node SSR process in
 *    America/New_York renders "27 Jun 2026" while the reader's browser in Europe/London
 *    renders "28 Jun 2026". Every publication date on the site is then off by one, the
 *    server and client HTML disagree, and React logs a hydration mismatch. For a journal,
 *    a wrong publication date is not cosmetic — it is part of the citation.
 *
 * 2. LOCALE. `.toLocaleString()` with no locale uses the runtime default: "12,480" on a
 *    US Node process, "12.480" in a German browser. Same mismatch, on every metric.
 *
 * Pinning both makes the server and the client produce identical strings, always.
 */

const DATE_FMT = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'UTC',
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

const LONG_DATE_FMT = new Intl.DateTimeFormat('en-GB', {
  timeZone: 'UTC',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

const NUMBER_FMT = new Intl.NumberFormat('en-GB')

/** "2026-06-28" -> "28 Jun 2026". Null-safe: unpublished things have no date. */
export function formatDate(iso: string | null | undefined): string {
  if (!iso || iso === '—') return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  return DATE_FMT.format(date)
}

/** "2026-06-28" -> "28 June 2026". */
export function formatLongDate(iso: string | null | undefined): string {
  if (!iso || iso === '—') return '—'
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  return LONG_DATE_FMT.format(date)
}

/**
 * The year, in UTC, from an ISO string — for the footer copyright.
 *
 * Feed it the SERVER's `now`, never `new Date()`. A year taken from the client clock and a
 * year taken from the SSR process's clock disagree across New Year and across a timezone
 * boundary, which is a hydration mismatch on the copyright line of every page on the site,
 * appearing at midnight on 31 December and not before.
 */
export function formatYear(iso: string | null | undefined): string | null {
  if (!iso) return null
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return null
  return String(date.getUTCFullYear())
}

/** 12480 -> "12,480". Always en-GB, on both server and client. */
export function formatNumber(value: number, decimals = 0): string {
  if (decimals > 0) {
    return value.toLocaleString('en-GB', {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    })
  }
  return NUMBER_FMT.format(Math.round(value))
}

/** Days between an ISO date and a server-supplied "now". Never uses the client clock. */
export function daysUntil(iso: string, now: string): number {
  const target = new Date(iso).getTime()
  const from = new Date(now).getTime()
  return Math.ceil((target - from) / 86_400_000)
}

/**
 * Overdue is computed against the server's clock, passed down as a prop.
 * The Dashboard used to compare against a hardcoded `new Date('2026-07-13')`, which
 * meant "overdue" was frozen in the past and would have been wrong on every real day.
 */
export function isOverdue(due: string, now: string): boolean {
  return new Date(due).getTime() < new Date(now).getTime()
}
