/**
 * Generate WordPress.org plugin assets (icon-128/256, banner-772x250/1544x500)
 * for the sibling-branded extensions, rendered from the brand marks + fonts and
 * screenshotted at exact pixel sizes with Playwright.
 *
 * Run from the website workspace (so `playwright` resolves):
 *   cd websites/wpgraphql.com && node ../../design/brand/generate-wporg-assets.mjs
 */
import { chromium } from "@playwright/test"
import { fileURLToPath } from "node:url"
import path from "node:path"
import { constellationSvg } from "./constellation.mjs"
import { marks } from "./marks.mjs"

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../")
const out = (slug, file) =>
  path.join(ROOT, "plugins", slug, ".wordpress-org", file)

const FONTS =
  "https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=DM+Mono:wght@400;500&display=swap"

const products = [
  {
    slug: "wp-graphql",
    mark: marks.wpgraphql,
    accent: "#FF8C1A",
    accentRgb: "255,140,26",
    name: "WPGraphQL",
    accentWord: "",
    tagline: "GraphQL for WordPress",
    minimal: true,
    graph: true, // constellation banner: graph of nodes radiating from the logo
    iconBg: "#0E1628", // fill the icon as a navy rounded square (the elephant mark is a circle)
  },
  {
    slug: "wp-graphql-ide",
    mark: marks.ide,
    accent: "#8B5CF6",
    accentRgb: "139,92,246",
    name: "WPGraphQL",
    accentWord: "IDE",
    tagline: "A modern GraphQL IDE for WordPress",
    minimal: true,
    graph: true,
    seed: 11,
  },
  {
    slug: "wp-graphql-acf",
    mark: marks.acf,
    accent: "#10B981",
    accentRgb: "16,185,129",
    name: "WPGraphQL",
    accentWord: "ACF",
    tagline: "Advanced Custom Fields, in GraphQL",
    minimal: true,
    graph: true,
    seed: 23,
  },
  {
    slug: "wp-graphql-smart-cache",
    mark: marks.smartCache,
    accent: "#F43F5E",
    accentRgb: "244,63,94",
    name: "WPGraphQL",
    accentWord: "Smart Cache",
    tagline: "Caching & invalidation for WPGraphQL",
    minimal: true,
    graph: true,
    seed: 37,
  },
]

const iconHtml = (p, size) => `<!doctype html><html><head><meta charset="utf-8">
<style>html,body{margin:0;padding:0;background:transparent}
.m{width:${size}px;height:${size}px${
  p.iconBg
    ? `;background:${p.iconBg};border-radius:${Math.round(size * 0.225)}px;overflow:hidden`
    : ""
}}.m svg{display:block;width:100%;height:100%}</style></head>
<body><div class="m">${p.mark}</div></body></html>`

const bannerHtml = (p, w, h) => {
  const mark = Math.round(h * 0.56)
  const nameSize = Math.round(h * 0.18)
  const tagSize = Math.round(h * 0.05)
  const pad = Math.round(h * 0.13)
  const gap = Math.round(h * 0.07)
  return `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="${FONTS}">
<style>
  html,body{margin:0;padding:0}
  .banner{width:${w}px;height:${h}px;display:flex;align-items:center;gap:${gap}px;
    padding:0 ${pad}px;box-sizing:border-box;background:#080D18;overflow:hidden;position:relative}
  .glow{position:absolute;inset:0;pointer-events:none;background:
    radial-gradient(ellipse ${Math.round(w * 0.5)}px ${Math.round(h * 1.3)}px at 82% 0%, rgba(${p.accentRgb},0.16) 0%, rgba(${p.accentRgb},0.05) 42%, transparent 72%),
    radial-gradient(ellipse ${Math.round(w * 0.3)}px ${Math.round(h * 0.9)}px at 5% 110%, rgba(${p.accentRgb},0.07) 0%, transparent 65%)}
  .mark{width:${mark}px;height:${mark}px;flex:0 0 auto;position:relative;
    filter:drop-shadow(0 0 ${Math.round(h * 0.06)}px rgba(${p.accentRgb},0.45))}
  .mark svg{display:block;width:100%;height:100%}
  .txt{position:relative;display:flex;flex-direction:column;justify-content:center}
  .name{font-family:'Bricolage Grotesque',system-ui,sans-serif;font-weight:800;
    font-size:${nameSize}px;line-height:1;letter-spacing:-0.03em;color:#F0F4FF}
  .name .accent{color:${p.accent}}
  .tag{font-family:'DM Mono',ui-monospace,monospace;font-weight:500;
    font-size:${tagSize}px;letter-spacing:0.04em;color:#96A8C8;margin-top:${Math.round(h * 0.05)}px}
</style></head>
<body><div class="banner"><div class="glow"></div>
  <div class="mark">${p.mark}</div>
  <div class="txt">
    <div class="name">${p.name} <span class="accent">${p.accentWord}</span></div>
    <div class="tag">${p.tagline}</div>
  </div>
</div></body></html>`
}

// CLI: pass plugin slugs to limit which products are generated, and `--banners`
// to skip icons. e.g. `node generate-wporg-assets.mjs wp-graphql-acf --banners`.
const argv = process.argv.slice(2)
const flags = argv.filter((a) => a.startsWith("--"))
const slugs = argv.filter((a) => !a.startsWith("--"))
const wantIcons = !flags.includes("--banners")
const selected = slugs.length
  ? products.filter((p) => slugs.includes(p.slug))
  : products

// Text-free banner: the brand mark centered on the navy field with an accent
// glow halo and faint ambient cards bleeding in from the sides for depth.
const minimalBannerHtml = (p, w, h) => {
  const mark = Math.round(h * 0.6)
  return `<!doctype html><html><head><meta charset="utf-8">
<style>
  html,body{margin:0;padding:0}
  .banner{width:${w}px;height:${h}px;background:#080D18;position:relative;overflow:hidden;
    display:flex;align-items:center;justify-content:center}
  .amb{position:absolute;top:50%;width:${Math.round(h * 0.95)}px;height:${Math.round(h * 1.5)}px;
    border-radius:${Math.round(h * 0.12)}px;background:#0C1220;opacity:0.55;
    filter:blur(${Math.round(h * 0.03)}px)}
  .amb.l{left:${-Math.round(h * 0.35)}px;transform:translateY(-50%) rotate(-8deg)}
  .amb.r{right:${-Math.round(h * 0.35)}px;transform:translateY(-50%) rotate(8deg)}
  .glow{position:absolute;inset:0;pointer-events:none;background:
    radial-gradient(ellipse ${Math.round(w * 0.28)}px ${Math.round(h * 0.95)}px at 50% 50%,
      rgba(${p.accentRgb},0.22) 0%, rgba(${p.accentRgb},0.07) 42%, transparent 70%)}
  .mark{position:relative;width:${mark}px;height:${mark}px;
    filter:drop-shadow(0 0 ${Math.round(h * 0.09)}px rgba(${p.accentRgb},0.5))
           drop-shadow(0 0 ${Math.round(h * 0.03)}px rgba(${p.accentRgb},0.35))}
  .mark svg{display:block;width:100%;height:100%}
</style></head>
<body><div class="banner">
  <div class="amb l"></div><div class="amb r"></div>
  <div class="glow"></div>
  <div class="mark">${p.mark}</div>
</div></body></html>`
}

const graphBannerHtml = (p, w, h) => {
  const mark = Math.round(h * 0.56)
  return `<!doctype html><html><head><meta charset="utf-8">
<style>
  html,body{margin:0;padding:0}
  .banner{width:${w}px;height:${h}px;background:#080D18;position:relative;overflow:hidden;
    display:flex;align-items:center;justify-content:center}
  .net{position:absolute;inset:0}
  .net svg{display:block;width:100%;height:100%}
  .glow{position:absolute;inset:0;pointer-events:none;background:
    radial-gradient(ellipse ${Math.round(w * 0.22)}px ${Math.round(h * 0.85)}px at 50% 50%,
      rgba(${p.accentRgb},0.22) 0%, rgba(${p.accentRgb},0.07) 45%, transparent 72%)}
  .mark{position:relative;width:${mark}px;height:${mark}px;
    filter:drop-shadow(0 0 ${Math.round(h * 0.09)}px rgba(${p.accentRgb},0.55))
           drop-shadow(0 0 ${Math.round(h * 0.03)}px rgba(${p.accentRgb},0.4))}
  .mark svg{display:block;width:100%;height:100%}
</style></head>
<body><div class="banner">
  <div class="net">${constellationSvg(w, h, p.accentRgb, p.seed)}</div>
  <div class="glow"></div>
  <div class="mark">${p.mark}</div>
</div></body></html>`
}

const run = async () => {
  const browser = await chromium.launch()
  for (const p of selected) {
    if (wantIcons) {
      for (const size of [256, 128]) {
        const page = await browser.newPage({
          viewport: { width: size, height: size },
          deviceScaleFactor: 1,
        })
        await page.setContent(iconHtml(p, size), { waitUntil: "networkidle" })
        await page.screenshot({
          path: out(p.slug, `icon-${size}x${size}.png`),
          omitBackground: true,
        })
        await page.close()
      }
    }
    for (const [w, h] of [
      [1544, 500],
      [772, 250],
    ]) {
      const page = await browser.newPage({
        viewport: { width: w, height: h },
        deviceScaleFactor: 1,
      })
      const html = p.graph
        ? graphBannerHtml(p, w, h)
        : p.minimal
          ? minimalBannerHtml(p, w, h)
          : bannerHtml(p, w, h)
      await page.setContent(html, { waitUntil: "networkidle" })
      await page.evaluate(() => document.fonts.ready)
      await page.screenshot({ path: out(p.slug, `banner-${w}x${h}.png`) })
      await page.close()
    }
    console.log(`generated ${wantIcons ? "assets" : "banners"} for ${p.slug}`)
  }
  await browser.close()
}

run().catch((e) => {
  console.error(e)
  process.exit(1)
})
