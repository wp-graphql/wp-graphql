import Link from "next/link"

import { docsPortalProducts } from "../../data/docs-portal"

/**
 * The /docs landing page: the family strip. This is where "WPGraphQL the
 * ecosystem" becomes navigable — a short vision layer, then one card per
 * product's documentation, before the reader narrows into a product hub.
 */
export default function DocsHub() {
  const products = docsPortalProducts()

  return (
    <div id="content-wrapper" className="relative z-20 mt-8 prose">
      <header className="relative z-20 -mt-8">
        <h1>Documentation</h1>
      </header>
      <p>
        WPGraphQL brings GraphQL to WordPress: the core plugin provides the
        GraphQL schema and API for your WordPress site, and a family of
        first-party extensions builds on it — exposing ACF fields, caching
        responses, and reimagining the IDE. Each product has its own
        documentation, all searchable from anywhere on the site.
      </p>

      <div className="not-prose mt-8 grid gap-4 sm:grid-cols-2">
        {products.map(({ key, label, basePath, description, theme, Mark }) => (
          <Link key={key} href={basePath} legacyBehavior>
            <a
              className={`${theme} group flex items-start gap-4 rounded-xl border border-border bg-card p-5 transition-colors hover:border-primary/50 hover:bg-accent`}
            >
              {Mark && (
                <Mark
                  size={44}
                  className="h-11 w-11 flex-shrink-0 rounded-md"
                />
              )}
              <div>
                <p className="text-base font-semibold text-foreground group-hover:text-primary">
                  {label}
                </p>
                <p className="mt-1 text-sm text-muted-foreground">
                  {description}
                </p>
              </div>
            </a>
          </Link>
        ))}
      </div>

      <p className="mt-8">
        New to WPGraphQL? Start with the{" "}
        <Link href="/docs/introduction">introduction</Link> or the{" "}
        <Link href="/docs/quick-start">quick start</Link>.
      </p>
    </div>
  )
}
