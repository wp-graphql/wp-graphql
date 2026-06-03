# WPGraphQL Product Family — Brand Guides

Source-of-truth design system for the WPGraphQL family of products. Each product
shares a navy foundation and differs only by an accent color. These guides were
produced alongside the sibling-brand landing pages on `wpgraphql.com`.

| Product | Accent | Theme class | Folder |
| --- | --- | --- | --- |
| WPGraphQL (core) | `#FF8C1A` orange | *(default — no class)* | [`wpgraphql/`](./wpgraphql) |
| WPGraphQL IDE | `#8B5CF6` violet | `.theme-ide` | [`ide/`](./ide) |
| WPGraphQL for ACF | `#10B981` emerald | `.theme-acf` | [`acf/`](./acf) |
| WPGraphQL Smart Cache | `#F43F5E` rose | `.theme-smart-cache` | [`smart-cache/`](./smart-cache) |
| RadiQL | `#00D4F5` cyan | `.theme-radiql` | *(in family-globals)* |

Each folder contains: `README.md` (the brand guide), `tokens.ts` (token source),
`family-globals.css` (all products' CSS variables in one file), `globals.css`
(standalone-app variant), `tailwind.config.ts`, `components.json`, and the
product's logo component.

## How these map to the website

These are **reference** material, not imported directly. The website
(`websites/wpgraphql.com`) already mirrors this token architecture in its own
`src/styles/globals.css` (navy foundation + a single `--primary` accent consumed
via `hsl(var(--…))`). Each sibling brand is added there as a scoped theme class
(e.g. `.theme-ide`, `.theme-acf`) that overrides only the accent tokens
(`--primary` / `--ring` / `--glow`), so wrapping an extension landing page body in
that class re-tints it. Logo components are ported into
`src/components/<Product>/` (with a `useId`-based SVG gradient id for SSR safety).

## Shipped conventions (where the website is the source of truth)

The first extension pages — `/extensions/wp-graphql-ide` (violet) and
`/extensions/wp-graphql-acf` (emerald) — established the conventions below. Where
they differ from the standalone guide files, **the website is canonical**; these
notes keep the guides honest about what actually ships.

- **Scoped theme classes override only the accent.** A `.theme-*` block sets
  `--primary`, `--ring`, and `--glow` (plus `--ide-status-bar` for the IDE). The
  navy foundation, surfaces, and light/dark behavior are inherited; the
  `chart-*` / `sidebar-*` tokens from the guides' `family-globals.css` are not
  used on the site. `--primary-foreground` is left inherited (navy reads on the
  light dark-mode accent; the `.light` block supplies a near-white foreground for
  the darker light-mode accent).
- **`--glow` token.** Glow shadows (`--shadow-glow-*`) and the `glow-pulse`
  keyframe read `hsl(var(--glow) / …)`, so each theme scope re-tints every glow
  utility. The standalone guides hard-code glow color per product; the site uses
  this single indirection instead.
- **Per-section brand override.** Only the page *body* adopts the sibling brand;
  the shared site header and footer stay WPGraphQL-orange. (The brand guides'
  "per-section override" note, applied site-wide.)
- **Logo mark as the hero visual.** The hero leads with the large product logo
  mark + accent glow (not a product screenshot/mock), with a small uppercase
  product eyebrow above the headline.
- **Two-line accent headings + icon eyebrow.** Section titles are two short
  sentences stacked, with the entire second line in `text-primary` (the
  WPGraphQL/RadiQL hero pattern). Eyebrows are an uppercase mono label with a
  product-fitting glyph (IDE: command-line; ACF: field-group table) flanked by
  gradient rules. Shared impl: `src/components/extensions/SectionHeading.js`.
- **Navy values differ from the guides.** The site's navy scale predates these
  guides (e.g. site `--navy-950: 222 47% 8%` vs guide `224 48% 7%`). The site's
  values are intentionally left as-is; only the accent scales (violet, emerald,
  …) were transcribed from the guides.

## WordPress.org asset generation

This directory also holds the scripts that generate the WordPress.org plugin
assets (icons, banners, screenshots) for each product, so they can be
re-rendered whenever the branding or a plugin's UI changes — nothing is
hand-drawn in an image editor. Generated files are written into each plugin's
`plugins/<slug>/.wordpress-org/` directory.

### Files

| File | Purpose |
| --- | --- |
| `marks.mjs` | The product brand marks as inline SVG (`ide` / `acf` / `smartCache` / `wpgraphql`), transcribed from the website logo components. Shared by both generators. |
| `constellation.mjs` | Generates the "constellation" graph SVG — a network of accent nodes/edges radiating from the logo. Seeded + deterministic; options: `clearFactor` (radius kept clear around the logo) and `density` (node count). |
| `generate-wporg-assets.mjs` | Renders **icons** (128/256) and **banners** (772×250, 1544×500). |
| `generate-wporg-screenshots.mjs` | Composites raw admin-UI captures onto a branded **frame** (4000×2200). |

### How it works

Each script defines a `products` array. Every entry sets the product `slug`,
`accent` / `accentRgb`, its `mark` (from `marks.mjs`), the wordmark `name` /
`accentWord`, and per-asset flags. The script builds on-brand HTML and
screenshots it at exact pixel sizes with **Playwright** (Chromium), then writes
the PNG/JPG into `plugins/<slug>/.wordpress-org/`.

- **Banners** support a `minimal` variant (centered mark + accent glow) and a
  `graph: true` variant (the constellation). `seed` controls the constellation
  layout so each product differs.
- **Screenshots** list `sources` (paths to the raw UI captures). Each is drawn
  in a rounded, shadowed window on the navy field, with the product logo and a
  subtle constellation in the margins. `ext` picks `jpg`/`png`.
- Wordmark/tagline text uses **Bricolage Grotesque** / **DM Mono** loaded from
  Google Fonts at render time.

### Prerequisites

- Install the Chromium build Playwright expects (one-time):
  `npx playwright install chromium`
- Run the scripts **from the website workspace** so `@playwright/test` resolves:
  `cd websites/wpgraphql.com`
- Network access (for the Google Fonts used in banners/screenshots).

### Commands

```bash
cd websites/wpgraphql.com

# Icons + banners for every product
node ../../design/brand/generate-wporg-assets.mjs

# Limit to one product; --banners skips icons
node ../../design/brand/generate-wporg-assets.mjs wp-graphql-ide --banners

# Screenshots for every product (needs the source captures to exist)
node ../../design/brand/generate-wporg-screenshots.mjs

# Limit screenshots to one product
node ../../design/brand/generate-wporg-screenshots.mjs wp-graphql-acf
```

Both generators accept plugin slugs as positional args to limit which products
they render.

### Updating assets

- **New/changed icon or banner:** edit the product's accent / mark / flags in
  `generate-wporg-assets.mjs` and re-run it.
- **New screenshots:** capture the raw admin UI, set the product's `sources`
  paths in `generate-wporg-screenshots.mjs`, run it, then update the
  `== Screenshots ==` captions in that plugin's `readme.txt` (order matters —
  caption *n* describes `screenshot-n`).
- **Heads-up:** the screenshot `sources` currently point at absolute paths on
  the author's machine (a local CleanShot folder). Swap in your own capture
  paths before running the screenshot generator.
