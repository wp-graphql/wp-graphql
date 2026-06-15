// =============================================================
// WPGraphQL Smart Cache Logo Component
// Sonar/radar mark: concentric rings pulsing from a center node.
// Rose accent. Rounded square container.
//
// The rings represent cache layers:
//   inner (bright)  = WPGraphQL query cache
//   middle          = object / server cache
//   outer (faint)   = network / CDN / edge cache
//
// At larger sizes (showSweep=true), a faint radar sweep arm
// and blip are rendered, suggesting active cache scanning.
// =============================================================
import { cn } from "@/lib/utils";

type LogoVariant = "default" | "reversed" | "light";

interface SCLogoMarkProps {
  size?: number;
  variant?: LogoVariant;
  showGlow?: boolean;
  /** Show radar sweep arm — recommended for 48px+ */
  showSweep?: boolean;
  className?: string;
  style?: React.CSSProperties;
}

export function WPGraphQLSmartCacheLogoMark({
  size = 32,
  variant = "default",
  showGlow = false,
  showSweep = false,
  className,
  style,
}: SCLogoMarkProps) {
  const isReversed = variant === "reversed";
  const container  = isReversed ? "#F43F5E" : "#0C1220";
  const ringColor  = isReversed ? "rgba(0,0,0,0.55)" : "#F43F5E";
  const sweepColor = isReversed ? "rgba(0,0,0,0.2)"  : "#F43F5E";
  const nodeOuter  = isReversed ? "rgba(0,0,0,0.45)" : "#F43F5E";
  const nodeInner  = isReversed ? "rgba(255,255,255,0.75)" : "#FFF1F2";
  const blipColor  = isReversed ? "rgba(0,0,0,0.5)"  : "#FB7185";

  const glowStyle = showGlow
    ? { filter: "drop-shadow(0 0 24px rgba(244,63,94,0.42)) drop-shadow(0 0 6px rgba(244,63,94,0.25))" }
    : {};

  // Ring stroke weights scale up at smaller sizes so they stay visible
  const sw1 = size <= 20 ? 5 : size <= 32 ? 3.5 : 1.5;
  const sw2 = size <= 20 ? 6 : size <= 32 ? 4   : 2;
  const sw3 = size <= 20 ? 7 : size <= 32 ? 4.5 : 2.5;

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 160 160"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-label="WPGraphQL Smart Cache"
      className={className}
      style={{ ...glowStyle, ...style }}
    >
      {/* Container */}
      <rect width="160" height="160" rx="36" fill={container} />

      {/* Sonar rings — full circles, fading outward */}
      <circle cx="80" cy="80" r="60" stroke={ringColor} strokeWidth={sw1} fill="none" opacity="0.18" />
      <circle cx="80" cy="80" r="44" stroke={ringColor} strokeWidth={sw2} fill="none" opacity="0.40" />
      <circle cx="80" cy="80" r="28" stroke={ringColor} strokeWidth={sw3} fill="none" opacity="0.72" />

      {/* Radar sweep arm — only at larger sizes */}
      {showSweep && (
        <>
          <path d="M 80 80 L 130 30 A 71 71 0 0 0 151 80 Z" fill={sweepColor} opacity="0.05" />
          <line x1="80" y1="80" x2="130" y2="30" stroke={sweepColor} strokeWidth="1" strokeLinecap="round" opacity="0.2" />
          {/* Cache blip on sweep arm */}
          <circle cx="116" cy="44" r="2.5" fill={blipColor} opacity="0.75" />
        </>
      )}

      {/* Origin node */}
      <circle cx="80" cy="80" r="9"   fill={nodeOuter} opacity="0.93" />
      <circle cx="80" cy="80" r="4.5" fill={nodeInner} opacity="0.87" />
    </svg>
  );
}

interface SCLogoProps {
  showWordmark?: boolean;
  size?: number;
  variant?: LogoVariant;
  subLabel?: string;
  showGlow?: boolean;
  showSweep?: boolean;
  className?: string;
  markClassName?: string;
}

export function WPGraphQLSmartCacheLogo({
  showWordmark = true,
  size = 32,
  variant = "default",
  subLabel = "by WPGraphQL",
  showGlow = false,
  showSweep = false,
  className,
  markClassName,
}: SCLogoProps) {
  const isLight = variant === "light";

  return (
    <div className={cn("flex items-center gap-3", className)}>
      <WPGraphQLSmartCacheLogoMark
        size={size}
        variant={variant}
        showGlow={showGlow}
        showSweep={showSweep}
        className={markClassName}
      />
      {showWordmark && (
        <div className="flex flex-col leading-none">
          <span
            className={cn("font-extrabold tracking-tight", isLight ? "text-navy-950" : "text-foreground")}
            style={{ fontSize: size * 0.45, letterSpacing: "-0.025em" }}
          >
            WPGraphQL{" "}
            <span className={isLight ? "text-rose-500" : "text-rose-300"}>Smart Cache</span>
          </span>
          {subLabel && (
            <span
              className={cn("font-mono mt-0.5", isLight ? "text-navy-400" : "text-muted-foreground")}
              style={{ fontSize: size * 0.2, letterSpacing: "0.05em" }}
            >
              {subLabel}
            </span>
          )}
        </div>
      )}
    </div>
  );
}

// ── Usage Examples ────────────────────────────────────────────
//
// Nav bar (32px, no sweep):
//   <WPGraphQLSmartCacheLogo size={32} />
//
// Hero mark (large, with glow + sweep arm):
//   <WPGraphQLSmartCacheLogoMark
//     size={300} showGlow showSweep
//     style={{ filter: "drop-shadow(0 0 32px rgba(244,63,94,0.42))" }}
//   />
//
// Icon only (16px — simplified):
//   <WPGraphQLSmartCacheLogoMark size={16} />
//
// Reversed (on rose backgrounds):
//   <WPGraphQLSmartCacheLogo variant="reversed" />
//
// Light background:
//   <WPGraphQLSmartCacheLogo variant="light" />
//
// Cache purge feedback animation:
//   <div className={isPurging ? "animate-purge-flash" : ""}>
//     <WPGraphQLSmartCacheLogoMark size={24} />
//   </div>
