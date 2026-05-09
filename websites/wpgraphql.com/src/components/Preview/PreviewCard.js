import Link from "next/link"
import { ArrowTopRightOnSquareIcon } from "@heroicons/react/24/outline"

/**
 * Card layout shared by Recipe / Filter / Function / Action / Extension
 * preview components in archive listings.
 *
 * The whole card is clickable via a "stretched link" pattern: the actual
 * anchor wraps the title text, with `position: absolute; inset: 0` to cover
 * the card. This keeps semantics clean (no <article> nested inside <a>,
 * which triggered a hydration mismatch under Next.js 15) while still giving
 * us a card-sized click target.
 */
export default function PreviewCard({
  title,
  excerpt,
  href,
  cta = "View",
  external = false,
}) {
  const linkClass =
    "before:absolute before:inset-0 before:content-[''] before:rounded-xl focus-visible:outline-none focus-visible:before:ring-2 focus-visible:before:ring-ring"

  const titleLink = external ? (
    <a
      href={href}
      target="_blank"
      rel="noreferrer"
      className={linkClass}
    >
      {title}
    </a>
  ) : (
    <Link href={href} className={linkClass}>
      {title}
    </Link>
  )

  return (
    <article className="group relative rounded-xl border border-border bg-card p-6 transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-glow-sm">
      <h2 className="text-2xl font-bold tracking-tight text-foreground transition-colors group-hover:text-primary">
        {titleLink}
      </h2>
      {excerpt ? (
        <div
          className="prose mt-3"
          dangerouslySetInnerHTML={{ __html: excerpt }}
        />
      ) : null}
      <div className="mt-5 inline-flex items-center gap-1.5 font-mono text-xs font-medium uppercase tracking-widest text-primary">
        <span>{cta}</span>
        {external ? (
          <ArrowTopRightOnSquareIcon className="h-3.5 w-3.5 transition-transform group-hover:-translate-y-px group-hover:translate-x-px" />
        ) : (
          <span className="transition-transform group-hover:translate-x-1" aria-hidden="true">→</span>
        )}
      </div>
    </article>
  )
}
