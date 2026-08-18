import Link from "next/link"

/**
 * Breadcrumb trail for Developer Reference pages, e.g.
 * "Developer Reference / Actions / graphql_init".
 *
 * Items are `{ label, href }`; the last item is treated as the current
 * page and rendered unlinked.
 */
export default function Breadcrumbs({ items }) {
  if (!items || items.length === 0) {
    return null
  }

  return (
    <nav aria-label="Breadcrumb" className="not-prose mb-4">
      <ol className="flex flex-wrap items-center gap-1.5 font-mono text-xs tracking-wide text-muted-foreground">
        {items.map((item, index) => {
          const isLast = index === items.length - 1

          return (
            <li key={item.label} className="flex items-center gap-1.5">
              {index > 0 && (
                <span aria-hidden="true" className="text-border">
                  /
                </span>
              )}
              {item.href && !isLast ? (
                <Link
                  href={item.href}
                  className="transition-colors hover:text-primary"
                >
                  {item.label}
                </Link>
              ) : (
                <span
                  aria-current={isLast ? "page" : undefined}
                  className="text-foreground"
                >
                  {item.label}
                </span>
              )}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
