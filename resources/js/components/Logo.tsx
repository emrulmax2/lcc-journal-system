/**
 * The JCDMS brand mark — the "network globe" lockup.
 *
 * Rebuilt as a VECTOR from the supplied logo (the bottom of the four options): a teal
 * globe drawn as a connected network of nodes, the bold "JCDMS" wordmark, a divider, and
 * the full journal name in serif with "and" italicised. A vector, not the supplied raster,
 * because the mark renders at the navbar's 36px, the footer's larger size and on Retina —
 * all crisp from one file, with `currentColor` letting the emblem adapt to the dark hero
 * bar and the light solid bar. (To use the exact supplied artwork instead, drop the PNG in
 * and upload it under Admin → Content → Settings → Logo; that upload overrides this mark.)
 */

export function LogoMark({ className = '' }: { className?: string }) {
  // A globe as a network: nodes joined by edges, inside a faint sphere outline. Edges are
  // drawn first so the node dots sit on top of them.
  return (
    <svg viewBox="0 0 32 32" className={className} fill="none" aria-hidden="true">
      {/* Sphere outline */}
      <circle cx="16" cy="16" r="13.2" stroke="currentColor" strokeOpacity="0.35" strokeWidth="1.1" />
      {/* Meridian + equator, to read as a globe */}
      <ellipse cx="16" cy="16" rx="5.6" ry="13.2" stroke="currentColor" strokeOpacity="0.22" strokeWidth="1" />
      <path d="M3.1 13.2h25.8M3.1 18.8h25.8" stroke="currentColor" strokeOpacity="0.22" strokeWidth="1" />

      {/* Network edges */}
      <path
        d="M16 4.6 6.4 10.4M16 4.6l9.5 6M16 4.6v11M6.4 10.4 16 15.6M25.5 10.6 16 15.6M6.4 10.4 8 22.6M25.5 10.6 24 21.8M16 15.6 8 22.6M16 15.6l8 6.2M8 22.6 15.4 27.2M24 21.8 15.4 27.2M16 15.6v11.6"
        stroke="currentColor"
        strokeWidth="1.1"
        strokeLinecap="round"
      />

      {/* Nodes */}
      <g fill="currentColor">
        <circle cx="16" cy="4.6" r="2.1" />
        <circle cx="6.4" cy="10.4" r="1.7" />
        <circle cx="25.5" cy="10.6" r="1.9" />
        <circle cx="16" cy="15.6" r="2.5" />
        <circle cx="8" cy="22.6" r="1.8" />
        <circle cx="24" cy="21.8" r="1.7" />
        <circle cx="15.4" cy="27.2" r="1.7" />
      </g>
    </svg>
  )
}

/**
 * The lockup. `transparent` switches it for the see-through hero bar (light on a dark
 * photo) vs the solid bar (dark on white). `name` controls the full journal-name block:
 *   'hidden'     — emblem + JCDMS only
 *   'responsive' — full name appears from xl up (the navbar: compact on small, full on wide)
 *   'always'     — full name always (the footer)
 */
export default function Logo({
  transparent = false,
  name = 'hidden',
}: {
  transparent?: boolean
  name?: 'hidden' | 'responsive' | 'always'
}) {
  const markColor = transparent ? 'text-brand-300' : 'text-brand-700'
  const wordColor = transparent ? 'text-white' : 'text-ink-900'
  const nameColor = transparent ? 'text-white/75' : 'text-ink-600'
  const divider = transparent ? 'bg-white/30' : 'bg-brand-600/40'
  const nameShown = name === 'always' ? 'flex' : name === 'responsive' ? 'hidden xl:flex' : 'hidden'

  return (
    <span className="flex items-center gap-2.5">
      <LogoMark className={`h-9 w-9 shrink-0 transition-colors duration-300 ${markColor}`} />

      <span
        className={`whitespace-nowrap font-sans text-xl font-extrabold tracking-tight transition-colors duration-300 ${wordColor}`}
      >
        JCDMS
      </span>

      <span className={`${nameShown} items-center gap-2.5`}>
        <span className={`h-9 w-px shrink-0 ${divider}`} aria-hidden="true" />
        <span className="flex flex-col font-serif leading-[1.15]">
          <span className={`text-[11px] ${nameColor}`}>Journal of</span>
          <span className={`whitespace-nowrap text-sm font-medium ${wordColor}`}>
            Contemporary Development
          </span>
          <span className={`whitespace-nowrap text-sm font-medium ${wordColor}`}>
            <span className="italic">and</span> Management Studies
          </span>
        </span>
      </span>
    </span>
  )
}
