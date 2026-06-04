/**
 * Generate branded WordPress.org screenshot assets: composite a raw admin-UI
 * capture onto the sibling-brand frame (navy + accent glow + product logo, with
 * the UI in a rounded, shadowed window) plus a subtle constellation. Screenshotted
 * at 4000x2200 to match the existing assets.
 *
 * Edit the `sources` list per product, then run from the website workspace
 * (optionally pass plugin slugs to limit which products generate):
 *   cd websites/wpgraphql.com && node ../../design/brand/generate-wporg-screenshots.mjs
 */
import { chromium } from "@playwright/test"
import { fileURLToPath } from "node:url"
import fs from "node:fs"
import path from "node:path"
import { constellationSvg } from "./constellation.mjs"
import { marks } from "./marks.mjs"

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../")
const CLEANSHOT = "/Users/jasonbahl/Library/Application Support/CleanShot/media"

const products = [
  {
    slug: "wp-graphql",
    accent: "#FF8C1A",
    accentRgb: "255,140,26",
    mark: marks.wpgraphql,
    name: "WPGraphQL",
    accentWord: "",
    ext: "jpg",
    seed: 7, // match the core banner constellation
    sources: [
      `${CLEANSHOT}/media_LNR8wc4miM/CleanShot 2026-06-03 at 11.16.26.png`,
      `${CLEANSHOT}/media_5pEX74N5aQ/CleanShot 2026-06-03 at 11.16.46.png`,
    ],
  },
  {
    slug: "wp-graphql-ide",
    accent: "#8B5CF6",
    accentRgb: "139,92,246",
    mark: marks.ide,
    name: "WPGraphQL",
    accentWord: "IDE",
    ext: "jpg",
    seed: 11, // match the IDE banner constellation
    sources: [
      `${CLEANSHOT}/media_NRJBQu6aS9/CleanShot 2026-06-03 at 11.27.37.png`,
      `${CLEANSHOT}/media_aZqTbWgADu/CleanShot 2026-06-03 at 11.27.48.png`,
      `${CLEANSHOT}/media_kOPFgd5xqX/CleanShot 2026-06-03 at 11.29.22.png`,
    ],
  },
  {
    slug: "wp-graphql-acf",
    accent: "#10B981",
    accentRgb: "16,185,129",
    mark: marks.acf,
    name: "WPGraphQL",
    accentWord: "ACF",
    ext: "jpg",
    seed: 23, // match the ACF banner constellation
    sources: [
      `${CLEANSHOT}/media_XbPKB9wIi8/CleanShot 2026-06-03 at 09.33.37.png`,
      `${CLEANSHOT}/media_NbqDoViNff/CleanShot 2026-06-03 at 09.34.28.png`,
      `${CLEANSHOT}/media_xQKxF7mAvl/CleanShot 2026-06-03 at 09.36.35.png`,
    ],
  },
  {
    slug: "wp-graphql-smart-cache",
    accent: "#F43F5E",
    accentRgb: "244,63,94",
    mark: marks.smartCache,
    name: "WPGraphQL",
    accentWord: "Smart Cache",
    ext: "jpg",
    seed: 37, // match the Smart Cache banner constellation
    sources: [
      `${CLEANSHOT}/media_hvrY0MngK5/CleanShot 2026-06-03 at 11.35.12.png`,
      `${CLEANSHOT}/media_AVSs0tQGJl/CleanShot 2026-06-03 at 11.35.36.png`,
    ],
  },
]

const dataUri = (file) =>
  `data:image/png;base64,${fs.readFileSync(file).toString("base64")}`

const frameHtml = (p, imgUri) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&display=swap">
<style>
  html,body{margin:0;padding:0}
  .frame{width:2000px;height:1100px;background:#080D18;position:relative;overflow:hidden;
    display:flex;flex-direction:column;font-family:'Bricolage Grotesque',system-ui,sans-serif}
  .net{position:absolute;inset:0;z-index:0;opacity:0.5;pointer-events:none}
  .net svg{display:block;width:100%;height:100%}
  .glow{position:absolute;inset:0;z-index:0;pointer-events:none;background:
    radial-gradient(ellipse 1100px 760px at 76% -12%, rgba(${p.accentRgb},0.16) 0%, rgba(${p.accentRgb},0.05) 45%, transparent 70%),
    radial-gradient(ellipse 700px 560px at 8% 116%, rgba(${p.accentRgb},0.06) 0%, transparent 64%)}
  .head{position:relative;z-index:2;display:flex;align-items:center;gap:16px;padding:46px 88px 0}
  .head .mk{width:46px;height:46px;display:block}
  .head .wm{font-weight:800;font-size:31px;line-height:1;letter-spacing:-0.03em;color:#F0F4FF}
  .head .wm .a{color:${p.accent}}
  .stage{position:relative;z-index:2;flex:1;display:flex;align-items:center;justify-content:center;padding:34px 88px 84px}
  .win{width:1580px;border-radius:16px;overflow:hidden;background:#0C1220;
    border:1px solid rgba(${p.accentRgb},0.22);
    box-shadow:0 44px 110px -24px rgba(0,0,0,0.72), 0 0 90px -26px rgba(${p.accentRgb},0.28)}
  .win img{display:block;width:100%;height:auto}
</style></head>
<body>
  <div class="frame">
    <div class="net">${constellationSvg(2000, 1100, p.accentRgb, p.seed ?? 7, { clearFactor: 0.52, density: 0.7 })}</div>
    <div class="glow"></div>
    <div class="head">
      <div class="mk">${p.mark}</div>
      <div class="wm">${p.name} <span class="a">${p.accentWord}</span></div>
    </div>
    <div class="stage"><div class="win"><img src="${imgUri}"/></div></div>
  </div>
</body></html>`

// CLI: pass plugin slugs to limit which products are generated.
const slugs = process.argv.slice(2).filter((a) => !a.startsWith("--"))
const selected = slugs.length
  ? products.filter((p) => slugs.includes(p.slug))
  : products

const run = async () => {
  const browser = await chromium.launch()
  for (const p of selected) {
    for (let i = 0; i < p.sources.length; i++) {
      const src = p.sources[i]
      if (!fs.existsSync(src)) {
        console.warn(`SKIP missing source: ${src}`)
        continue
      }
      const page = await browser.newPage({
        viewport: { width: 2000, height: 1100 },
        deviceScaleFactor: 2,
      })
      await page.setContent(frameHtml(p, dataUri(src)), {
        waitUntil: "networkidle",
      })
      await page.evaluate(() => document.fonts.ready)
      const outPath = path.join(
        ROOT,
        "plugins",
        p.slug,
        ".wordpress-org",
        `screenshot-${i + 1}.${p.ext}`
      )
      await page.screenshot({
        path: outPath,
        type: p.ext === "jpg" ? "jpeg" : "png",
        ...(p.ext === "jpg" ? { quality: 92 } : {}),
      })
      await page.close()
      console.log(`wrote screenshot-${i + 1}.${p.ext} for ${p.slug}`)
    }
  }
  await browser.close()
}

run().catch((e) => {
  console.error(e)
  process.exit(1)
})
