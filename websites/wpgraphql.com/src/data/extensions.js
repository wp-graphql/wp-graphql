/**
 * First-party, sibling-branded extensions — used by the header "Extensions"
 * dropdown and featured on the /extensions archive (rendered by
 * src/wp-templates/archive.js).
 *
 * Each has its own brand logo mark + theme scope class so cards/menus render in
 * the product's accent color, and links to its dedicated landing page.
 *
 * `aliases` are lowercase substrings used to de-duplicate these from the
 * headless ExtensionPlugin CPT list (so a featured extension isn't also shown
 * in the community grid below).
 *
 * The community list itself is NOT hardcoded — it's sourced headlessly from the
 * WordPress `ExtensionPlugin` post type via the archive template.
 */

import { WPGraphQLIDELogoMark } from "@/components/IDE/WPGraphQLIDELogo"
import { WPGraphQLACFLogoMark } from "@/components/ACF/WPGraphQLACFLogo"
import { WPGraphQLSmartCacheLogoMark } from "@/components/SmartCache/WPGraphQLSmartCacheLogo"

export const featuredExtensions = [
  {
    name: "WPGraphQL IDE",
    href: "/extensions/wp-graphql-ide",
    description: "A schema-aware GraphQL IDE, native to wp-admin.",
    theme: "theme-ide",
    Mark: WPGraphQLIDELogoMark,
    aliases: ["wpgraphql ide"],
  },
  {
    name: "WPGraphQL for ACF",
    href: "/extensions/wp-graphql-acf",
    description: "Expose Advanced Custom Fields to the GraphQL schema.",
    theme: "theme-acf",
    Mark: WPGraphQLACFLogoMark,
    aliases: ["advanced custom fields", "for acf"],
  },
  {
    name: "WPGraphQL Smart Cache",
    href: "/extensions/wp-graphql-smart-cache",
    description: "Fast responses with smart, tag-based invalidation.",
    theme: "theme-smart-cache",
    Mark: WPGraphQLSmartCacheLogoMark,
    aliases: ["smart cache"],
  },
]

// True if a headless ExtensionPlugin node matches one of the featured
// extensions (so it can be filtered out of the community grid).
export function isFeaturedExtension(title = "") {
  const t = title.toLowerCase()
  return featuredExtensions.some((ext) =>
    ext.aliases.some((alias) => t.includes(alias))
  )
}
