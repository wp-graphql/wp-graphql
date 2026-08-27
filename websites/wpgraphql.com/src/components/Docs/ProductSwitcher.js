import Link from "next/link"

import { enabledProducts } from "lib/docs-products"

/**
 * The docs-portal product switcher: lets readers move between the family's
 * doc sets (WPGraphQL, WPGraphQL for ACF, Smart Cache, IDE) from anywhere in
 * the docs area. The active product is highlighted with the product accent
 * (each docs section is wrapped in its sibling-brand theme scope, so
 * `--primary` is already the right hue here).
 */
export default function ProductSwitcher({ product }) {
  const products = enabledProducts()

  if (products.length < 2) {
    return null
  }

  return (
    <nav aria-label="Documentation product" className="mb-6">
      <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
        Documentation
      </p>
      <ul className="flex flex-col gap-1">
        {products.map((item) => {
          const isActive = item.key === product?.key
          return (
            <li key={item.key}>
              <Link
                href={item.basePath}
                aria-current={isActive ? "page" : undefined}
                className={
                  isActive
                    ? "block rounded-md border border-primary/40 bg-primary/10 px-3 py-1.5 text-sm font-medium text-primary"
                    : "block rounded-md border border-transparent px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:border-border hover:text-foreground"
                }
              >
                {item.label}
              </Link>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
