import Link from "next/link"
import { ArrowTopRightOnSquareIcon } from "@heroicons/react/24/outline"

/**
 * Card layout shared by Recipe / Filter / Function / Action / Extension
 * preview components in archive listings.
 *
 * Renders a bg-card panel with a hover-glow border, the item title (orange
 * on hover), an HTML excerpt rendered through .prose, and a footer "View X →"
 * link that translates the arrow on group-hover. The whole card is wrapped
 * in a Link so the click target is the entire panel.
 */
export default function PreviewCard({
  title,
  excerpt,
  href,
  cta = "View",
  external = false,
}) {
  const Wrapper = external ? "a" : Link
  const wrapperProps = external
    ? { href, target: "_blank", rel: "noreferrer" }
    : { href, legacyBehavior: false }

  return (
    <Wrapper {...wrapperProps} className="group block">
      <article className="rounded-xl border border-border bg-card p-6 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-primary/40 group-hover:shadow-glow-sm">
        <h2 className="text-2xl font-bold tracking-tight text-foreground transition-colors group-hover:text-primary">
          {title}
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
    </Wrapper>
  )
}
