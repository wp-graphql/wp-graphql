import { configure } from "./next-wpgraphql"
import templates from "wp-templates"
import { Layout } from "components/Site/SiteLayout"

configure({ templates, Layout })

export const NEXT_WPGRAPHQL_PREFIXES = (process.env.NEXT_WPGRAPHQL_FOR ?? "")
  .split(",")
  .map((s) => s.trim())
  .filter(Boolean)

export function shouldUseNextWpGraphQL(ctx) {
  if (NEXT_WPGRAPHQL_PREFIXES.length === 0) return false
  const node = ctx?.params?.wordpressNode ?? []
  const segments = Array.isArray(node) ? node : [node]
  const path = "/" + segments.join("/")
  return NEXT_WPGRAPHQL_PREFIXES.some((prefix) => path === prefix || path.startsWith(prefix + "/"))
}
