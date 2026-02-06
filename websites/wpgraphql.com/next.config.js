const { withFaust, getWpHostname } = require("@faustwp/core")

const withBundleAnalyzer = require("@next/bundle-analyzer")({
  enabled: process.env.ANALYZE === "true",
})

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
    ];
}

/** @type {import('next').NextConfig} */
const nextConfig = withFaust(
  withBundleAnalyzer({
    swcMinify: true,
    experimental: {
      runtime: "nodejs",
    },
    pageExtensions: ["ts", "tsx", "js", "jsx"],
    images: {
      domains: [
        "secure.gravatar.com",
        "raw.githubusercontent.com",
        getWpHostname(),
      ],
      disableStaticImages: true,
    },
    headers: async () => await getHeaders(),
    async redirects() {
      return require("./redirects.json")
    },
    rewrites: async () => [
      {
        source: "/rss.xml",
        destination: "/api/feeds/rss.xml",
      },
      {
        source: "/feed.atom",
        destination: "/api/feeds/feed.atom",
      },
      {
        source: "/feed.json",
        destination: "/api/feeds/feed.json",
      },
    ],
  })
)

module.exports = nextConfig
