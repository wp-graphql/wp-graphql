const withBundleAnalyzer = require("@next/bundle-analyzer")({
  enabled: process.env.ANALYZE === "true",
})

function getWpRemotePattern() {
  const url = process.env.NEXT_PUBLIC_WORDPRESS_URL || "https://wp.wpgraphql.com"
  try {
    const parsed = new URL(url)
    return {
      protocol: parsed.protocol.replace(":", ""),
      hostname: parsed.hostname,
    }
  } catch {
    return { protocol: "https", hostname: "wp.wpgraphql.com" }
  }
}

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
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "secure.gravatar.com" },
      { protocol: "https", hostname: "raw.githubusercontent.com" },
      getWpRemotePattern(),
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
