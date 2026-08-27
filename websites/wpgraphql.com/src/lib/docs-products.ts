/**
 * The docs portal product registry.
 *
 * wpgraphql.com serves documentation for the whole WPGraphQL product family
 * from one portal. Core is the hub and keeps its historical URLs at /docs/**;
 * extensions mount at /docs/<key>/** using short product keys (the rule of
 * thumb: marketing routes carry full product names, e.g.
 * /extensions/wp-graphql-acf, while docs paths use short keys).
 *
 * Each product's markdown lives in its plugin's docs folder in the monorepo,
 * alongside a docs_nav.json describing the sidebar. Nav hrefs are stored
 * relative to the product's docs root (or as ./file.md links, which render on
 * GitHub); normalizeNavHref() maps any of the shapes found in the wild onto
 * the product's site base path.
 */

export type DocsProduct = {
  /** Short product key; also the reserved first path segment under /docs. */
  key: string
  /** Full product name, used in the switcher and page chrome. */
  label: string
  /** Site base path for the product's docs. */
  basePath: string
  /** Monorepo folder holding the product's markdown + docs_nav.json. */
  docsFolder: string
  /** Sibling-brand theme scope class; null renders the default (orange). */
  themeClass: string | null
  /**
   * Whether the product appears in the switcher and prerenders paths.
   * Disable products whose docs folder isn't portal-ready yet (e.g. missing
   * docs_nav.json) — the routes still resolve for enabled products only.
   */
  enabled: boolean
}

export const CORE_PRODUCT_KEY = "wp-graphql"

export const DOCS_PRODUCTS: Record<string, DocsProduct> = {
  [CORE_PRODUCT_KEY]: {
    key: CORE_PRODUCT_KEY,
    label: "WPGraphQL",
    basePath: "/docs",
    docsFolder: "plugins/wp-graphql/docs",
    themeClass: null,
    enabled: true,
  },
  acf: {
    key: "acf",
    label: "WPGraphQL for ACF",
    basePath: "/docs/acf",
    docsFolder: "plugins/wp-graphql-acf/docs",
    themeClass: "theme-acf",
    enabled: true,
  },
  "smart-cache": {
    key: "smart-cache",
    label: "WPGraphQL Smart Cache",
    basePath: "/docs/smart-cache",
    docsFolder: "plugins/wp-graphql-smart-cache/docs",
    themeClass: "theme-smart-cache",
    enabled: true,
  },
  ide: {
    key: "ide",
    label: "WPGraphQL IDE",
    basePath: "/docs/ide",
    docsFolder: "plugins/wp-graphql-ide/docs",
    themeClass: "theme-ide",
    enabled: true,
  },
}

export const CORE_PRODUCT = DOCS_PRODUCTS[CORE_PRODUCT_KEY]

/**
 * The extension keys are reserved first segments in the /docs namespace: a
 * core doc may never use one of these slugs. Guarded here and (belt and
 * braces) by core docs review.
 */
export const RESERVED_DOCS_SEGMENTS = Object.keys(DOCS_PRODUCTS).filter(
  (key) => key !== CORE_PRODUCT_KEY
)

export function enabledProducts(): DocsProduct[] {
  return Object.values(DOCS_PRODUCTS).filter((product) => product.enabled)
}

/**
 * Resolve which product a /docs/<...slug> request belongs to.
 *
 * Returns the product plus the slug parts relative to the product's docs
 * root. A reserved-but-disabled product resolves to null (404) rather than
 * falling through to core, so a core doc can never shadow a product key.
 */
export function productFromSlugParts(slugParts: string[]): {
  product: DocsProduct
  restParts: string[]
} | null {
  const [first, ...rest] = slugParts

  if (first && RESERVED_DOCS_SEGMENTS.includes(first)) {
    const product = DOCS_PRODUCTS[first]
    if (!product.enabled) {
      return null
    }
    return { product, restParts: rest }
  }

  return { product: CORE_PRODUCT, restParts: slugParts }
}

/**
 * Normalize a docs_nav.json href onto the product's site base path.
 *
 * Handles the shapes found across the plugins' nav files:
 * - core: absolute site paths ("/docs/introduction") — already based
 * - ACF:  product-relative paths ("/installation-and-activation")
 * - IDE:  GitHub-browsable file links ("./introduction.md")
 */
export function normalizeNavHref(product: DocsProduct, href: string): string {
  if (typeof href !== "string" || href.length === 0) {
    return href
  }

  // External links pass through untouched.
  if (/^(?:https?:)?\/\//i.test(href)) {
    return href
  }

  let slug = href.replace(/^\.\//, "").replace(/\.mdx?$/i, "")

  // Already expressed against the product's base path.
  if (slug === product.basePath || slug.startsWith(`${product.basePath}/`)) {
    return slug
  }

  slug = slug.replace(/^\/+/, "").replace(/\/+$/, "")

  return slug ? `${product.basePath}/${slug}` : product.basePath
}

/**
 * Normalize a whole grouped docs nav ({ section: [{ title, href }] }) for a
 * product, returning the same shape with site-ready hrefs.
 */
export function normalizeDocsNav(
  product: DocsProduct,
  nav: Record<string, Array<{ title?: string; href?: string }>>
): Record<string, Array<{ title?: string; href?: string }>> {
  if (!nav || typeof nav !== "object") {
    return nav
  }

  const normalized: Record<
    string,
    Array<{ title?: string; href?: string }>
  > = {}
  for (const [section, items] of Object.entries(nav)) {
    if (!Array.isArray(items)) {
      continue
    }
    normalized[section] = items.map((item) =>
      item && typeof item.href === "string"
        ? { ...item, href: normalizeNavHref(product, item.href) }
        : item
    )
  }
  return normalized
}
