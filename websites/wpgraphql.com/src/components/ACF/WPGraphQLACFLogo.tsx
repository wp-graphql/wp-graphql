// =============================================================
// WPGraphQL for ACF Logo Component
// Rounded square + field-group-table mark (emerald header, column
// headers, active row with left accent bar, action buttons).
//
// Ported from the ACF brand guide (acf-theme/WPGraphQLACFLogo.tsx),
// reformatted and given a `markStyle` prop for parity with the IDE logo.
// (No SVG gradient id here, so no useId hydration concern.)
// =============================================================

import { cn } from "@/lib/utils"

type LogoVariant = "default" | "reversed" | "light"

interface ACFLogoMarkProps {
  size?: number
  variant?: LogoVariant
  showGlow?: boolean
  className?: string
  style?: React.CSSProperties
}

export function WPGraphQLACFLogoMark({
  size = 32,
  variant = "default",
  showGlow = false,
  className,
  style,
}: ACFLogoMarkProps) {
  const isReversed = variant === "reversed"
  const container = isReversed ? "#10B981" : "#0C1220"
  const header = isReversed ? "rgba(0,0,0,0.28)" : "#10B981"
  const headerDot = isReversed
    ? "rgba(255,255,255,0.35)"
    : "rgba(255,255,255,0.25)"
  const colHdr = isReversed ? "rgba(0,0,0,0.18)" : "#131B30"
  const colLine = isReversed ? "rgba(255,255,255,0.4)" : "#435678"
  const activeBg = isReversed ? "rgba(0,0,0,0.1)" : "rgba(16,185,129,0.08)"
  const activeBar = isReversed ? "rgba(0,0,0,0.5)" : "#10B981"
  const activeLine = isReversed ? "rgba(255,255,255,0.85)" : "#34D399"
  const dataLine = isReversed ? "rgba(255,255,255,0.5)" : "#96A8C8"
  const rowDiv = isReversed ? "rgba(0,0,0,0.12)" : "#1A2540"
  const saveBtn = isReversed ? "rgba(0,0,0,0.28)" : "#10B981"
  const addBtn = isReversed ? "rgba(0,0,0,0.15)" : "rgba(16,185,129,0.15)"
  const addBorder = isReversed ? "rgba(0,0,0,0.25)" : "rgba(16,185,129,0.3)"

  const glowStyle = showGlow
    ? {
        filter:
          "drop-shadow(0 0 24px rgba(16,185,129,0.4)) drop-shadow(0 0 6px rgba(16,185,129,0.25))",
      }
    : {}

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 160 160"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-label="WPGraphQL for ACF"
      className={className}
      style={{ ...glowStyle, ...style }}
    >
      <rect width="160" height="160" rx="36" fill={container} />
      {/* Header bar */}
      <rect
        x="14"
        y="14"
        width="132"
        height="14"
        rx="3.5"
        fill={header}
        opacity="0.9"
      />
      <rect x="18" y="17.5" width="14" height="7" rx="1.5" fill={headerDot} />
      {/* Column headers */}
      <rect x="14" y="34" width="132" height="10" fill={colHdr} />
      <rect
        x="19"
        y="37"
        width="18"
        height="3"
        rx="1.5"
        fill={colLine}
        opacity="0.8"
      />
      <rect
        x="52"
        y="37"
        width="28"
        height="3"
        rx="1.5"
        fill={colLine}
        opacity="0.8"
      />
      <rect
        x="94"
        y="37"
        width="20"
        height="3"
        rx="1.5"
        fill={colLine}
        opacity="0.8"
      />
      {/* Active row */}
      <rect x="14" y="44.75" width="132" height="16" fill={activeBg} />
      <rect x="14" y="44.75" width="3" height="16" fill={activeBar} />
      <rect
        x="19"
        y="50.5"
        width="22"
        height="3"
        rx="1.5"
        fill={activeLine}
        opacity="0.9"
      />
      <rect
        x="52"
        y="50.5"
        width="34"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.65"
      />
      <rect
        x="94"
        y="50.5"
        width="24"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.5"
      />
      <rect x="14" y="60.75" width="132" height="0.75" fill={rowDiv} />
      {/* Row 2 */}
      <rect
        x="19"
        y="66.5"
        width="18"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.55"
      />
      <rect
        x="52"
        y="66.5"
        width="28"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.45"
      />
      <rect
        x="94"
        y="66.5"
        width="20"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.4"
      />
      <rect x="14" y="76.75" width="132" height="0.75" fill={rowDiv} />
      {/* Row 3 */}
      <rect
        x="19"
        y="82.5"
        width="20"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.45"
      />
      <rect
        x="52"
        y="82.5"
        width="32"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.35"
      />
      <rect x="14" y="92.75" width="132" height="0.75" fill={rowDiv} />
      {/* Row 4 */}
      <rect
        x="19"
        y="98.5"
        width="16"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.35"
      />
      <rect
        x="52"
        y="98.5"
        width="24"
        height="3"
        rx="1.5"
        fill={dataLine}
        opacity="0.28"
      />
      {/* Bottom buttons */}
      <rect
        x="14"
        y="130"
        width="60"
        height="16"
        rx="4"
        fill={addBtn}
        stroke={addBorder}
        strokeWidth="1"
      />
      <rect
        x="90"
        y="132"
        width="56"
        height="12"
        rx="3"
        fill={saveBtn}
        opacity="0.75"
      />
      <rect
        x="95"
        y="135.5"
        width="40"
        height="2.5"
        rx="1.25"
        fill="rgba(255,255,255,0.6)"
      />
    </svg>
  )
}

interface ACFLogoProps {
  showWordmark?: boolean
  size?: number
  variant?: LogoVariant
  subLabel?: string
  showGlow?: boolean
  className?: string
  markClassName?: string
  /** Inline style applied to the logo mark only (e.g. a glow drop-shadow). */
  markStyle?: React.CSSProperties
}

export function WPGraphQLACFLogo({
  showWordmark = true,
  size = 32,
  variant = "default",
  subLabel = "by WPGraphQL",
  showGlow = false,
  className,
  markClassName,
  markStyle,
}: ACFLogoProps) {
  const isLight = variant === "light"
  return (
    <div className={cn("flex items-center gap-3", className)}>
      <WPGraphQLACFLogoMark
        size={size}
        variant={variant}
        showGlow={showGlow}
        className={markClassName}
        style={markStyle}
      />
      {showWordmark && (
        <div className="flex flex-col leading-none">
          <span
            className={cn(
              "font-extrabold tracking-tight",
              isLight ? "text-navy-950" : "text-foreground"
            )}
            style={{ fontSize: size * 0.5, letterSpacing: "-0.025em" }}
          >
            WPGraphQL{" "}
            <span className={isLight ? "text-emerald-500" : "text-emerald-300"}>
              ACF
            </span>
          </span>
          {subLabel && (
            <span
              className={cn(
                "font-mono mt-0.5",
                isLight ? "text-navy-400" : "text-muted-foreground"
              )}
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
