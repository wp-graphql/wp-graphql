// =============================================================
// WPGraphQL Design System — tokens.ts
// Central reference for all design tokens.
// Import from here instead of hardcoding values.
// =============================================================

// ── Raw Color Values ──────────────────────────────────────────
export const colors = {
  navy: {
    950: "#0A0F1E",
    900: "#0E1628",
    800: "#162039",
    700: "#1E2D50",
    600: "#243560",
    500: "#2D4070",
    400: "#4A5F8A",
    300: "#7189B0",
    200: "#A3B4CC",
    100: "#D0D9E8",
    50:  "#EDF0F6",
  },
  orange: {
    600: "#C45C00",
    500: "#E06A00",
    400: "#F27800",
    300: "#FF8C1A",  // PRIMARY ACCENT
    200: "#FFAA4D",
    100: "#FFD099",
    50:  "#FFF2E0",
  },
  // Semantic aliases
  semantic: {
    background:      "#0A0F1E",   // navy-950
    surface:         "#0E1628",   // navy-900
    surfaceRaised:   "#162039",   // navy-800
    border:          "#1E2D50",   // navy-700
    textPrimary:     "#F0F4FF",
    textSecondary:   "#A3B4CC",   // navy-200
    textMuted:       "#7189B0",   // navy-300
    accent:          "#FF8C1A",   // orange-300
    accentDim:       "#E06A00",   // orange-500
  },
} as const;

// ── Typography ────────────────────────────────────────────────
export const typography = {
  fonts: {
    sans:    "'Bricolage Grotesque', system-ui, sans-serif",
    mono:    "'DM Mono', ui-monospace, monospace",
    display: "'Bricolage Grotesque', system-ui, sans-serif",
  },
  // Google Fonts URL for <head>
  googleFontsUrl:
    "https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,400&display=swap",

  scale: {
    displayXl: { size: "4.5rem",   lineHeight: "1",     tracking: "-0.04em",  weight: 800 },
    displayLg: { size: "3.75rem",  lineHeight: "1.02",  tracking: "-0.035em", weight: 800 },
    displayMd: { size: "3rem",     lineHeight: "1.05",  tracking: "-0.03em",  weight: 700 },
    displaySm: { size: "2.25rem",  lineHeight: "1.1",   tracking: "-0.025em", weight: 700 },
    headline:  { size: "1.875rem", lineHeight: "1.15",  tracking: "-0.02em",  weight: 600 },
    title:     { size: "1.25rem",  lineHeight: "1.2",   tracking: "-0.015em", weight: 600 },
    body:      { size: "1rem",     lineHeight: "1.65",  tracking: "0",        weight: 400 },
    small:     { size: "0.875rem", lineHeight: "1.5",   tracking: "0",        weight: 400 },
    label:     { size: "0.72rem",  lineHeight: "1",     tracking: "0.1em",    weight: 500, mono: true, uppercase: true },
    code:      { size: "0.875rem", lineHeight: "1.8",   tracking: "0",        weight: 400, mono: true },
  },
} as const;

// ── Spacing ───────────────────────────────────────────────────
// 4px base grid
export const spacing = {
  "1": "4px",
  "2": "8px",
  "3": "12px",
  "4": "16px",
  "6": "24px",
  "8": "32px",
  "12": "48px",
  "16": "64px",
  "20": "80px",
  "24": "96px",
  "32": "128px",
} as const;

// ── Border Radius ─────────────────────────────────────────────
export const radius = {
  sm:   "4px",
  md:   "6px",
  DEFAULT: "8px",
  lg:   "10px",
  xl:   "12px",
  "2xl": "16px",
  pill: "9999px",
} as const;

// ── Shadows ───────────────────────────────────────────────────
export const shadows = {
  glowSm: "0 0 12px -2px hsl(30 100% 55% / 0.25)",
  glowMd: "0 0 24px -4px hsl(30 100% 55% / 0.30)",
  glowLg: "0 0 40px -8px hsl(30 100% 55% / 0.35)",
  elevSm: "0 1px 3px 0 hsl(222 47% 4% / 0.4), 0 1px 2px -1px hsl(222 47% 4% / 0.4)",
  elevMd: "0 4px 12px -2px hsl(222 47% 4% / 0.5), 0 2px 4px -2px hsl(222 47% 4% / 0.3)",
  elevLg: "0 12px 32px -4px hsl(222 47% 4% / 0.6), 0 4px 8px -4px hsl(222 47% 4% / 0.3)",
} as const;

// ── Utility: cn class helper (mirrors shadcn pattern) ─────────
// Usage: import { cn } from "@/lib/utils"  (standard shadcn location)
// This file is just the token reference; cn lives in lib/utils.ts
