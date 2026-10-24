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
 * in the community grid below). Each entry notes which CMS listing its aliases
 * match, since most are defensive and match nothing today.
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
    // The CMS has no IDE listing today. This is defensive, covering one added
    // under the plugin's name.
    aliases: ["wpgraphql ide"],
  },
  {
    name: "WPGraphQL for ACF",
    href: "/extensions/wp-graphql-acf",
    description: "Expose Advanced Custom Fields to the GraphQL schema.",
    theme: "theme-acf",
    Mark: WPGraphQLACFLogoMark,
    // Title substrings that mark a CMS ExtensionPlugin listing as this plugin, so
    // it isn't shown again in the community grid. The CMS entry is currently
    // "WPGraphQL for Advanced Custom Fields"; the others are defensive, covering
    // a rename to the plugin's short name ("WPGraphQL for ACF") or a listing
    // under the Secure Custom Fields name.
    aliases: [
      "advanced custom fields",
      "for acf",
      "secure custom fields",
      "scf",
    ],
  },
  {
    name: "WPGraphQL Smart Cache",
    href: "/extensions/wp-graphql-smart-cache",
    description: "Fast responses with smart, tag-based invalidation.",
    theme: "theme-smart-cache",
    Mark: WPGraphQLSmartCacheLogoMark,
    // The CMS has no Smart Cache listing today. This is defensive, covering one
    // added under the plugin's name ("WPGraphQL Smart Cache").
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
