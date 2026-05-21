import {
  Bars3Icon,
  BoltIcon,
  BookOpenIcon,
  ChartBarIcon,
  CodeBracketIcon,
  CommandLineIcon,
  FunnelIcon,
  PuzzlePieceIcon,
  ShieldCheckIcon,
} from "@heroicons/react/24/outline"

/**
 * Renders a Heroicon by name (set via WordPress Nav Menu CSS class
 * `icon-{HeroiconName}`, e.g. `icon-BookOpenIcon`).
 *
 * Uses static imports rather than `next/dynamic` so the icons SSR correctly
 * (next/dynamic skipped server rendering and left empty tiles in dropdowns
 * until client hydration). When adding a new icon to a WP menu CSS class,
 * import it here and add it to ICON_MAP.
 *
 * `Bars` is mapped to `Bars3Icon` so legacy `icon-Bars` menu classes
 * continue working.
 */
const ICON_MAP = {
  Bars: Bars3Icon,
  Bars3Icon,
  BoltIcon,
  BookOpenIcon,
  ChartBarIcon,
  CodeBracketIcon,
  CommandLineIcon,
  FunnelIcon,
  PuzzlePieceIcon,
  ShieldCheckIcon,
}

export default function DynamicHeroIcon({ icon, ...rest }) {
  if (!icon) return null
  const Icon = ICON_MAP[icon]
  if (!Icon) return null
  return <Icon className="h-6 w-6" aria-hidden="true" {...rest} />
}
