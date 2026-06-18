/**
 * Shared constellation generator for the WordPress.org brand assets — a graph of
 * accent nodes/edges radiating from the centered logo (the "entry point" to the
 * graph). Used by both the banner and screenshot generators.
 */

// Seeded PRNG (mulberry32) so the layout is deterministic per (size, seed).
export const mulberry32 = (seed) => () => {
  seed |= 0
  seed = (seed + 0x6d2b79f5) | 0
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed)
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296
}

/**
 * @param {number} w  canvas width
 * @param {number} h  canvas height
 * @param {string} accentRgb  "r,g,b"
 * @param {number} seed
 * @param {{clearFactor?: number, density?: number}} [opts]
 *   clearFactor — radius (× h) kept clear around the center (default 0.4)
 *   density     — node-count multiplier (default 1)
 */
export const constellationSvg = (w, h, accentRgb, seed = 7, opts = {}) => {
  const clearFactor = opts.clearFactor ?? 0.4
  const density = opts.density ?? 1
  const rnd = mulberry32(seed)
  const cx = w / 2
  const cy = h / 2
  const clear = h * clearFactor
  const target = Math.round((w / 1544) * 78 * density)
  const nodes = []
  let attempts = 0
  while (nodes.length < target && attempts < target * 30) {
    attempts++
    const x = rnd() * w
    const y = cy + (rnd() - 0.5) * h * 1.06
    if (y < 4 || y > h - 4) continue
    if (Math.hypot(x - cx, y - cy) < clear) continue
    nodes.push({ x, y, r: 1.1 + rnd() * rnd() * 5, glow: rnd() < 0.14 })
  }
  const maxLen = w * 0.085
  const edges = []
  for (let i = 0; i < nodes.length; i++) {
    const near = []
    for (let j = 0; j < nodes.length; j++) {
      if (i === j) continue
      const d = Math.hypot(nodes[i].x - nodes[j].x, nodes[i].y - nodes[j].y)
      if (d < maxLen) near.push([d, j])
    }
    near.sort((a, b) => a[0] - b[0])
    const k = 1 + Math.floor(rnd() * 2)
    for (let n = 0; n < Math.min(k, near.length); n++) {
      const j = near[n][1]
      if (i < j) edges.push([i, j, near[n][0]])
    }
  }
  const entry = nodes
    .map((nd, i) => [Math.hypot(nd.x - cx, nd.y - cy), i])
    .sort((a, b) => a[0] - b[0])
    .slice(0, 8)
    .map((d) => d[1])

  const line = (x1, y1, x2, y2, op) =>
    `<line x1="${x1.toFixed(1)}" y1="${y1.toFixed(1)}" x2="${x2.toFixed(1)}" y2="${y2.toFixed(1)}" stroke="rgba(${accentRgb},${op})" stroke-width="1"/>`

  let glow = ""
  let dots = ""
  for (const nd of nodes) {
    if (nd.glow)
      glow += `<circle cx="${nd.x.toFixed(1)}" cy="${nd.y.toFixed(1)}" r="${(nd.r * 3.4).toFixed(1)}" fill="rgba(${accentRgb},0.5)"/>`
    const op = (0.45 + rnd() * 0.55).toFixed(2)
    dots += `<circle cx="${nd.x.toFixed(1)}" cy="${nd.y.toFixed(1)}" r="${nd.r.toFixed(1)}" fill="rgba(${accentRgb},${op})"/>`
  }
  let lines = ""
  for (const [i, j, d] of edges)
    lines += line(
      nodes[i].x,
      nodes[i].y,
      nodes[j].x,
      nodes[j].y,
      (0.26 * (1 - d / maxLen) + 0.05).toFixed(3)
    )
  for (const idx of entry)
    lines += line(cx, cy, nodes[idx].x, nodes[idx].y, "0.2")

  return `<svg width="${w}" height="${h}" viewBox="0 0 ${w} ${h}" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs><filter id="cg" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="5"/></filter></defs>
    ${lines}
    <g filter="url(#cg)">${glow}</g>
    ${dots}
  </svg>`
}
