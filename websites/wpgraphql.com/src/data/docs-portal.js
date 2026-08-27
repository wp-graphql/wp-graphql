/**
 * Presentation data for the docs portal's product family — joins the docs
 * product registry (lib/docs-products) with each product's brand mark, theme
 * scope, and a one-line description. Used by the header "Docs" dropdown and
 * the /docs family hub.
 *
 * The registry decides WHICH products exist and where their docs live; this
 * file only decides how they look when presented as a family.
 */

import { WPGraphQLLogoMark } from "@/components/Site/WPGraphQLLogo"
import { WPGraphQLIDELogoMark } from "@/components/IDE/WPGraphQLIDELogo"
import { WPGraphQLACFLogoMark } from "@/components/ACF/WPGraphQLACFLogo"
import { WPGraphQLSmartCacheLogoMark } from "@/components/SmartCache/WPGraphQLSmartCacheLogo"
import { enabledProducts } from "lib/docs-products"

const PRESENTATION = {
  "wp-graphql": {
    description: "The GraphQL API for WordPress: schema, queries, mutations.",
    Mark: WPGraphQLLogoMark,
    theme: "",
  },
  acf: {
    description: "Query Advanced Custom Fields field groups with GraphQL.",
    Mark: WPGraphQLACFLogoMark,
    theme: "theme-acf",
  },
  "smart-cache": {
    description: "Caching, invalidation, and persisted queries.",
    Mark: WPGraphQLSmartCacheLogoMark,
    theme: "theme-smart-cache",
  },
  ide: {
    description: "Extend and embed the GraphQL IDE in wp-admin.",
    Mark: WPGraphQLIDELogoMark,
    theme: "theme-ide",
  },
}

/**
 * The enabled products, decorated for family presentation (mark, theme,
 * description), in registry order with core first.
 */
export function docsPortalProducts() {
  return enabledProducts().map((product) => ({
    ...product,
    ...(PRESENTATION[product.key] ?? {}),
  }))
}
