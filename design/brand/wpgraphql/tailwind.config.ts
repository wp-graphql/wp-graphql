// =============================================================
// WPGraphQL Design System — tailwind.config.ts
// Compatible with Tailwind v4 + shadcn/ui
// =============================================================

import type { Config } from "tailwindcss";

const config: Config = {
  darkMode: ["class"],
  content: [
    "./pages/**/*.{ts,tsx}",
    "./components/**/*.{ts,tsx}",
    "./app/**/*.{ts,tsx}",
    "./src/**/*.{ts,tsx}",
  ],
  theme: {
    container: {
      center: true,
      padding: "2rem",
      screens: {
        "2xl": "1400px",
      },
    },
    extend: {
      // ── Color Palette ──────────────────────────────────────
      colors: {
        // Raw navy scale — available as navy-950, navy-900, etc.
        navy: {
          950: "hsl(222, 47%, 8%)",
          900: "hsl(222, 45%, 10%)",
          800: "hsl(222, 42%, 15%)",
          700: "hsl(224, 43%, 21%)",
          600: "hsl(225, 42%, 26%)",
          500: "hsl(226, 40%, 31%)",
          400: "hsl(220, 31%, 42%)",
          300: "hsl(219, 26%, 57%)",
          200: "hsl(219, 23%, 73%)",
          100: "hsl(219, 30%, 86%)",
          50:  "hsl(220, 35%, 95%)",
        },
        // Raw orange scale — available as orange-wpg-300, etc.
        // (prefixed to avoid collision with Tailwind's built-in orange)
        "orange-wpg": {
          600: "hsl(26, 100%, 38%)",   // #C45C00
          500: "hsl(27, 100%, 44%)",   // #E06A00
          400: "hsl(28, 100%, 47%)",   // #F27800
          300: "hsl(30, 100%, 55%)",   // #FF8C1A  ← primary accent
          200: "hsl(31, 100%, 65%)",   // #FFAA4D
          100: "hsl(33, 100%, 80%)",   // #FFD099
          50:  "hsl(35, 100%, 93%)",   // #FFF2E0
        },

        // ── shadcn/ui semantic tokens (CSS var–backed) ──
        border:      "hsl(var(--border))",
        input:       "hsl(var(--input))",
        ring:        "hsl(var(--ring))",
        background:  "hsl(var(--background))",
        foreground:  "hsl(var(--foreground))",

        primary: {
          DEFAULT:    "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT:    "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT:    "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT:    "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT:    "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        popover: {
          DEFAULT:    "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        card: {
          DEFAULT:    "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },

        // Chart
        "chart-1": "hsl(var(--chart-1))",
        "chart-2": "hsl(var(--chart-2))",
        "chart-3": "hsl(var(--chart-3))",
        "chart-4": "hsl(var(--chart-4))",
        "chart-5": "hsl(var(--chart-5))",

        // Sidebar
        sidebar: {
          DEFAULT:            "hsl(var(--sidebar-background))",
          foreground:         "hsl(var(--sidebar-foreground))",
          primary:            "hsl(var(--sidebar-primary))",
          "primary-foreground": "hsl(var(--sidebar-primary-foreground))",
          accent:             "hsl(var(--sidebar-accent))",
          "accent-foreground": "hsl(var(--sidebar-accent-foreground))",
          border:             "hsl(var(--sidebar-border))",
          ring:               "hsl(var(--sidebar-ring))",
        },
      },

      // ── Typography ─────────────────────────────────────────
      fontFamily: {
        sans:    ["Bricolage Grotesque", "system-ui", "sans-serif"],
        mono:    ["DM Mono", "ui-monospace", "monospace"],
        display: ["Bricolage Grotesque", "system-ui", "sans-serif"],
      },

      fontSize: {
        // Display scale (large headings)
        "display-2xl": ["4.5rem",  { lineHeight: "1",    letterSpacing: "-0.04em", fontWeight: "800" }],
        "display-xl":  ["3.75rem", { lineHeight: "1.02", letterSpacing: "-0.035em", fontWeight: "800" }],
        "display-lg":  ["3rem",    { lineHeight: "1.05", letterSpacing: "-0.03em", fontWeight: "700" }],
        "display-md":  ["2.25rem", { lineHeight: "1.1",  letterSpacing: "-0.025em", fontWeight: "700" }],
        "display-sm":  ["1.875rem",{ lineHeight: "1.15", letterSpacing: "-0.02em", fontWeight: "600" }],

        // UI / body scale (inherits Tailwind defaults, listed for clarity)
        // xs: 0.75rem, sm: 0.875rem, base: 1rem, lg: 1.125rem, xl: 1.25rem ...
      },

      fontWeight: {
        // Bricolage Grotesque variable axes
        normal:    "400",
        medium:    "500",
        semibold:  "600",
        bold:      "700",
        extrabold: "800",
      },

      // ── Border Radius ──────────────────────────────────────
      borderRadius: {
        lg:  "var(--radius)",          // 0.5rem
        md:  "calc(var(--radius) - 2px)",
        sm:  "calc(var(--radius) - 4px)",
        xl:  "calc(var(--radius) + 4px)",
        "2xl": "calc(var(--radius) + 8px)",
        pill: "9999px",
      },

      // ── Spacing ─────────────────────────────────────────────
      // Extends Tailwind's 4px base grid — nothing overridden,
      // just documenting the semantic names used in the system:
      // 1=4px  2=8px  3=12px  4=16px  6=24px  8=32px
      // 12=48px  16=64px  20=80px  24=96px  32=128px

      // ── Box Shadow ─────────────────────────────────────────
      boxShadow: {
        // Glow effects using orange accent
        "glow-sm":  "0 0 12px -2px hsl(30 100% 55% / 0.25)",
        "glow-md":  "0 0 24px -4px hsl(30 100% 55% / 0.30)",
        "glow-lg":  "0 0 40px -8px hsl(30 100% 55% / 0.35)",
        // Elevation using navy
        "elev-sm":  "0 1px 3px 0 hsl(222 47% 4% / 0.4), 0 1px 2px -1px hsl(222 47% 4% / 0.4)",
        "elev-md":  "0 4px 12px -2px hsl(222 47% 4% / 0.5), 0 2px 4px -2px hsl(222 47% 4% / 0.3)",
        "elev-lg":  "0 12px 32px -4px hsl(222 47% 4% / 0.6), 0 4px 8px -4px hsl(222 47% 4% / 0.3)",
      },

      // ── Keyframes / Animation ──────────────────────────────
      keyframes: {
        "accordion-down": {
          from: { height: "0" },
          to:   { height: "var(--radix-accordion-content-height)" },
        },
        "accordion-up": {
          from: { height: "var(--radix-accordion-content-height)" },
          to:   { height: "0" },
        },
        "fade-in": {
          from: { opacity: "0", transform: "translateY(4px)" },
          to:   { opacity: "1", transform: "translateY(0)" },
        },
        "fade-out": {
          from: { opacity: "1", transform: "translateY(0)" },
          to:   { opacity: "0", transform: "translateY(4px)" },
        },
        "slide-in-right": {
          from: { transform: "translateX(100%)" },
          to:   { transform: "translateX(0)" },
        },
        "glow-pulse": {
          "0%, 100%": { boxShadow: "0 0 12px -2px hsl(30 100% 55% / 0.2)" },
          "50%":       { boxShadow: "0 0 24px -2px hsl(30 100% 55% / 0.5)" },
        },
      },
      animation: {
        "accordion-down":  "accordion-down 0.2s ease-out",
        "accordion-up":    "accordion-up 0.2s ease-out",
        "fade-in":         "fade-in 0.2s ease-out",
        "fade-out":        "fade-out 0.15s ease-in",
        "slide-in-right":  "slide-in-right 0.25s ease-out",
        "glow-pulse":      "glow-pulse 2.5s ease-in-out infinite",
      },
    },
  },
  plugins: [
    require("tailwindcss-animate"),
  ],
};

export default config;
