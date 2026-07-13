/**
 * Free, no-attribution-required imagery.
 *
 * Photos: Unsplash (free to use under the Unsplash licence, commercial use OK).
 * Every ID below was checked to return HTTP 200 before being added — if you add
 * more, check them too, and give <ImageWithFallback> the final say at runtime.
 *
 * Avatars: randomuser.me (free demo portraits).
 * Fallback: picsum.photos (free placeholder service) — see ImageWithFallback.
 */

const UNSPLASH = 'https://images.unsplash.com/photo-'

/** Build a responsive, cropped, WebP-ish Unsplash URL. */
export function unsplash(id: string, w = 1200, h?: number) {
  const params = new URLSearchParams({
    auto: 'format', // serves WebP/AVIF when the browser supports it
    fit: 'crop',
    q: '72',
    w: String(w),
  })
  if (h) params.set('h', String(h))
  return `${UNSPLASH}${id}?${params.toString()}`
}

/** Fallback so a dead CDN never leaves a broken-image icon on the page. */
export function fallback(seed: string, w = 1200, h = 800) {
  return `https://picsum.photos/seed/${encodeURIComponent(seed)}/${w}/${h}`
}

export function avatar(gender: 'men' | 'women', n: number) {
  return `https://randomuser.me/api/portraits/${gender}/${n}.jpg`
}

/** Verified Unsplash photo IDs, named by what they show. */
export const PHOTO = {
  aurora: '1554080353-a576cf803bda', // climber under aurora — hero
  testTubes: '1532094349884-543bc11b234d',
  pipette: '1576086213369-97a306d36557',
  earthNetwork: '1451187580459-43490279c0fa',
  satellite: '1446776877081-d282a0f896e2',
  nebula: '1462331940025-496dfbfc7564',
  circuit: '1507413245164-6160d8298b31',
  engineer: '1581092918056-0c4c3acd3789',
  library: '1481627834876-b7833e8f5570',
  books: '1532012197267-da84d127e765',
  writing: '1434030216411-0b793f4b4173',
  bookshelf: '1497633762265-9d179a990aa6',
  microscope: '1579154204601-01588f351e67',
  coral: '1559757148-5c350d0d3c56',
  dataScreen: '1516321318423-f06f85e504b3',
  neuro: '1628595351029-c2bf17511435',
  greenTech: '1614935151651-0bea6508db6b',
  climate: '1567427017947-545c5f8d16ad',
} as const

export type PhotoKey = keyof typeof PHOTO
