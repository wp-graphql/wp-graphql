# WPGraphQL Smart Cache Design System
## Tailwind v4 + shadcn/ui · Rose accent

## The complete WPGraphQL product family

| Product | Accent | Theme Class | Mark | Container |
|---|---|---|---|---|
| WPGraphQL | `#FF8C1A` orange | *(default)* | Elephant | Circle |
| RadiQL | `#00D4F5` cyan | `.theme-radiql` | Lightning bolt | Rounded square |
| WPGraphQL IDE | `#8B5CF6` violet | `.theme-ide` | Split-panel IDE | Rounded square |
| WPGraphQL ACF | `#10B981` emerald | `.theme-acf` | Field group table | Rounded square |
| WPGraphQL Smart Cache | `#F43F5E` rose | `.theme-smart-cache` | Cache rings + purge spark | Rounded square |

## Files
```
wpgraphql-smart-cache-theme/
├── globals.css                    ← Smart Cache-only CSS vars
├── family-globals.css             ← ALL FIVE PRODUCTS (the one to use)
├── tailwind.config.ts             ← navy + rose + all family refs
├── components.json                ← shadcn/ui CLI config
├── tokens.ts                      ← All five products + SVG paths
└── WPGraphQLSmartCacheLogo.tsx    ← React logo component
```

## Setup
```bash
# For a standalone Smart Cache site:
cp globals.css ./app/globals.css

# For a unified product site (recommended):
cp family-globals.css ./app/globals.css
```

## Theme switching
```tsx
<html className="theme-smart-cache">    // rose
<html className="theme-acf">            // emerald
<html className="theme-ide">            // violet
<html className="theme-radiql">         // cyan
<html>                                  // orange (WPGraphQL default)
```

## Logo usage
```tsx
import { WPGraphQLSmartCacheLogo, WPGraphQLSmartCacheLogoMark } from "@/components/WPGraphQLSmartCacheLogo";

<WPGraphQLSmartCacheLogo size={32} />
<WPGraphQLSmartCacheLogoMark size={280} showGlow />
<WPGraphQLSmartCacheLogo variant="reversed" />
```

## Smart Cache patterns

### Cache status indicator
```tsx
<div className="flex items-center gap-2 px-3 py-1 rounded-full bg-rose-300/10 border border-rose-300/20">
  <span className="w-1.5 h-1.5 rounded-full bg-rose-300 animate-glow-pulse" />
  <span className="font-mono text-xs text-rose-200">PURGING</span>
</div>
```

### Purge flash feedback
```tsx
<div className={isPurging ? "animate-purge-flash" : ""}>
  <WPGraphQLSmartCacheLogoMark size={24} />
</div>
```

### TTL progress bar
```tsx
<div className="h-0.5 bg-navy-700 rounded-full overflow-hidden">
  <div
    className="h-full bg-gradient-to-r from-rose-500 to-rose-300 rounded-full transition-all"
    style={{ width: `${ttlPercent}%` }}
  />
</div>
```

### Cache layer ring callout
```tsx
<div className="border-l-2 border-rose-300 pl-4 py-2 bg-rose-300/[0.04] rounded-r-md">
  <p className="text-sm text-muted-foreground">
    Tag <code className="font-mono bg-navy-800 text-rose-200 px-1.5 py-0.5 rounded text-xs">post:42</code>
    {" "}purged — 12 cached responses invalidated.
  </p>
</div>
```

## Hero gradient
```tsx
background: `
  radial-gradient(ellipse 900px 600px at 85% 0%, rgba(244,63,94,0.10) 0%, rgba(190,18,60,0.04) 40%, transparent 70%),
  radial-gradient(ellipse 500px 500px at 5% 85%, rgba(159,18,57,0.05) 0%, transparent 65%)
`
```
