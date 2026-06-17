# WPGraphQL Design System
## Tailwind v4 + shadcn/ui Theme

A developer-native dark theme for WPGraphQL projects. Navy/orange palette,
Bricolage Grotesque + DM Mono typography.

---

## Files

```
wpgraphql-theme/
├── globals.css          ← CSS custom properties + Tailwind imports
├── tailwind.config.ts   ← Full Tailwind config with WPGraphQL tokens
├── components.json      ← shadcn/ui CLI config
└── tokens.ts            ← TypeScript token reference
```

---

## Setup

### 1. Install dependencies

```bash
npm install tailwindcss tailwindcss-animate
npx shadcn@latest init
```

When `shadcn init` prompts for base color, choose **Slate** — the
`globals.css` will override all CSS variables.

### 2. Copy theme files

```bash
# Copy to your project root
cp tailwind.config.ts  ./tailwind.config.ts
cp components.json     ./components.json

# Copy globals.css to your app directory
cp globals.css         ./app/globals.css   # Next.js App Router
# or
cp globals.css         ./src/index.css     # Vite
```

### 3. Add Google Fonts to your layout

```tsx
// app/layout.tsx (Next.js)
import { Bricolage_Grotesque, DM_Mono } from "next/font/google";

const bricolage = Bricolage_Grotesque({
  subsets: ["latin"],
  axes: ["opsz"],
  weight: ["400", "500", "600", "700", "800"],
  variable: "--font-bricolage",
});

const dmMono = DM_Mono({
  subsets: ["latin"],
  weight: ["300", "400", "500"],
  variable: "--font-dm-mono",
});

export default function RootLayout({ children }) {
  return (
    <html lang="en" className={`${bricolage.variable} ${dmMono.variable}`}>
      <body>{children}</body>
    </html>
  );
}
```

Then update `globals.css` font references to use CSS variables:
```css
body        { font-family: var(--font-bricolage), sans-serif; }
code, .mono { font-family: var(--font-dm-mono), monospace; }
```

---

## Usage Examples

### Colors (Tailwind classes)

```tsx
// Semantic (always prefer these)
<div className="bg-background text-foreground" />
<div className="bg-card border border-border" />
<button className="bg-primary text-primary-foreground" />
<p className="text-muted-foreground" />

// Raw navy scale
<div className="bg-navy-900 text-navy-100" />
<div className="border-navy-700 hover:border-navy-500" />

// Raw orange scale
<span className="text-orange-wpg-300" />
<div className="bg-orange-wpg-500" />
```

### shadcn/ui Components (work out of the box)

```tsx
import { Button }   from "@/components/ui/button";
import { Badge }    from "@/components/ui/badge";
import { Card }     from "@/components/ui/card";
import { Input }    from "@/components/ui/input";

// Primary CTA — renders in orange
<Button>Get Started</Button>

// Secondary — renders in navy-700
<Button variant="secondary">View Docs</Button>

// Ghost
<Button variant="ghost">GitHub ↗</Button>

// Badges
<Badge>Open Source</Badge>
<Badge variant="secondary">MIT License</Badge>
<Badge variant="outline">v2.0.0</Badge>
```

### Typography Classes

```tsx
// Display headings (custom Tailwind classes from config)
<h1 className="text-display-xl font-extrabold tracking-tight">
  GraphQL for Every WordPress
</h1>

<h2 className="text-display-md font-bold tracking-tight">
  Turn any WordPress site into a <span className="text-orange-wpg-300">powerful GraphQL API</span>
</h2>

// Eyebrow / label (mono)
<p className="font-mono text-xs uppercase tracking-widest text-muted-foreground">
  Open Source · MIT License · 6M+ Downloads
</p>

// Inline code
<code className="font-mono text-sm text-orange-wpg-300 bg-navy-800 px-2 py-0.5 rounded">
  useQuery(GET_POSTS)
</code>
```

### Glow + Elevation Shadows

```tsx
// Orange glow on primary actions
<button className="bg-primary shadow-glow-sm hover:shadow-glow-md transition-shadow">
  Get Started
</button>

// Elevation for cards
<div className="bg-card shadow-elev-md rounded-xl p-6">
  ...
</div>

// Pulsing glow for live/active indicators
<div className="w-2 h-2 rounded-full bg-orange-wpg-300 animate-glow-pulse" />
```

### Card Pattern

```tsx
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";

<Card className="bg-card border-border">
  <CardHeader>
    <p className="font-mono text-xs uppercase tracking-widest text-muted-foreground">
      Schema Registry
    </p>
    <CardTitle className="text-display-sm">
      42 Types Registered
    </CardTitle>
  </CardHeader>
  <CardContent>
    <p className="text-muted-foreground text-sm">
      Last updated 2 minutes ago
    </p>
  </CardContent>
</Card>
```

### Light Mode

The theme defaults to dark. To support light mode, toggle the `.light`
class on `<html>` or use a theme provider:

```tsx
// Simple toggle
document.documentElement.classList.toggle("light");

// With next-themes
import { ThemeProvider } from "next-themes";

<ThemeProvider attribute="class" defaultTheme="dark" enableSystem>
  {children}
</ThemeProvider>
```

---

## Token Reference

| Token | Dark Value | Light Value | Hex (Dark) |
|---|---|---|---|
| `--background` | navy-950 | navy-50 | `#0A0F1E` |
| `--card` | navy-900 | white | `#0E1628` |
| `--muted` | navy-800 | navy-100 | `#162039` |
| `--border` | navy-700 | navy-200 | `#1E2D50` |
| `--primary` | orange-300 | orange-400 | `#FF8C1A` |
| `--muted-foreground` | navy-300 | navy-400 | `#7189B0` |
| `--ring` | orange-300 | orange-400 | `#FF8C1A` |

---

## Color Palette Quick Reference

### Navy
| Name | Class | Hex |
|---|---|---|
| navy-950 | `bg-navy-950` | `#0A0F1E` |
| navy-900 | `bg-navy-900` | `#0E1628` |
| navy-800 | `bg-navy-800` | `#162039` |
| navy-700 | `bg-navy-700` | `#1E2D50` |
| navy-300 | `text-navy-300` | `#7189B0` |
| navy-200 | `text-navy-200` | `#A3B4CC` |

### Orange
| Name | Class | Hex |
|---|---|---|
| orange-wpg-300 | `text-orange-wpg-300` | `#FF8C1A` ← primary |
| orange-wpg-400 | `text-orange-wpg-400` | `#F27800` |
| orange-wpg-500 | `text-orange-wpg-500` | `#E06A00` |
| orange-wpg-200 | `text-orange-wpg-200` | `#FFAA4D` |
