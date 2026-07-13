import { useState } from 'react'
import { fallback } from '@/lib/images'

type Props = {
  src: string
  /** Required: describe the image, or pass "" if it is purely decorative. */
  alt: string
  seed: string
  className?: string
  width?: number
  height?: number
  /** Above-the-fold images should be eager so the LCP element isn't deferred. */
  priority?: boolean
}

/**
 * <img> with a skeleton while loading and a picsum fallback if the CDN fails,
 * so a dead photo URL degrades to a placeholder instead of a broken-image icon.
 * Width/height are always set to reserve space and avoid layout shift.
 */
export default function ImageWithFallback({
  src,
  alt,
  seed,
  className = '',
  width = 1200,
  height = 800,
  priority = false,
}: Props) {
  const [current, setCurrent] = useState(src)
  const [loaded, setLoaded] = useState(false)

  // React 18 doesn't know the camelCase `fetchPriority` prop, so pass the raw HTML attribute.
  const priorityAttrs: Record<string, string> = { fetchpriority: priority ? 'high' : 'auto' }

  return (
    <span className="relative block h-full w-full overflow-hidden bg-ink-100">
      {!loaded && (
        <span
          aria-hidden="true"
          className="absolute inset-0 animate-pulse bg-gradient-to-br from-ink-100 to-ink-200"
        />
      )}
      <img
        src={current}
        alt={alt}
        width={width}
        height={height}
        loading={priority ? 'eager' : 'lazy'}
        decoding="async"
        {...priorityAttrs}
        onLoad={() => setLoaded(true)}
        onError={() => {
          const fb = fallback(seed, width, height)
          if (current !== fb) setCurrent(fb)
          else setLoaded(true)
        }}
        className={`h-full w-full object-cover transition-opacity duration-500 ${
          loaded ? 'opacity-100' : 'opacity-0'
        } ${className}`}
      />
    </span>
  )
}
