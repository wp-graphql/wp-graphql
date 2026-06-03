# WPGraphQL IDE Design System
## Tailwind v4 + shadcn/ui Theme

Part of the WPGraphQL product family. Violet accent (#8B5CF6) on shared navy foundation.

---

## Files

```
wpgraphql-ide-theme/
├── globals.css           ← IDE-only CSS variables
├── family-globals.css    ← All three products in one file (recommended)
├── tailwind.config.ts    ← Full Tailwind config: navy + violet + cross-refs
├── components.json       ← shadcn/ui CLI config
├── tokens.ts             ← TypeScript tokens for all three products
└── WPGraphQLIDELogo.tsx  ← React logo component
```

---

## Quick Setup

```bash
npm install tailwindcss tailwindcss-animate
npx shadcn@latest init

# For IDE standalone:
cp globals.css ./app/globals.css

# For a site that includes multiple products:
cp family-globals.css ./app/globals.css

cp tailwind.config.ts ./tailwind.config.ts
cp components.json    ./components.json
```

---

## Theme Switching

`family-globals.css` supports all three products via CSS class:

```tsx
// WPGraphQL (default — no class needed)
<html>

// RadiQL
<html className="theme-radiql">

// WPGraphQL IDE
<html className="theme-ide">

// Per-section override (e.g. a unified product page)
<section className="theme-ide bg-background text-foreground rounded-xl p-8">
  ...IDE content...
</section>
```

---

## Color Usage

```tsx
// Semantic (works in all three themes — adapts automatically)
<div className="bg-background text-foreground" />
<button className="bg-primary text-primary-foreground" />
<p className="text-muted-foreground" />

// IDE violet (explicit, for IDE-specific UI)
<div className="bg-violet-800 border-violet-700" />
<span className="text-violet-300" />
<div className="bg-violet-300/10 border border-violet-300/20" />

// IDE status bar
<div style={{ background: "hsl(var(--ide-status-bar))" }} />

// Glow shadows
<button className="bg-primary shadow-glow-md hover:shadow-glow-lg transition-shadow">
  Open IDE
</button>
```

---

## Logo Component

```tsx
import { WPGraphQLIDELogo, WPGraphQLIDELogoMark } from "@/components/WPGraphQLIDELogo";

// Nav bar (32px)
<WPGraphQLIDELogo size={32} />

// Hero mark — large with violet glow
<WPGraphQLIDELogoMark
  size={320}
  style={{ filter: "drop-shadow(0 0 32px rgba(139,92,246,0.45)) drop-shadow(0 0 8px rgba(139,92,246,0.3))" }}
/>

// Icon only
<WPGraphQLIDELogoMark size={16} showGradient={false} />

// Reversed (on violet backgrounds)
<WPGraphQLIDELogo variant="reversed" />

// Light background
<WPGraphQLIDELogo variant="light" />

// Custom sub-label
<WPGraphQLIDELogo subLabel="GraphQL IDE for WordPress" />
```

---

## IDE-Specific Patterns

### Hero section gradient (matches brand guide)
```tsx
// In your layout or page component:
<div className="relative overflow-hidden">
  {/* Violet radial glow top-right */}
  <div
    className="pointer-events-none absolute inset-0"
    style={{
      background: `
        radial-gradient(ellipse 900px 600px at 85% 0%, rgba(139,92,246,0.13) 0%, rgba(94,46,196,0.05) 40%, transparent 70%),
        radial-gradient(ellipse 500px 500px at 5% 85%, rgba(92,46,196,0.06) 0%, transparent 65%)
      `
    }}
  />
  {/* your content */}
</div>
```

### Active tab indicator (violet top border)
```tsx
<div
  className="border-t-2 border-violet-300 bg-background px-3 py-1.5 font-mono text-xs text-foreground"
>
  query.graphql
</div>
```

### Status bar strip
```tsx
<div
  className="flex items-center justify-between px-3 py-1 rounded text-xs font-mono"
  style={{ background: "hsl(var(--ide-status-bar))" }}
>
  <span className="text-white/70">WPGraphQL IDE</span>
  <span className="text-white/50">v4.4.1 · WordPress 6.8</span>
</div>
```

### IDE callout / tip card
```tsx
<div className="border-l-2 border-violet-300 pl-4 py-2 bg-violet-300/[0.05] rounded-r-md">
  <p className="text-sm text-muted-foreground">
    Press{" "}
    <kbd className="font-mono bg-navy-800 border border-navy-600 border-b-2 px-1.5 py-0.5 rounded text-violet-200 text-xs">
      ⌘↵
    </kbd>{" "}
    to execute your query.
  </p>
</div>
```

### Cursor blink (IDE aesthetic)
```tsx
<span className="inline-block w-0.5 h-4 bg-violet-300 animate-cursor-blink" />
```

---

## Token Reference

| Token | IDE Dark | IDE Light | Hex (Dark) |
|---|---|---|---|
| `--background` | navy-950 | navy-50 | `#080D18` |
| `--card` | navy-900 | white | `#0C1220` |
| `--primary` | violet-300 | violet-500 | `#8B5CF6` |
| `--ring` | violet-300 | violet-500 | `#8B5CF6` |
| `--ide-status-bar` | violet-500 | violet-600 | `#5E2EC4` |
| `--border` | navy-700 | navy-200 | `#1A2540` |

## Full Product Family

| Product | Accent | Theme Class | Container |
|---|---|---|---|
| WPGraphQL | `#FF8C1A` orange | *(default)* | Circle |
| RadiQL | `#00D4F5` cyan | `.theme-radiql` | Rounded square |
| WPGraphQL IDE | `#8B5CF6` violet | `.theme-ide` | Rounded square |
