import { useEffect, useState } from 'react'
import { Link } from '@inertiajs/react'
import { AlertTriangle, Check, ImageOff, ImagePlus, Info, Upload, X } from 'lucide-react'
import { contentHref, type MediaItem } from '@/lib/content'
import { formatBytes } from '@/lib/admin'

/**
 * The media picker. Reused by every screen that puts an image on a public page.
 *
 * IT SHOWS THE ALT TEXT, AND IT SHOWS WHEN THERE ISN'T ANY.
 *
 * That is the whole reason this is a component rather than a `<select>` of filenames. An
 * image with `alt = NULL` has never been described by anybody, and putting one on a public
 * page is a hole in a screen reader's view of it — London Churchill College is a public-sector
 * body and is legally required to keep this site accessible. So an undescribed image is
 * flagged here, at the moment someone is choosing it, with an icon and a word (never colour
 * alone), and a link to go and fix it.
 *
 * `alt = ''` is NOT that. It is a deliberate "decorative — skip this", and it renders as such.
 */
export function MediaPicker({
  id,
  value,
  onChange,
  media,
  label,
  hint,
  error,
}: {
  id: string
  value: number | null
  onChange: (id: number | null) => void
  media: MediaItem[]
  label: string
  hint?: string
  error?: string
}) {
  const [open, setOpen] = useState(false)
  const selected = media.find((m) => m.id === value) ?? null

  // Escape closes. A modal a keyboard user cannot leave is a trap.
  useEffect(() => {
    if (!open) return

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false)
    }

    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  return (
    <div>
      <span id={`${id}-label`} className="block text-sm font-medium text-ink-800">
        {label}
      </span>

      <div className="mt-1.5 flex flex-wrap items-start gap-4">
        {selected ? (
          <figure className="w-48 overflow-hidden rounded-lg border border-ink-200">
            <img
              src={selected.url}
              alt={selected.alt ?? ''}
              className="h-28 w-full bg-ink-100 object-cover"
            />
            <figcaption className="border-t border-ink-200 p-2.5">
              <AltStatus media={selected} />
            </figcaption>
          </figure>
        ) : (
          <div className="flex h-28 w-48 flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-ink-300 text-ink-500">
            <ImageOff className="h-5 w-5" aria-hidden="true" />
            <span className="text-xs">No image</span>
          </div>
        )}

        <div className="flex flex-col gap-2">
          <button
            type="button"
            onClick={() => setOpen(true)}
            aria-labelledby={`${id}-label`}
            className="btn-secondary px-4 py-2 text-xs"
          >
            <ImagePlus className="h-4 w-4" aria-hidden="true" />
            {selected ? 'Change image' : 'Choose image'}
          </button>

          {selected && (
            <button
              type="button"
              onClick={() => onChange(null)}
              className="btn-ghost px-4 py-2 text-xs"
            >
              <X className="h-4 w-4" aria-hidden="true" />
              Remove
            </button>
          )}
        </div>
      </div>

      {error ? (
        <p className="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">
          <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          {error}
        </p>
      ) : (
        hint && (
          <p className="mt-1.5 flex items-start gap-1.5 text-sm text-ink-600">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
            <span>{hint}</span>
          </p>
        )
      )}

      {open && (
        <div
          role="dialog"
          aria-modal="true"
          aria-label="Choose an image"
          className="fixed inset-0 z-modal flex items-start justify-center overflow-y-auto bg-ink-950/50 p-4 sm:p-8"
          onClick={(e) => {
            if (e.target === e.currentTarget) setOpen(false)
          }}
        >
          <div className="w-full max-w-4xl rounded-xl border border-ink-200 bg-white shadow-lift">
            <div className="flex items-center justify-between gap-4 border-b border-ink-200 p-5">
              <h2 className="font-serif text-xl text-ink-900">Choose an image</h2>
              <button
                type="button"
                onClick={() => setOpen(false)}
                aria-label="Close"
                className="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg
                           text-ink-500 transition-colors duration-200 hover:bg-ink-100 hover:text-ink-900"
              >
                <X className="h-5 w-5" aria-hidden="true" />
              </button>
            </div>

            <div className="max-h-[60vh] overflow-y-auto p-5">
              {media.length === 0 ? (
                <div className="rounded-xl border border-dashed border-ink-300 p-10 text-center">
                  <span className="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-ink-100 text-ink-500">
                    <Upload className="h-6 w-6" aria-hidden="true" />
                  </span>
                  <p className="mt-4 font-serif text-lg text-ink-900">The library is empty</p>
                  <p className="mx-auto mt-2 max-w-prose text-sm text-ink-600">
                    Every photo on this site is still a remote stock image. Upload one we own,
                    and describe it.
                  </p>
                  <Link href={contentHref.media} className="btn-primary mt-6">
                    <Upload className="h-4 w-4" aria-hidden="true" />
                    Go to the media library
                  </Link>
                </div>
              ) : (
                <>
                  <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <li>
                      <button
                        type="button"
                        onClick={() => {
                          onChange(null)
                          setOpen(false)
                        }}
                        className="flex h-full w-full cursor-pointer flex-col items-center justify-center gap-2
                                   rounded-lg border border-dashed border-ink-300 p-6 text-ink-600
                                   transition-colors duration-200 hover:border-ink-900 hover:text-ink-900"
                      >
                        <ImageOff className="h-5 w-5" aria-hidden="true" />
                        <span className="text-xs font-semibold">No image</span>
                      </button>
                    </li>

                    {media.map((item) => {
                      const isSelected = item.id === value

                      return (
                        <li key={item.id}>
                          <button
                            type="button"
                            onClick={() => {
                              onChange(item.id)
                              setOpen(false)
                            }}
                            aria-pressed={isSelected}
                            className={`w-full cursor-pointer overflow-hidden rounded-lg border text-left
                                        transition-colors duration-200 ${
                                          isSelected
                                            ? 'border-brand-700 ring-2 ring-brand-600'
                                            : 'border-ink-200 hover:border-ink-900'
                                        }`}
                          >
                            <span className="relative block">
                              <img
                                src={item.url}
                                alt={item.alt ?? ''}
                                className="h-28 w-full bg-ink-100 object-cover"
                              />
                              {isSelected && (
                                <span className="absolute right-2 top-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-700 text-white">
                                  <Check className="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                              )}
                            </span>
                            <span className="block border-t border-ink-200 p-2.5">
                              <span className="block truncate text-xs font-medium text-ink-800">
                                {item.name}
                              </span>
                              <span className="mt-1 block">
                                <AltStatus media={item} />
                              </span>
                            </span>
                          </button>
                        </li>
                      )
                    })}
                  </ul>

                  <p className="mt-6 flex items-start gap-1.5 text-sm text-ink-600">
                    <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                    <span>
                      Missing alt text?{' '}
                      <Link href={contentHref.media} className="link-underline">
                        Fix it in the media library
                      </Link>{' '}
                      — it is what a blind reader gets instead of the image.
                    </span>
                  </p>
                </>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

/** Icon AND word. Never a colour on its own. */
function AltStatus({ media }: { media: MediaItem }) {
  if (media.needsAltText) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-danger-50 px-2 py-0.5 text-[11px] font-semibold text-danger-800">
        <AlertTriangle className="h-3 w-3" aria-hidden="true" />
        No alt text
      </span>
    )
  }

  if (media.isDecorative) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-700">
        <Check className="h-3 w-3" aria-hidden="true" />
        Decorative
      </span>
    )
  }

  return (
    <span className="flex items-start gap-1.5 text-[11px] text-ink-600">
      <Check className="mt-0.5 h-3 w-3 shrink-0 text-success-700" aria-hidden="true" />
      <span className="line-clamp-2">{media.alt}</span>
    </span>
  )
}

/** Re-exported for the media library's own grid, which shows the same status. */
export { AltStatus, formatBytes }
