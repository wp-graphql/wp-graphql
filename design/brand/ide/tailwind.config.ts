// =============================================================
// WPGraphQL IDE Design System — tailwind.config.ts
// Tailwind v4 + shadcn/ui
// Violet accent (#8B5CF6) on shared navy foundation.
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
      screens: { "2xl": "1400px" },
    },
    extend: {
      colors: {
        // Shared navy (identical across family)
        navy: {
          950: "hsl(224, 48%, 7%)",
          900: "hsl(224, 46%, 9%)",
          800: "hsl(224, 42%, 13%)",
          700: "hsl(225, 42%, 18%)",
          600: "hsl(225, 40%, 22%)",
          500: "hsl(226, 38%, 27%)",
          400: "hsl(220, 28%, 36%)",
          300: "hsl(220, 24%, 51%)",
          200: "hsl(220, 22%, 67%)",
          100: "hsl(220, 28%, 82%)",
          50:  "hsl(220, 32%, 93%)",
        },

        // IDE violet scale
        violet: {
          700: "hsl(268, 61%, 26%)",   // #3B1A6B
          600: "hsl(268, 63%, 37%)",   // #4C23A0
          500: "hsl(268, 65%, 47%)",   // #5E2EC4  ← status bar
          400: "hsl(268, 82%, 58%)",   // #7040E8
          300: "hsl(267, 89%, 65%)",   // #8B5CF6  ← PRIMARY
          200: "hsl(267, 83%, 76%)",   // #A78BFA
          100: "hsl(267, 69%, 85%)",   // #C4B5FD
          50:  "hsl(267, 87%, 95%)",   // #EDE9FE
        },

        // Family accent cross-references
        "orange-wpg": { 300: "hsl(30, 100%, 55%)" },
        "cyan-rql":   { 300: "hsl(191, 100%, 48%)" },

        // shadcn semantic tokens
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
        "chart-1": "hsl(var(--chart-1))",
        "chart-2": "hsl(var(--chart-2))",
        "chart-3": "hsl(var(--chart-3))",
        "chart-4": "hsl(var(--chart-4))",
        "chart-5": "hsl(var(--chart-5))",
        sidebar: {
          DEFAULT:               "hsl(var(--sidebar-background))",
          foreground:            "hsl(var(--sidebar-foreground))",
          primary:               "hsl(var(--sidebar-primary))",
          "primary-foreground":  "hsl(var(--sidebar-primary-foreground))",
          accent:                "hsl(var(--sidebar-accent))",
          "accent-foreground":   "hsl(var(--sidebar-accent-foreground))",
          border:                "hsl(var(--sidebar-border))",
          ring:                  "hsl(var(--sidebar-ring))",
        },
      },

      fontFamily: {
        sans:    ["Bricolage Grotesque", "system-ui", "sans-serif"],
        mono:    ["DM Mono", "ui-monospace", "monospace"],
        display: ["Bricolage Grotesque", "system-ui", "sans-serif"],
      },

      fontSize: {
        "display-2xl": ["4.5rem",  { lineHeight: "1",    letterSpacing: "-0.04em",  fontWeight: "800" }],
        "display-xl":  ["3.75rem", { lineHeight: "1.02", letterSpacing: "-0.035em", fontWeight: "800" }],
        "display-lg":  ["3rem",    { lineHeight: "1.05", letterSpacing: "-0.03em",  fontWeight: "700" }],
        "display-md":  ["2.25rem", { lineHeight: "1.1",  letterSpacing: "-0.025em", fontWeight: "700" }],
        "display-sm":  ["1.875rem",{ lineHeight: "1.15", letterSpacing: "-0.02em",  fontWeight: "600" }],
      },

      borderRadius: {
        lg:         "var(--radius)",
        md:         "calc(var(--radius) - 2px)",
        sm:         "calc(var(--radius) - 4px)",
        xl:         "calc(var(--radius) + 4px)",
        "2xl":      "calc(var(--radius) + 8px)",
        "app-icon": "22.5%",   // matches IDE mark container spec
        pill:       "9999px",
      },

      boxShadow: {
        // Violet glow — buttons, active states, hero mark
        "glow-sm":  "0 0 12px -2px hsl(267 89% 65% / 0.28)",
        "glow-md":  "0 0 24px -4px hsl(267 89% 65% / 0.38)",
        "glow-lg":  "0 0 40px -8px hsl(267 89% 65% / 0.45)",
        // IDE mark drop-shadow (use with filter: drop-shadow)
        "mark-glow":"0 0 32px rgba(139,92,246,0.45), 0 0 8px rgba(139,92,246,0.3)",
        // Elevation
        "elev-sm":  "0 1px 3px 0 hsl(224 48% 4% / 0.45)",
        "elev-md":  "0 4px 12px -2px hsl(224 48% 4% / 0.55)",
        "elev-lg":  "0 12px 32px -4px hsl(224 48% 4% / 0.65)",
      },

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
        // Cursor blink — for IDE / code aesthetics
        "cursor-blink": {
          "0%, 100%": { opacity: "1" },
          "50%":       { opacity: "0" },
        },
        // Violet glow pulse — active/live indicators
        "glow-pulse": {
          "0%, 100%": { boxShadow: "0 0 8px -2px hsl(267 89% 65% / 0.2)" },
          "50%":       { boxShadow: "0 0 20px -2px hsl(267 89% 65% / 0.55)" },
        },
        // Tab slide in
        "tab-in": {
          from: { opacity: "0", transform: "translateX(-4px)" },
          to:   { opacity: "1", transform: "translateX(0)" },
        },
      },

      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up":   "accordion-up 0.2s ease-out",
        "fade-in":        "fade-in 0.2s ease-out",
        "cursor-blink":   "cursor-blink 1s step-end infinite",
        "glow-pulse":     "glow-pulse 2s ease-in-out infinite",
        "tab-in":         "tab-in 0.15s ease-out",
      },
    },
  },
  plugins: [require("tailwindcss-animate")],
};

export default config;
