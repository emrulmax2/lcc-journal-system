import type { Variants } from 'framer-motion'

/**
 * Shared Framer Motion primitives.
 *
 * Everything here animates `transform` / `opacity` only — never width/height/top —
 * so animation stays on the compositor. Durations sit in the 150–450ms band:
 * micro-interactions at 200ms, entrances at 400–450ms.
 *
 * Reduced motion is handled globally: <MotionConfig reducedMotion="user"> in App.tsx
 * makes Framer Motion drop transform/opacity animations for anyone whose OS asks for it.
 */

/** Custom cubic-bezier: quick out of the gate, long soft landing. */
export const easeOut: [number, number, number, number] = [0.22, 1, 0.36, 1]

export const fadeUp: Variants = {
  hidden: { opacity: 0, y: 24 },
  visible: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.45, ease: easeOut },
  },
}

export const fadeIn: Variants = {
  hidden: { opacity: 0 },
  visible: { opacity: 1, transition: { duration: 0.4, ease: easeOut } },
}

export const scaleIn: Variants = {
  hidden: { opacity: 0, scale: 0.96 },
  visible: { opacity: 1, scale: 1, transition: { duration: 0.35, ease: easeOut } },
}

/** Parent for staggered children. Pair with `fadeUp` on each child. */
export const staggerParent = (stagger = 0.08, delayChildren = 0): Variants => ({
  hidden: {},
  visible: {
    transition: { staggerChildren: stagger, delayChildren },
  },
})

/** Route-level page transition. */
export const pageTransition: Variants = {
  hidden: { opacity: 0, y: 8 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.3, ease: easeOut } },
  exit: { opacity: 0, y: -8, transition: { duration: 0.2, ease: 'easeIn' } },
}

/** Viewport config for scroll reveals — fire once, slightly before fully in view. */
export const viewportOnce = { once: true, amount: 0.25, margin: '0px 0px -80px 0px' } as const

/**
 * Hover/tap feedback for cards and buttons.
 * Lifts with translateY + shadow rather than `scale`, which would nudge neighbours
 * and cause layout-shift-looking jitter in a grid.
 */
export const hoverLift = {
  whileHover: { y: -4, transition: { duration: 0.2, ease: easeOut } },
  whileTap: { y: -1, transition: { duration: 0.1 } },
} as const
