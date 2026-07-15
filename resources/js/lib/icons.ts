import {
  BookOpen,
  ClipboardCheck,
  Compass,
  Database,
  FileSearch,
  Gavel,
  Globe,
  Rocket,
  Send,
  ShieldCheck,
  Sparkles,
  Users,
  type LucideIcon,
} from 'lucide-react'

/**
 * The editor-choosable Lucide icons, as a STATIC import map.
 *
 * The CMS stores an icon as a NAME ('FileSearch'), and the allow-list lives in
 * App\Models\HomeSectionItem::ICONS. This file is the frontend half of that contract and
 * must be kept in step with it.
 *
 * Deliberately not `import * as Lucide from 'lucide-react'` with a `Lucide[name]` lookup:
 *
 *   1. It defeats tree-shaking. A dynamic index into the namespace object forces every one
 *      of Lucide's ~1,500 icon components into the bundle, because the bundler cannot know
 *      which of them the string will select.
 *   2. It cannot fail safely. A name that is not in the namespace yields `undefined`, and
 *      `<undefined />` is a render-time crash — in the SSR process, that is a 500 on a page
 *      whose only fault is a typo an editor made in a dropdown.
 *
 * A name that is not in this map resolves to `null`, and the caller renders NO icon.
 */
const ICONS: Record<string, LucideIcon> = {
  FileSearch,
  Compass,
  Send,
  Database,
  BookOpen,
  Users,
  ClipboardCheck,
  Gavel,
  Rocket,
  ShieldCheck,
  Globe,
  Sparkles,
}

/** Resolve a CMS icon name. Unknown or null -> null, and the caller renders nothing. */
export function icon(name: string | null | undefined): LucideIcon | null {
  if (!name) return null
  return ICONS[name] ?? null
}
