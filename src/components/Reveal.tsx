import type { ReactNode } from 'react'
import { motion } from 'framer-motion'
import { fadeUp, staggerParent, viewportOnce } from '@/lib/motion'

type RevealProps = {
  children: ReactNode
  className?: string
  delay?: number
  as?: 'div' | 'section' | 'li' | 'article' | 'header'
}

/** Fades + lifts its child into view once, on scroll. */
export function Reveal({ children, className, delay = 0, as = 'div' }: RevealProps) {
  const Tag = motion[as]
  return (
    <Tag
      className={className}
      variants={fadeUp}
      initial="hidden"
      whileInView="visible"
      viewport={viewportOnce}
      transition={{ delay }}
    >
      {children}
    </Tag>
  )
}

/** Parent that staggers `RevealItem` children as the group scrolls in. */
export function RevealGroup({
  children,
  className,
  stagger = 0.08,
  as = 'div',
}: RevealProps & { stagger?: number }) {
  const Tag = motion[as]
  return (
    <Tag
      className={className}
      variants={staggerParent(stagger)}
      initial="hidden"
      whileInView="visible"
      viewport={viewportOnce}
    >
      {children}
    </Tag>
  )
}

export function RevealItem({
  children,
  className,
  as = 'div',
}: Omit<RevealProps, 'delay'>) {
  const Tag = motion[as]
  return (
    <Tag className={className} variants={fadeUp}>
      {children}
    </Tag>
  )
}
