const { withFaust, getWpHostname } = require("@faustwp/core")
const path = require("path")
const fs = require("fs")

// Ensure possibleTypes.json exists before webpack processes faust.config.js
// This must happen at the top level, before any webpack configuration
// Use __dirname to ensure we're in the same directory as faust.config.js
const possibleTypesPath = path.join(__dirname, "possibleTypes.json")
if (!fs.existsSync(possibleTypesPath)) {
  console.log(`[next.config.js] Creating possibleTypes.json at: ${path.resolve(possibleTypesPath)}`)
  fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
} else {
  console.log(`[next.config.js] possibleTypes.json exists at: ${path.resolve(possibleTypesPath)}`)
}

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
      // This runs during webpack compilation, but the file should already exist
      // from the top-level check or from the prebuild step
      const possibleTypesPath = path.join(__dirname, "possibleTypes.json")
      if (!fs.existsSync(possibleTypesPath)) {
        // Create empty fallback file if it doesn't exist
        // This is a critical fallback - webpack needs this file to exist
        // when it processes faust.config.js
        try {
          fs.writeFileSync(possibleTypesPath, JSON.stringify({}), "utf-8")
          console.log(`[webpack] Created possibleTypes.json fallback at ${possibleTypesPath}`)
        } catch (error) {
          console.error(`[webpack] Failed to create possibleTypes.json: ${error.message}`)
        }
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
