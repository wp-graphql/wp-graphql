import Link from "next/link"

/**
 * Previous / next navigation for the bottom of a reference detail page.
 * `prev` and `next` are `{ href, label }` (or null at the ends).
 */
export default function PrevNext({ prev, next }) {
  if (!prev && !next) {
    return null
  }

  return (
    <nav
      aria-label="Previous and next"
      className="not-prose mt-12 flex items-stretch justify-between gap-4 border-t border-border pt-6"
    >
      {prev ? (
        <Link
          href={prev.href}
          className="group flex flex-1 flex-col items-start rounded-xl border border-border bg-card px-4 py-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-glow-sm"
        >
          <span className="font-mono text-xs uppercase tracking-widest text-muted-foreground">
            ← Previous
          </span>
          <span className="mt-1 font-medium text-foreground transition-colors group-hover:text-primary">
            {prev.label}
          </span>
        </Link>
      ) : (
        <span className="flex-1" />
      )}
      {next ? (
        <Link
          href={next.href}
          className="group flex flex-1 flex-col items-end rounded-xl border border-border bg-card px-4 py-3 text-right transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-glow-sm"
        >
          <span className="font-mono text-xs uppercase tracking-widest text-muted-foreground">
            Next →
          </span>
          <span className="mt-1 font-medium text-foreground transition-colors group-hover:text-primary">
            {next.label}
          </span>
        </Link>
      ) : (
        <span className="flex-1" />
      )}
    </nav>
  )
}
