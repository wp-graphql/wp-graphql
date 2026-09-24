const withBundleAnalyzer = require("@next/bundle-analyzer")({
  enabled: process.env.ANALYZE === "true",
})

/**
 * Allowlist the WordPress origin's own hostname for `next/image`.
 *
 * This covers media served directly by WordPress. It does NOT cover media
 * served through Jetpack's Site Accelerator, which rewrites URLs onto a
 * different hostname entirely — see PHOTON_REMOTE_PATTERNS below.
 */
function getWpRemotePattern() {
  const url =
    process.env.NEXT_PUBLIC_WORDPRESS_URL ||
    "https://contentwpgraphql.wpcomstaging.com"
  try {
    const parsed = new URL(url)
    return {
      protocol: parsed.protocol.replace(":", ""),
      hostname: parsed.hostname,
    }
  } catch {
    return {
      protocol: "https",
      hostname: "contentwpgraphql.wpcomstaging.com",
    }
  }
}

/**
 * Jetpack's Site Accelerator (Photon) rewrites media URLs onto its own CDN, so
 * a `sourceUrl` from the WordPress.com-hosted backend points at `i0.wp.com`
 * with the origin folded into the path:
 *
 *   https://i0.wp.com/contentwpgraphql.wpcomstaging.com/wp-content/uploads/...
 *
 * getWpRemotePattern() only knows the origin hostname, so it can't match these
 * on its own.
 *
 * All three of `i0`, `i1` and `i2` are allowlisted rather than just the shard
 * the backend happens to use today. Every attachment in the media library
 * currently resolves to `i0`, but post content carries hardcoded `i1` URLs
 * inherited from a much older host, so the other shards are not hypothetical.
 * They are interchangeable anyway: all three return byte-identical responses
 * for the same path.
 *
 * These are inert for a backend that isn't behind Site Accelerator — a local
 * install, or one with the feature switched off — which serves media from the
 * origin instead, the case getWpRemotePattern() already covers.
 */
const PHOTON_REMOTE_PATTERNS = ["i0.wp.com", "i1.wp.com", "i2.wp.com"].map(
  (hostname) => ({ protocol: "https", hostname })
)

const getHeaders = async () => {
  return [
    {
      source: "/:path*",
      headers: [
        {
          key: "Content-Security-Policy",
          value: "frame-ancestors 'self' *.wpgraphql.com",
        },
      ],
    },
  ]
}

/** @type {import('next').NextConfig} */
const nextConfig = withBundleAnalyzer({
  pageExtensions: ["ts", "tsx", "js", "jsx"],
  // @docsearch/react v4 is ESM-only (its exports map has no `require`
  // condition), so the serverless runtime crashes require()ing it during
  // on-demand ISR renders. Bundling it into the server build avoids the
  // runtime require entirely. feed v6 is ESM-only too (its `require`
  // condition points at an ESM file), so it gets the same treatment.
  transpilePackages: ["@docsearch/react", "feed"],
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "secure.gravatar.com" },
      { protocol: "https", hostname: "raw.githubusercontent.com" },
      getWpRemotePattern(),
      ...PHOTON_REMOTE_PATTERNS,
    ],
    disableStaticImages: true,
  },
  headers: async () => await getHeaders(),
  async redirects() {
    return require("./redirects.json")
  },
  rewrites: async () => [
    { source: "/rss.xml", destination: "/api/feeds/rss.xml" },
    { source: "/feed.atom", destination: "/api/feeds/feed.atom" },
    { source: "/feed.json", destination: "/api/feeds/feed.json" },
  ],
})

module.exports = nextConfig
