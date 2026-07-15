import { useEffect, useRef, useState } from 'react'
import { formatNumber } from '@/lib/format'

type Props = {
  value: number
  suffix?: string
  decimals?: number
  duration?: number
}

/**
 * Counts up to `value` when scrolled into view.
 *
 * The initial state is `value`, NOT 0. This is the whole point: with useState(0) the
 * server rendered every headline number on the site as a literal zero — "0 articles
 * published", "0 open-access journals" — and that is what a crawler, a no-JS reader and
 * any snapshot tool saw. The count-up is decoration; the number is the information, and
 * the information must survive without JavaScript.
 *
 * The cost of doing it this way is one frame showing the final value before the count-up
 * starts on already-visible counters. That is a fair trade for the number being true in
 * the HTML.
 */
export default function Counter({ value, suffix = '', decimals = 0, duration = 1600 }: Props) {
  const ref = useRef<HTMLSpanElement>(null)
  const [display, setDisplay] = useState(value)
  const animated = useRef(false)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (animated.current) return

    // Reduced motion: the final number, immediately, with no animation. Already correct
    // from the server render, so there is nothing to do.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    if (typeof IntersectionObserver === 'undefined') return

    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0]
        if (!entry?.isIntersecting || animated.current) return

        animated.current = true
        observer.disconnect()

        let frame = 0
        const start = performance.now()

        const tick = (now: number) => {
          const progress = Math.min((now - start) / duration, 1)
          // easeOutExpo — fast out of the gate, gentle settle.
          const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress)
          setDisplay(value * eased)
          if (progress < 1) frame = requestAnimationFrame(tick)
        }

        frame = requestAnimationFrame(tick)
        cleanup = () => cancelAnimationFrame(frame)
      },
      { threshold: 0.5 },
    )

    let cleanup: (() => void) | undefined
    observer.observe(el)

    return () => {
      observer.disconnect()
      cleanup?.()
    }
  }, [value, duration])

  return (
    <span ref={ref} className="tabular-nums">
      {formatNumber(display, decimals)}
      {suffix}
    </span>
  )
}
