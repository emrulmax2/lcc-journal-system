import { useEffect, useRef, useState } from 'react'
import { ImageOff } from 'lucide-react'
import type { MediaImage } from '@/lib/props'
import { photoUrl } from '@/lib/images'

type Props = {
  /** null renders the neutral placeholder. It never renders a substitute photograph. */
  src: string | null
  /** Required: describe the image, or pass "" if it is purely decorative. */
  alt: string
  className?: string
  width?: number
  height?: number
  /** Above-the-fold images should be eager so the LCP element isn't deferred. */
  priority?: boolean
}

/**
 * <img> with a skeleton underneath and a NEUTRAL placeholder if it fails or is absent.
 *
 * WHAT CHANGED, AND WHY IT MATTERS: this component used to swap a failed image for
 * `picsum.photos/seed/<slug>` — a RANDOM stock photograph. So a journal with no cover, or
 * a news item whose Unsplash key had gone stale, was silently illustrated with an
 * arbitrary picture of something else entirely, presented to the reader as its image. A
 * blank is honest; a random photograph is a small fabrication, repeated on every card.
 *
 * Two things here are load-bearing under SSR:
 *
 * 1. THE IMAGE IS NEVER RENDERED TRANSPARENT. It used to start at `opacity-0` and fade in
 *    on the React `onLoad` handler. Server-side that produced `<img class="opacity-0">`,
 *    and the handler is only attached at HYDRATION — so for a cached or fast image, the
 *    browser's `load` event fires BEFORE React attaches, the handler never runs, and the
 *    image stays permanently invisible behind a permanently pulsing skeleton. The skeleton
 *    now sits behind the image instead, which needs no JavaScript to go away.
 *
 * 2. THE ERROR PATH IS RECONCILED AFTER MOUNT. `onError` has the same problem: a dead URL
 *    that 404s before hydration never triggers the handler. The effect below asks the real
 *    DOM whether the image already failed, rather than trusting that React saw it happen.
 */
export default function ImageWithFallback({
  src,
  alt,
  className = '',
  width = 1200,
  height = 800,
  priority = false,
}: Props) {
  const [failed, setFailed] = useState(false)
  const imgRef = useRef<HTMLImageElement>(null)

  useEffect(() => {
    setFailed(false)
  }, [src])

  useEffect(() => {
    const el = imgRef.current
    if (!el) return

    // The image may already have failed before React attached its handlers. `complete`
    // plus a zero naturalWidth is how the DOM reports "I tried and it broke", and it is
    // the only reliable way to detect a pre-hydration failure.
    if (el.complete && el.naturalWidth === 0) setFailed(true)
  }, [src])

  if (src === null || failed) return <Placeholder className={className} />

  return (
    <span className="relative block h-full w-full overflow-hidden bg-ink-100">
      <span
        aria-hidden="true"
        className="absolute inset-0 animate-pulse bg-gradient-to-br from-ink-100 to-ink-200"
      />
      <img
        ref={imgRef}
        src={src}
        alt={alt}
        width={width}
        height={height}
        loading={priority ? 'eager' : 'lazy'}
        decoding="async"
        // React 18 doesn't know the camelCase prop, so pass the raw HTML attribute.
        {...{ fetchpriority: priority ? 'high' : 'auto' }}
        onError={() => setFailed(true)}
        className={`relative h-full w-full object-cover ${className}`}
      />
    </span>
  )
}

/**
 * No image. Says so, quietly, and says nothing else — no photograph of anything, and no
 * text that a screen reader would read out as content.
 */
function Placeholder({ className = '' }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={`flex h-full w-full items-center justify-center bg-ink-100 ${className}`}
    >
      <ImageOff className="h-6 w-6 text-ink-400" />
    </span>
  )
}

/**
 * The card image, with the resolution order the whole site now follows:
 *
 *   1. `image`  — a real asset we own and uploaded, with its own alt text.
 *   2. `photo`  — the legacy Unsplash key, until a real one is uploaded.
 *   3. nothing  — the neutral placeholder.
 *
 * `alt` from the media record wins over the caller's, because whoever uploaded the file
 * described the actual picture; the caller can only describe what it is standing in for.
 */
export function CardImage({
  image,
  photo,
  alt,
  width,
  height,
  className = '',
  priority = false,
}: {
  image: MediaImage | null | undefined
  photo: string | null | undefined
  alt: string
  width: number
  height: number
  className?: string
  priority?: boolean
}) {
  const src = image?.url ?? photoUrl(photo, width, height)

  return (
    <ImageWithFallback
      src={src}
      alt={image ? (image.alt ?? alt) : alt}
      width={width}
      height={height}
      className={className}
      priority={priority}
    />
  )
}
