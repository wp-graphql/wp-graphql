/**
 * ESLint flat config for wpgraphql.com (Next.js app).
 *
 * Flat config, run via the ESLint CLI directly (`eslint .`) rather than the
 * removed `next lint` command, mirroring the wp-graphql-acf migration.
 *
 * ESLint is pinned to v9 for now: v10 requires @typescript-eslint v8+
 * everywhere, but npm hoists @typescript-eslint v6 to the monorepo root
 * (via @wordpress/scripts in the plugin workspaces), and ESLint 10 crashes
 * loading its scope-manager (`scopeManager.addGlobals is not a function`).
 * Unpin when @wordpress/scripts ships a typescript-eslint v8+ tree. The
 * dependabot ignore for the eslint major lives in .github/dependabot.yml.
 *
 * Layers:
 *   - eslint-config-next/core-web-vitals — Next's React / hooks / a11y /
 *     core-web-vitals rules (the same set `next lint` used to apply).
 *   - eslint-config-prettier — turned on last so all formatting is deferred
 *     to Prettier (see .prettierrc.js) rather than fought over by ESLint.
 */
const next = require("eslint-config-next/core-web-vitals")
const prettier = require("eslint-config-prettier/flat")

module.exports = [
  {
    // Replaces the old .eslintignore. Build output, generated assets, and
    // ephemeral test artifacts are never linted (kept in sync with
    // .prettierignore).
    ignores: [
      ".next/**",
      ".cache/**",
      ".yarn/**",
      "dist/**",
      "node_modules/**",
      "WordPress/**",
      "test-results/**",
      "playwright-report/**",
      "src/generated/**",
      "public/**",
    ],
  },
  ...next,
  prettier,
  {
    rules: {
      // The hydration-mount pattern (`useEffect(() => setMounted(true), [])`)
      // and one-shot platform sniffing in an effect are intentional and
      // correct here. react-hooks v7 flags synchronous setState in an effect
      // as an error by default; keep it visible as a warning rather than
      // forcing a refactor of working components in a lint-adoption pass.
      "react-hooks/set-state-in-effect": "warn",
    },
  },
]
