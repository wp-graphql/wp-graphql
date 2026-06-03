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

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../")
const out = (slug, file) =>
  path.join(ROOT, "plugins", slug, ".wordpress-org", file)

const FONTS =
  "https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=DM+Mono:wght@400;500&display=swap"

// Marks transcribed from src/components/<Product>/*Logo.tsx (default variant).
const marks = {
  ide: `<svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect width="160" height="160" rx="36" fill="#0C1220"/>
    <rect x="14" y="14" width="132" height="18" rx="4" fill="url(#ideGrad)"/>
    <defs><linearGradient id="ideGrad" x1="14" y1="14" x2="146" y2="32" gradientUnits="userSpaceOnUse">
      <stop offset="0%" stop-color="#9B72FF" stop-opacity="0.35"/><stop offset="100%" stop-color="#6B3FD0" stop-opacity="0"/>
    </linearGradient></defs>
    <rect x="14" y="14" width="132" height="18" rx="4" fill="#8B5CF6"/>
    <circle cx="25" cy="23" r="3.5" fill="rgba(255,255,255,0.55)"/>
    <circle cx="35" cy="23" r="3.5" fill="rgba(255,255,255,0.35)"/>
    <circle cx="45" cy="23" r="3.5" fill="rgba(255,255,255,0.2)"/>
    <circle cx="136" cy="23" r="7" fill="rgba(255,255,255,0.12)"/>
    <path d="M 133.5 20.5 L 139 23 L 133.5 25.5 Z" fill="rgba(255,255,255,0.7)"/>
    <rect x="79.5" y="38" width="1" height="108" fill="#1A2540"/>
    <rect x="19" y="44" width="36" height="2.5" rx="1.25" fill="#A78BFA" opacity="0.7"/>
    <rect x="24" y="51" width="44" height="2.5" rx="1.25" fill="#A78BFA" opacity="0.55"/>
    <rect x="29" y="58" width="32" height="2.5" rx="1.25" fill="#C4B5FD" opacity="0.45"/>
    <rect x="34" y="65" width="22" height="2.5" rx="1.25" fill="#6578A0" opacity="0.5"/>
    <rect x="29" y="72" width="30" height="2.5" rx="1.25" fill="#A78BFA" opacity="0.4"/>
    <rect x="24" y="79" width="36" height="2.5" rx="1.25" fill="#C4B5FD" opacity="0.35"/>
    <rect x="19" y="86" width="20" height="2.5" rx="1.25" fill="#6578A0" opacity="0.4"/>
    <rect x="86" y="44" width="20" height="2.5" rx="1.25" fill="#50FA7B" opacity="0.8"/>
    <rect x="91" y="51" width="44" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.55"/>
    <rect x="91" y="58" width="34" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.45"/>
    <rect x="96" y="65" width="38" height="2.5" rx="1.25" fill="#50FA7B" opacity="0.4"/>
    <rect x="96" y="72" width="28" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.4"/>
    <rect x="91" y="79" width="40" height="2.5" rx="1.25" fill="#96A8C8" opacity="0.35"/>
    <rect x="14" y="134" width="132" height="12" rx="3" fill="#5E2EC4" opacity="0.75"/>
    <rect x="19" y="137" width="40" height="2" rx="1" fill="rgba(255,255,255,0.4)"/>
    <rect x="106" y="137" width="34" height="2" rx="1" fill="rgba(255,255,255,0.25)"/>
  </svg>`,
  acf: `<svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect width="160" height="160" rx="36" fill="#0C1220"/>
    <rect x="14" y="14" width="132" height="14" rx="3.5" fill="#10B981" opacity="0.9"/>
    <rect x="18" y="17.5" width="14" height="7" rx="1.5" fill="rgba(255,255,255,0.25)"/>
    <rect x="14" y="34" width="132" height="10" fill="#131B30"/>
    <rect x="19" y="37" width="18" height="3" rx="1.5" fill="#435678" opacity="0.8"/>
    <rect x="52" y="37" width="28" height="3" rx="1.5" fill="#435678" opacity="0.8"/>
    <rect x="94" y="37" width="20" height="3" rx="1.5" fill="#435678" opacity="0.8"/>
    <rect x="14" y="44.75" width="132" height="16" fill="rgba(16,185,129,0.08)"/>
    <rect x="14" y="44.75" width="3" height="16" fill="#10B981"/>
    <rect x="19" y="50.5" width="22" height="3" rx="1.5" fill="#34D399" opacity="0.9"/>
    <rect x="52" y="50.5" width="34" height="3" rx="1.5" fill="#96A8C8" opacity="0.65"/>
    <rect x="94" y="50.5" width="24" height="3" rx="1.5" fill="#96A8C8" opacity="0.5"/>
    <rect x="14" y="60.75" width="132" height="0.75" fill="#1A2540"/>
    <rect x="19" y="66.5" width="18" height="3" rx="1.5" fill="#96A8C8" opacity="0.55"/>
    <rect x="52" y="66.5" width="28" height="3" rx="1.5" fill="#96A8C8" opacity="0.45"/>
    <rect x="94" y="66.5" width="20" height="3" rx="1.5" fill="#96A8C8" opacity="0.4"/>
    <rect x="14" y="76.75" width="132" height="0.75" fill="#1A2540"/>
    <rect x="19" y="82.5" width="20" height="3" rx="1.5" fill="#96A8C8" opacity="0.45"/>
    <rect x="52" y="82.5" width="32" height="3" rx="1.5" fill="#96A8C8" opacity="0.35"/>
    <rect x="14" y="92.75" width="132" height="0.75" fill="#1A2540"/>
    <rect x="19" y="98.5" width="16" height="3" rx="1.5" fill="#96A8C8" opacity="0.35"/>
    <rect x="52" y="98.5" width="24" height="3" rx="1.5" fill="#96A8C8" opacity="0.28"/>
    <rect x="14" y="130" width="60" height="16" rx="4" fill="rgba(16,185,129,0.15)" stroke="rgba(16,185,129,0.3)" stroke-width="1"/>
    <rect x="90" y="132" width="56" height="12" rx="3" fill="#10B981" opacity="0.75"/>
    <rect x="95" y="135.5" width="40" height="2.5" rx="1.25" fill="rgba(255,255,255,0.6)"/>
  </svg>`,
  smartCache: `<svg viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect width="160" height="160" rx="36" fill="#0C1220"/>
    <circle cx="80" cy="80" r="60" stroke="#F43F5E" stroke-width="1.5" fill="none" opacity="0.18"/>
    <circle cx="80" cy="80" r="44" stroke="#F43F5E" stroke-width="2" fill="none" opacity="0.40"/>
    <circle cx="80" cy="80" r="28" stroke="#F43F5E" stroke-width="2.5" fill="none" opacity="0.72"/>
    <path d="M 80 80 L 130 30 A 71 71 0 0 0 151 80 Z" fill="#F43F5E" opacity="0.05"/>
    <line x1="80" y1="80" x2="130" y2="30" stroke="#F43F5E" stroke-width="1" stroke-linecap="round" opacity="0.2"/>
    <circle cx="116" cy="44" r="2.5" fill="#FB7185" opacity="0.75"/>
    <circle cx="80" cy="80" r="9" fill="#F43F5E" opacity="0.93"/>
    <circle cx="80" cy="80" r="4.5" fill="#FFF1F2" opacity="0.87"/>
  </svg>`,
}

const products = [
  {
    slug: "wp-graphql-ide",
    mark: marks.ide,
    accent: "#8B5CF6",
    accentRgb: "139,92,246",
    name: "WPGraphQL",
    accentWord: "IDE",
    tagline: "A modern GraphQL IDE for WordPress",
  },
  {
    slug: "wp-graphql-acf",
    mark: marks.acf,
    accent: "#10B981",
    accentRgb: "16,185,129",
    name: "WPGraphQL",
    accentWord: "ACF",
    tagline: "Advanced Custom Fields, in GraphQL",
  },
  {
    slug: "wp-graphql-smart-cache",
    mark: marks.smartCache,
    accent: "#F43F5E",
    accentRgb: "244,63,94",
    name: "WPGraphQL",
    accentWord: "Smart Cache",
    tagline: "Caching & invalidation for WPGraphQL",
  },
]

const iconHtml = (p, size) => `<!doctype html><html><head><meta charset="utf-8">
<style>html,body{margin:0;padding:0;background:transparent}
.m{width:${size}px;height:${size}px}.m svg{display:block;width:100%;height:100%}</style></head>
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

const run = async () => {
  const browser = await chromium.launch()
  for (const p of products) {
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
    for (const [w, h] of [
      [1544, 500],
      [772, 250],
    ]) {
      const page = await browser.newPage({
        viewport: { width: w, height: h },
        deviceScaleFactor: 1,
      })
      await page.setContent(bannerHtml(p, w, h), { waitUntil: "networkidle" })
      await page.evaluate(() => document.fonts.ready)
      await page.screenshot({ path: out(p.slug, `banner-${w}x${h}.png`) })
      await page.close()
    }
    console.log(`generated assets for ${p.slug}`)
  }
  await browser.close()
}

run().catch((e) => {
  console.error(e)
  process.exit(1)
})
