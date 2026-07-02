/**
 * Brand-tinted constellation field — the glowing star-node motif from the
 * WordPress.org plugin banners, as a reusable background layer.
 *
 * Color comes from the surrounding `.theme-*` scope via `currentColor`
 * (`text-primary`), so the same field renders orange on WPGraphQL surfaces,
 * violet under `.theme-ide`, emerald under `.theme-acf`, rose under
 * `.theme-smart-cache`, etc.
 *
 * The field is generated once per unique `(variant, count, width, height)` with
 * a seeded PRNG (no `Math.random`) and memoized, so server and client render
 * identically (no hydration mismatch) and repeated instances are free.
 *
 * Render it inside a `relative overflow-hidden` container; it positions itself
 * absolutely and fills the box (`preserveAspectRatio="xMidYMid slice"`).
 */

function seededRandom(seed) {
  let a = seed >>> 0
  return () => {
    a = (a + 0x6d2b79f5) | 0
    let t = Math.imul(a ^ (a >>> 15), 1 | a)
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}

function buildField(seed, count, width, height) {
  const rand = seededRandom(seed * 9301 + 49297)
  const points = Array.from({ length: count }, () => [
    +(rand() * width).toFixed(1),
    +(rand() * height).toFixed(1),
  ])
  // Brighter "glowing" nodes, chosen deterministically.
  const bright = points.map(() => rand() < 0.3)
  // Connect each node to its nearest neighbor (and sometimes a second) to form
  // loose constellation clusters, skipping links that span too far. The cutoff
  // scales with node density so it adapts to any field size.
  const spacing = Math.sqrt((width * height) / count)
  const maxDistSq = (spacing * 1.7) ** 2
  const seen = new Set()
  const edges = []
  points.forEach((p, i) => {
    const ranked = points
      .map((q, j) => [j, (p[0] - q[0]) ** 2 + (p[1] - q[1]) ** 2])
      .filter(([j]) => j !== i)
      .sort((a, b) => a[1] - b[1])
    const links = 1 + (rand() > 0.6 ? 1 : 0)
    for (let k = 0; k < links && k < ranked.length; k++) {
      const [j, distSq] = ranked[k]
      if (distSq > maxDistSq) continue
      const key = i < j ? `${i}-${j}` : `${j}-${i}`
      if (seen.has(key)) continue
      seen.add(key)
      edges.push([i, j])
    }
  })
  return { points, bright, edges }
}

const cache = new Map()
function getField(seed, count, width, height) {
  const key = `${seed}:${count}:${width}:${height}`
  let field = cache.get(key)
  if (!field) {
    field = buildField(seed, count, width, height)
    cache.set(key, field)
  }
  return field
}

/**
 * @param {number} [intensity=1] Multiplies node/line opacity (and nudges node
 *   size) so the same field can read as a faint backdrop (≈1) or a prominent
 *   accent (>1, e.g. on hover). Per-node opacities are clamped at 1.
 */
export default function Constellation({
  variant = 0,
  count = 16,
  width = 320,
  height = 360,
  className = "",
  opacity = 0.7,
  intensity = 1,
}) {
  const { points, bright, edges } = getField(variant, count, width, height)
  const clamp = (v) => Math.min(1, +(v * intensity).toFixed(3))
  const grow = Math.min(1.5, 0.5 + intensity / 2) // 1 → 1×, higher → larger nodes
  return (
    <svg
      aria-hidden="true"
      viewBox={`0 0 ${width} ${height}`}
      preserveAspectRatio="xMidYMid slice"
      style={{ opacity }}
      className={`pointer-events-none absolute inset-0 h-full w-full text-primary ${className}`}
    >
      <g stroke="currentColor" strokeWidth={0.6 * grow}>
        {edges.map(([i, j], k) => (
          <line
            key={k}
            x1={points[i][0]}
            y1={points[i][1]}
            x2={points[j][0]}
            y2={points[j][1]}
            strokeOpacity={clamp(0.18)}
          />
        ))}
      </g>
      <g fill="currentColor">
        {points.map(([x, y], i) =>
          bright[i] ? (
            <g key={i}>
              <circle cx={x} cy={y} r={3.4 * grow} fillOpacity={clamp(0.12)} />
              <circle cx={x} cy={y} r={1.5 * grow} fillOpacity={clamp(0.9)} />
            </g>
          ) : (
            <circle
              cx={x}
              cy={y}
              r={1 * grow}
              fillOpacity={clamp(0.45)}
              key={i}
            />
          )
        )}
      </g>
    </svg>
  )
}
