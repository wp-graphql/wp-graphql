import { getRegistry } from "./templates.js"
import { runQueries } from "./run-queries.js"

/**
 * Run only the configured Layout.queries and return the merged data object.
 * Useful for non-template pages that still need shared chrome data.
 */
export async function getLayoutData(reqCtx = {}) {
  const { Layout } = getRegistry()
  const layoutEntries = Object.entries(Layout?.queries ?? {})
  return runQueries(layoutEntries, reqCtx)
}
