# WPGraphQL for ACF Design System
## Tailwind v4 + shadcn/ui · Emerald accent

## Files
```
wpgraphql-acf-theme/
├── globals.css           ← ACF-only CSS variables
├── family-globals.css    ← All four products (recommended)
├── tailwind.config.ts    ← Full config: navy + emerald + cross-refs
├── components.json       ← shadcn/ui CLI config
├── tokens.ts             ← All four product tokens + SVG paths
└── WPGraphQLACFLogo.tsx  ← React logo component
```

## Theme class
```tsx
<html className="theme-acf">          // ACF emerald
<html className="theme-radiql">       // RadiQL cyan
<html className="theme-ide">          // IDE violet
<html>                                // WPGraphQL orange (default)
```

## Color usage
```tsx
<button className="bg-primary text-primary-foreground" />  // emerald in ACF theme
<span className="text-emerald-300" />
<div className="bg-emerald-300/10 border border-emerald-300/20" />
<button className="shadow-glow-md hover:shadow-glow-lg" />
```

## Logo
```tsx
import { WPGraphQLACFLogo, WPGraphQLACFLogoMark } from "@/components/WPGraphQLACFLogo";

<WPGraphQLACFLogo size={32} />
<WPGraphQLACFLogoMark size={280} showGlow />
<WPGraphQLACFLogo variant="reversed" />
<WPGraphQLACFLogo subLabel="Advanced Custom Fields + GraphQL" />
```

## Hero gradient
```tsx
background: `
  radial-gradient(ellipse 900px 600px at 85% 0%, rgba(16,185,129,0.10) 0%, rgba(4,120,87,0.04) 40%, transparent 70%),
  radial-gradient(ellipse 500px 500px at 5% 85%, rgba(5,150,105,0.05) 0%, transparent 65%)
`
```

## Active field row pattern
```tsx
<div className="grid border-l-4 border-emerald-300 bg-emerald-300/[0.08]">
  <span className="text-emerald-200">title</span>
  <span className="text-muted-foreground">Text</span>
  <span className="badge-emerald">✓ GraphQL</span>
</div>
```

## Token reference
| Token | ACF Dark | Hex |
|---|---|---|
| --primary | emerald-300 | #10B981 |
| --ring | emerald-300 | #10B981 |
| --background | navy-950 | #080D18 |
| --border | navy-700 | #1A2540 |

## Full product family
| Product | Accent | Class | Container |
|---|---|---|---|
| WPGraphQL | #FF8C1A orange | *(default)* | Circle |
| RadiQL | #00D4F5 cyan | .theme-radiql | Rounded square |
| WPGraphQL IDE | #8B5CF6 violet | .theme-ide | Rounded square |
| WPGraphQL ACF | #10B981 emerald | .theme-acf | Rounded square |
