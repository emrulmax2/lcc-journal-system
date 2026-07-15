import { useEffect, useRef, type CSSProperties, type ElementType, type ReactNode } from 'react'

type Tag = 'div' | 'section' | 'li' | 'article' | 'header' | 'ol' | 'ul'

type RevealProps = {
  children: ReactNode
  className?: string
  delay?: number
  as?: Tag
}

/**
 * Scroll reveal — as a PROGRESSIVE ENHANCEMENT, not a default state.
 *
 * This used to be framer-motion with `initial="hidden"`, and that is exactly what made
 * the site invisible to crawlers. framer serialises the initial variant into the
 * rendered style attribute, so the server emitted `style="opacity:0"` on every section
 * of every page. The content was in the HTML, and it was all transparent. A DOI landing
 * page that renders `opacity: 0` to Google Scholar is a DOI pointing at nothing.
 *
 * How this version avoids that:
 *
 *   1. The DEFAULT state — server HTML, no-JS, reduced-motion — is fully VISIBLE. There
 *      is no way to render this component into an invisible state without JavaScript.
 *
 *   2. On mount, we hide ONLY the elements that are currently below the viewport. The
 *      user cannot see that happen, because by definition those elements are off-screen.
 *      Anything already on screen is left alone and never animates — which is correct;
 *      a scroll reveal for something you are already looking at is just a flicker.
 *
 *   3. An IntersectionObserver then reveals each one as it scrolls in, via CSS.
 *
 * The animation itself lives in index.css under [data-reveal], where the
 * prefers-reduced-motion media query can switch it off wholesale.
 */
export function Reveal({ children, className, delay = 0, as = 'div' }: RevealProps) {
  const ref = useRevealOnScroll<HTMLElement>()

  // React.ElementType, not keyof JSX.IntrinsicElements: the latter makes TS resolve the
  // union to the narrowest member (SVGSymbolElement, via 'symbol') and then reject an
  // HTMLElement ref against it.
  const Tag = as as ElementType

  return (
    <Tag
      ref={ref}
      className={className}
      style={delay ? ({ '--reveal-delay': `${delay}s` } as CSSProperties) : undefined}
    >
      {children}
    </Tag>
  )
}

/** Parent that staggers its `RevealItem` children as the group scrolls in. */
export function RevealGroup({
  children,
  className,
  stagger = 0.08,
  as = 'div',
}: RevealProps & { stagger?: number }) {
  const ref = useRevealOnScroll<HTMLElement>({ stagger })
  const Tag = as as ElementType

  return (
    <Tag ref={ref} className={className}>
      {children}
    </Tag>
  )
}

/**
 * A child of RevealGroup. It carries no logic: the parent finds these by attribute and
 * assigns each one a transition-delay, so the stagger survives even if children are
 * reordered or filtered.
 */
export function RevealItem({ children, className, as = 'div' }: Omit<RevealProps, 'delay'>) {
  const Tag = as as ElementType

  return (
    <Tag data-reveal-item="" className={className}>
      {children}
    </Tag>
  )
}

function useRevealOnScroll<T extends HTMLElement>({ stagger }: { stagger?: number } = {}) {
  const ref = useRef<T>(null)

  useEffect(() => {
    const el = ref.current
    if (!el) return

    // The design system's rule: reduced motion snaps straight to the end state. Do not
    // even mark the element — leave it exactly as the server rendered it.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    if (typeof IntersectionObserver === 'undefined') return

    const targets: HTMLElement[] = stagger
      ? Array.from(el.querySelectorAll<HTMLElement>('[data-reveal-item]'))
      : [el]

    if (targets.length === 0) return

    // Only hide what is already off-screen. Anything the user can currently see stays
    // visible and is never touched — this is what makes the transition invisible.
    const pending = targets.filter((t) => t.getBoundingClientRect().top > window.innerHeight * 0.9)

    if (pending.length === 0) return

    pending.forEach((t, i) => {
      t.dataset.reveal = 'pending'
      if (stagger) t.style.setProperty('--reveal-delay', `${i * stagger}s`)
    })

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return
          ;(entry.target as HTMLElement).dataset.reveal = 'shown'
          observer.unobserve(entry.target) // fire once, never on every pass
        })
      },
      { threshold: 0.15, rootMargin: '0px 0px -80px 0px' },
    )

    pending.forEach((t) => observer.observe(t))

    return () => observer.disconnect()
  }, [stagger])

  return ref
}
