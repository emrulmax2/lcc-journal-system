import { usePage } from '@inertiajs/react'
import type { SharedProps } from '@/lib/props'

/**
 * Frontend i18n with no extra dependency and no round-trip.
 *
 * The backend shares the full message bag for the current locale as a page prop
 * (HandleInertiaRequests), so it is already in the SSR HTML — translated chrome is
 * server-rendered like everything else. `t('group.key')` walks that nested bag by dot path.
 *
 * A missing key returns the fallback if given, else the key itself — never a blank, so a
 * gap in a translation file is visible and diagnosable rather than an empty button.
 */
export function useTranslations() {
  const props = usePage().props as unknown as Partial<SharedProps>
  const bag = (props.translations ?? {}) as Record<string, unknown>
  const locale = props.locale ?? 'en'
  const locales = props.locales ?? []

  const t = (key: string, fallback?: string): string => {
    const value = key.split('.').reduce<unknown>((node, segment) => {
      if (node && typeof node === 'object' && segment in (node as Record<string, unknown>)) {
        return (node as Record<string, unknown>)[segment]
      }
      return undefined
    }, bag)

    return typeof value === 'string' ? value : (fallback ?? key)
  }

  return { t, locale, locales }
}
