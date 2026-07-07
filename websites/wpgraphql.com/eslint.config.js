/**
 * ESLint flat config for wpgraphql.com (Next.js app).
 *
 * Flat config for ESLint 10 — the legacy `.eslintrc` format and the
 * `next lint` command were both removed, so we run the ESLint CLI directly
 * (`eslint .`) against a flat config, mirroring the wp-graphql-acf migration.
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
