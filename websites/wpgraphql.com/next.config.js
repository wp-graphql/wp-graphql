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
    webpack: (config, { isServer }) => {
      const path = require("path")
      const fs = require("fs")
      
      // Provide fallback for possibleTypes.json if it doesn't exist
      const possibleTypesPath = path.join(__dirname, "possibleTypes.json")
      if (!fs.existsSync(possibleTypesPath)) {
        // Create empty fallback file if it doesn't exist
        fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
      }
      
      // Mark Node.js built-in modules as external for faust.config.js
      if (isServer) {
        config.externals = config.externals || []
        if (Array.isArray(config.externals)) {
          config.externals.push({
            "fs": "commonjs fs",
            "path": "commonjs path",
            "url": "commonjs url",
            "module": "commonjs module",
          })
        }
      }
      return config
    },
  })
)

module.exports = nextConfig
