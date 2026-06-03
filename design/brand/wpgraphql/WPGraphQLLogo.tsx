// =============================================================
// WPGraphQL Logo Component
// Uses the real SVG paths from the official WPGraphQL logo.
// =============================================================

import { cn } from "@/lib/utils";

const ELEPHANT_PATH =
  "m117.592 300.896c0-35.138.58-39.429 7.074-52.301 5.682-11.133 20.758-25.05 30.732-28.065 2.203-.696 2.899.348 6.726 9.858 12.408 31.195 37.11 54.505 69.349 65.29l8.465 2.899.348 16.815c.116 9.394-.116 16.932-.58 16.816-.58 0-2.899-3.131-5.45-6.958-11.945-18.671-35.718-30.036-59.724-28.645-21.802 1.276-40.589 12.061-52.765 30.152l-4.175 6.147zm25.165 85.353c10.09-3.015 17.743-13.568 17.743-24.47 0-7.77 9.51-16.699 17.627-16.699 10.321 0 17.396 6.958 18.787 18.44 1.276 10.32 5.567 16.815 14.032 21.337 4.407 2.436 6.147 2.552 32.471 2.552 26.441 0 28.065-.116 32.588-2.552 5.566-3.015 11.712-9.51 12.872-14.032.58-1.74.928-25.049.928-51.838v-48.706l-2.9-5.103c-4.87-8.582-10.437-11.597-24.469-13.452-19.019-2.436-30.036-7.538-41.053-18.787-8.117-8.118-14.96-21.57-16.815-33.051-3.71-21.918 7.19-46.503 26.325-59.26 11.48-7.654 20.526-10.437 33.979-10.437 8.813 0 12.64.58 19.25 2.9 14.728 5.218 25.745 14.031 33.515 27.02 8.234 13.916 8.002 10.205 8.698 94.514.58 68.885.928 76.539 2.783 82.337 6.146 19.02 18.903 34.559 34.443 42.097 21.338 10.437 42.212 11.133 60.767 2.087 19.019-9.393 33.747-30.615 37.69-54.389 2.435-14.612-1.16-23.193-11.83-28.528-10.32-5.219-21.917-3.827-29.107 3.479-4.639 4.639-6.262 8.118-8.234 17.86-2.551 12.06-8.118 17.394-18.323 17.394-6.378 0-12.524-3.247-15.424-8.233-2.203-3.827-2.319-6.61-2.899-78.743-.58-66.566-.812-75.727-2.667-82.801-12.409-47.895-49.403-80.366-98.69-86.513-24.584-3.015-56.94 6.843-78.858 24.354-17.627 13.916-29.108 30.615-36.53 52.997l-3.479 9.974-11.944 4.29c-19.02 6.727-28.645 12.641-42.909 26.441-12.872 12.525-21.802 26.441-27.6 43.14-5.335 15.772-5.799 21.339-5.799 75.844v51.374l2.668 5.102c3.015 5.683 10.089 11.25 16.003 12.64 2.204.465 14.38.929 27.253 1.044 17.511.116 24.701-.347 29.108-1.623zm132.204-172.793c6.03-2.551 8.35-4.87 11.48-11.597 4.523-9.625 3.248-20.526-3.362-28.064-4.755-5.45-9.51-7.306-18.555-7.306-6.03 0-8.234.58-12.64 3.363-15.077 9.51-14.265 34.79 1.39 42.792 6.147 3.016 15.425 3.363 21.687.812z";

type LogoVariant = "default" | "reversed" | "light";

interface WPGraphQLLogoMarkProps {
  /** Size in pixels (renders as a square). Default: 32 */
  size?: number;
  /**
   * default  — navy circle, orange elephant (for dark backgrounds)
   * reversed — orange circle, navy elephant (for orange backgrounds)
   * light    — navy circle, orange elephant (same as default, works on light bg too)
   */
  variant?: LogoVariant;
  className?: string;
}

export function WPGraphQLLogoMark({
  size = 32,
  variant = "default",
  className,
}: WPGraphQLLogoMarkProps) {
  const circleFill =
    variant === "reversed" ? "#FF8C1A" : "#0E1628";
  const elephantFill =
    variant === "reversed" ? "#0A0F1E" : "#FF8C1A";

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 512 512"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-label="WPGraphQL"
      className={className}
    >
      <circle cx="256" cy="256" r="256" fill={circleFill} />
      <path d={ELEPHANT_PATH} fill={elephantFill} fillRule="nonzero" />
    </svg>
  );
}

interface WPGraphQLLogoProps {
  /** Show wordmark next to the mark. Default: true */
  showWordmark?: boolean;
  /** Size of the mark in px. Default: 32 */
  size?: number;
  variant?: LogoVariant;
  /** Optional sub-label (e.g. "for WordPress" or ".cloud") */
  subLabel?: string;
  className?: string;
  markClassName?: string;
  wordmarkClassName?: string;
}

export function WPGraphQLLogo({
  showWordmark = true,
  size = 32,
  variant = "default",
  subLabel = "for WordPress",
  className,
  markClassName,
  wordmarkClassName,
}: WPGraphQLLogoProps) {
  const wordmarkColor =
    variant === "light" ? "text-navy-950" : "text-foreground";
  const subColor =
    variant === "light" ? "text-navy-400" : "text-muted-foreground";

  return (
    <div className={cn("flex items-center gap-3", className)}>
      <WPGraphQLLogoMark
        size={size}
        variant={variant}
        className={markClassName}
      />
      {showWordmark && (
        <div className={cn("flex flex-col leading-none", wordmarkClassName)}>
          <span
            className={cn(
              "font-bold tracking-tight",
              wordmarkColor
            )}
            style={{ fontSize: size * 0.56, letterSpacing: "-0.02em" }}
          >
            WPGraphQL
          </span>
          {subLabel && (
            <span
              className={cn("font-mono mt-0.5", subColor)}
              style={{ fontSize: size * 0.25, letterSpacing: "0.04em" }}
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
// Nav bar (32px mark + wordmark):
//   <WPGraphQLLogo size={32} />
//
// Favicon / icon only (no wordmark):
//   <WPGraphQLLogoMark size={16} />
//   <WPGraphQLLogoMark size={32} />
//   <WPGraphQLLogoMark size={64} />
//
// Cloud variant (custom sub-label):
//   <WPGraphQLLogo subLabel=".cloud" />
//
// Orange/reversed (for orange backgrounds):
//   <WPGraphQLLogo variant="reversed" />
//
// Light background:
//   <WPGraphQLLogo variant="light" />
//
// Large hero lockup:
//   <WPGraphQLLogo size={64} subLabel="for WordPress" />
