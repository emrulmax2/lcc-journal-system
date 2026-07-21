import { router } from '@inertiajs/react'
import { Globe } from 'lucide-react'
import { useTranslations } from '@/lib/i18n'

/**
 * The language switcher. Reads the available languages and the current one from the shared
 * props, and navigates to /locale/{code} — which stores the choice server-side and sends the
 * reader back to the same page in the new language. Rendered only when more than one language
 * is actually available.
 */
export function LocaleSwitcher({ className }: { className?: string }) {
  const { t, locale, locales } = useTranslations()

  if (locales.length <= 1) return null

  return (
    <label className={`inline-flex items-center gap-1.5 text-sm ${className ?? ''}`}>
      <Globe className="h-4 w-4 text-ink-500" aria-hidden="true" />
      <span className="sr-only">{t('common.language', 'Language')}</span>
      <select
        value={locale}
        onChange={(e) => router.visit(`/locale/${e.target.value}`, { preserveScroll: true })}
        className="cursor-pointer rounded-md border border-ink-300 bg-white px-2 py-1 text-sm text-ink-800 hover:border-ink-400 focus:border-brand-600"
      >
        {locales.map((l) => (
          <option key={l.code} value={l.code}>
            {l.name}
          </option>
        ))}
      </select>
    </label>
  )
}
