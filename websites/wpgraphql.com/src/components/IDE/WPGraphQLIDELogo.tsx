// =============================================================
// WPGraphQL IDE Logo Component
// Rounded square container + split-panel IDE mark.
// Violet title bar, panel divider, syntax lines, status bar.
//
// Ported from the IDE brand guide (ide-theme/WPGraphQLIDELogo.tsx). The only
// change from the source: the SVG gradient id is derived from React's useId()
// instead of Math.random(), so it is stable across SSR/client render (no
// hydration mismatch). useId() values contain colons, which are invalid in a
// url(#id) reference, so they're stripped.
// =============================================================

import { useId } from "react"
import { cn } from "@/lib/utils"

type LogoVariant = "default" | "reversed" | "light"

interface IDELogoMarkProps {
  /** Size in pixels. Default: 32 */
  size?: number
  /**
   * default  — navy bg, violet titlebar (dark backgrounds)
   * reversed — violet bg, dark interior (violet backgrounds)
   * light    — navy bg, violet titlebar (light backgrounds)
   */
  variant?: LogoVariant
  /**
   * Show gradient highlight on titlebar and glow drop-shadow.
   * Recommended for 32px+. Default: true
   */
  showGradient?: boolean
  className?: string
  style?: React.CSSProperties
}

export function WPGraphQLIDELogoMark({
  size = 32,
  variant = "default",
  showGradient = true,
  className,
  style,
}: IDELogoMarkProps) {
  const isReversed = variant === "reversed"
  const gradId = `ide-grad-${useId().replace(/:/g, "")}`

  // Color scheme by variant
  const container = isReversed ? "#8B5CF6" : "#0C1220"
  const titleBar = isReversed ? "rgba(0,0,0,0.25)" : "#8B5CF6"
  const divider = isReversed ? "rgba(255,255,255,0.2)" : "#1A2540"
  const dot1 = isReversed ? "rgba(255,255,255,0.7)" : "rgba(255,255,255,0.55)"
  const dot2 = isReversed ? "rgba(255,255,255,0.5)" : "rgba(255,255,255,0.35)"
  const dot3 = isReversed ? "rgba(255,255,255,0.3)" : "rgba(255,255,255,0.2)"
  const queryLine1 = isReversed ? "rgba(255,255,255,0.85)" : "#A78BFA"
  const queryLine2 = isReversed ? "rgba(255,255,255,0.6)" : "#C4B5FD"
  const queryLine3 = isReversed ? "rgba(255,255,255,0.4)" : "#6578A0"
  const respLine1 = isReversed ? "rgba(255,255,255,0.75)" : "#50FA7B"
  const respLine2 = isReversed ? "rgba(255,255,255,0.5)" : "#96A8C8"
  const statusBar = isReversed ? "rgba(0,0,0,0.3)" : "#5E2EC4"
  const playBtn = isReversed ? "rgba(0,0,0,0.15)" : "rgba(255,255,255,0.12)"
  const playArrow = isReversed ? "rgba(0,0,0,0.7)" : "rgba(255,255,255,0.7)"

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 160 160"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-label="WPGraphQL IDE"
      className={className}
      style={style}
    >
      {/* Container */}
      <rect width="160" height="160" rx="36" fill={container} />

      {/* Gradient titlebar highlight */}
      {showGradient && !isReversed && (
        <>
          <rect
            x="14"
            y="14"
            width="132"
            height="18"
            rx="4"
            fill={`url(#${gradId})`}
          />
          <defs>
            <linearGradient
              id={gradId}
              x1="14"
              y1="14"
              x2="146"
              y2="32"
              gradientUnits="userSpaceOnUse"
            >
              <stop offset="0%" stopColor="#9B72FF" stopOpacity="0.35" />
              <stop offset="100%" stopColor="#6B3FD0" stopOpacity="0" />
            </linearGradient>
          </defs>
        </>
      )}

      {/* Title bar */}
      <rect x="14" y="14" width="132" height="18" rx="4" fill={titleBar} />

      {/* Traffic light dots */}
      <circle cx="25" cy="23" r="3.5" fill={dot1} />
      <circle cx="35" cy="23" r="3.5" fill={dot2} />
      <circle cx="45" cy="23" r="3.5" fill={dot3} />

      {/* Play button */}
      <circle cx="136" cy="23" r="7" fill={playBtn} />
      <path d="M 133.5 20.5 L 139 23 L 133.5 25.5 Z" fill={playArrow} />

      {/* Panel divider */}
      <rect x="79.5" y="38" width="1" height="108" fill={divider} />

      {/* Left pane: query lines */}
      <rect
        x="19"
        y="44"
        width="36"
        height="2.5"
        rx="1.25"
        fill={queryLine1}
        opacity="0.7"
      />
      <rect
        x="24"
        y="51"
        width="44"
        height="2.5"
        rx="1.25"
        fill={queryLine1}
        opacity="0.55"
      />
      <rect
        x="29"
        y="58"
        width="32"
        height="2.5"
        rx="1.25"
        fill={queryLine2}
        opacity="0.45"
      />
      <rect
        x="34"
        y="65"
        width="22"
        height="2.5"
        rx="1.25"
        fill={queryLine3}
        opacity="0.5"
      />
      <rect
        x="29"
        y="72"
        width="30"
        height="2.5"
        rx="1.25"
        fill={queryLine1}
        opacity="0.4"
      />
      <rect
        x="24"
        y="79"
        width="36"
        height="2.5"
        rx="1.25"
        fill={queryLine2}
        opacity="0.35"
      />
      <rect
        x="19"
        y="86"
        width="20"
        height="2.5"
        rx="1.25"
        fill={queryLine3}
        opacity="0.4"
      />

      {/* Right pane: response lines */}
      <rect
        x="86"
        y="44"
        width="20"
        height="2.5"
        rx="1.25"
        fill={respLine1}
        opacity="0.8"
      />
      <rect
        x="91"
        y="51"
        width="44"
        height="2.5"
        rx="1.25"
        fill={respLine2}
        opacity="0.55"
      />
      <rect
        x="91"
        y="58"
        width="34"
        height="2.5"
        rx="1.25"
        fill={respLine2}
        opacity="0.45"
      />
      <rect
        x="96"
        y="65"
        width="38"
        height="2.5"
        rx="1.25"
        fill={respLine1}
        opacity="0.4"
      />
      <rect
        x="96"
        y="72"
        width="28"
        height="2.5"
        rx="1.25"
        fill={respLine2}
        opacity="0.4"
      />
      <rect
        x="91"
        y="79"
        width="40"
        height="2.5"
        rx="1.25"
        fill={respLine2}
        opacity="0.35"
      />

      {/* Status bar */}
      <rect
        x="14"
        y="134"
        width="132"
        height="12"
        rx="3"
        fill={statusBar}
        opacity="0.75"
      />
      <rect
        x="19"
        y="137"
        width="40"
        height="2"
        rx="1"
        fill="rgba(255,255,255,0.4)"
      />
      <rect
        x="106"
        y="137"
        width="34"
        height="2"
        rx="1"
        fill="rgba(255,255,255,0.25)"
      />
    </svg>
  )
}

interface IDELogoProps {
  showWordmark?: boolean
  size?: number
  variant?: LogoVariant
  subLabel?: string
  showGradient?: boolean
  className?: string
  markClassName?: string
  /** Inline style applied to the logo mark only (e.g. a glow drop-shadow). */
  markStyle?: React.CSSProperties
}

export function WPGraphQLIDELogo({
  showWordmark = true,
  size = 32,
  variant = "default",
  subLabel = "by WPGraphQL",
  showGradient = true,
  className,
  markClassName,
  markStyle,
}: IDELogoProps) {
  const isLight = variant === "light"
  const nameColor = isLight ? "text-navy-950" : "text-foreground"
  const ideColor = isLight ? "text-violet-500" : "text-violet-300"
  const subColor = isLight ? "text-navy-400" : "text-muted-foreground"

  return (
    <div className={cn("flex items-center gap-3", className)}>
      <WPGraphQLIDELogoMark
        size={size}
        variant={variant}
        showGradient={showGradient}
        className={markClassName}
        style={markStyle}
      />
      {showWordmark && (
        <div className="flex flex-col leading-none">
          <span
            className={cn("font-extrabold tracking-tight", nameColor)}
            style={{ fontSize: size * 0.5, letterSpacing: "-0.025em" }}
          >
            WPGraphQL <span className={ideColor}>IDE</span>
          </span>
          {subLabel && (
            <span
              className={cn("font-mono mt-0.5", subColor)}
              style={{ fontSize: size * 0.22, letterSpacing: "0.05em" }}
            >
              {subLabel}
            </span>
          )}
        </div>
      )}
    </div>
  )
}
