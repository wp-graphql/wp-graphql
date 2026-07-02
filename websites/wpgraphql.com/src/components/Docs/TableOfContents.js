import { useEffect, useState } from "react"
import Link from "next/link"
import { cn } from "@/lib/utils"

/**
 * Renders the docs "On this page" TOC and highlights the heading that's
 * currently in view (a.k.a. scroll spy). We use IntersectionObserver and
 * track every visible heading; the active link is the topmost-visible one.
 *
 * The rootMargin reserves space for the sticky site header (top) and pushes
 * the bottom edge upward so we don't activate a heading that's only briefly
 * in the bottom of the viewport.
 */
export default function TableOfContents({ toc }) {
  const [activeId, setActiveId] = useState(null)

  useEffect(() => {
    if (!toc?.length || typeof window === "undefined") return

    const ids = toc.map((item) => item.id).filter(Boolean)
    const headings = ids
      .map((id) => document.getElementById(id))
      .filter(Boolean)
    if (!headings.length) return

    const visible = new Map()

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            visible.set(entry.target.id, entry.target)
          } else {
            visible.delete(entry.target.id)
          }
        })

        if (visible.size === 0) return

        // Pick the visible heading that's nearest the top of the viewport.
        let topmost = null
        let topmostY = Infinity
        for (const el of visible.values()) {
          const y = el.getBoundingClientRect().top
          if (y < topmostY) {
            topmostY = y
            topmost = el
          }
        }
        if (topmost) setActiveId(topmost.id)
      },
      {
        // Top: clear the ~64px sticky header so a heading is only "active"
        // after it scrolls below the header.
        // Bottom: -60% means a heading must enter the upper 40% of the
        // viewport before it activates — feels more natural than triggering
        // at the very bottom edge.
        rootMargin: "-80px 0px -60% 0px",
        threshold: [0, 1],
      }
    )

    headings.forEach((h) => observer.observe(h))
    return () => observer.disconnect()
  }, [toc])

  if (!toc || !Array.isArray(toc) || toc.length === 0) {
    return null
  }

  return (
    <nav aria-label="Table of contents">
      <h2 className="mb-3 font-mono text-xs font-medium uppercase tracking-widest text-muted-foreground">
        On this page
      </h2>
      <ul className="border-l border-border space-y-1 text-sm leading-6">
        {toc.map((item) => {
          const isActive = item.id === activeId
          return (
            <li key={item.id} className={item.tagName === "h3" ? "ml-3" : ""}>
              <Link href={`#${item.id}`} legacyBehavior>
                <a
                  className={cn(
                    "-ml-px block border-l py-1 pl-4 transition-colors",
                    isActive
                      ? "border-primary text-primary"
                      : "border-transparent text-muted-foreground hover:border-primary/50 hover:text-foreground"
                  )}
                >
                  {item.title}
                </a>
              </Link>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
