import { useEffect, useMemo, useRef } from "react"
import Link from "next/link"
import { useRouter } from "next/router"
import { cn } from "@/lib/utils"

function normalizePath(path) {
  return (path || "").split(/[?#]/)[0].replace(/\/$/, "")
}

/** Walk up the DOM until we find an ancestor with vertical overflow. */
function findScrollParent(el) {
  let node = el?.parentElement
  while (node) {
    const overflowY = window.getComputedStyle(node).overflowY
    if (overflowY === "auto" || overflowY === "scroll") return node
    node = node.parentElement
  }
  return null
}

export default function DocsNav({ docsNavData }) {
  const { asPath } = useRouter()
  const currentPath = normalizePath(asPath)

  // Which section (Getting Started / Beginner Guides / ...) contains the
  // current page. We scroll this section to the top of the nav so the
  // section header is always visible above the active link.
  const activeSectionKey = useMemo(() => {
    if (!docsNavData) return null
    for (const [key, children] of Object.entries(docsNavData)) {
      if (children?.some((c) => normalizePath(c.href) === currentPath)) {
        return key
      }
    }
    return null
  }, [docsNavData, currentPath])

  const sectionRefs = useRef({})

  // On mount and on path/section change, scroll the nav's overflow
  // container so the active section's heading sits at the top. We update
  // scrollTop directly (rather than scrollIntoView) so only the nav
  // container scrolls — the document stays put.
  useEffect(() => {
    if (!activeSectionKey) return
    const sectionEl = sectionRefs.current[activeSectionKey]
    if (!sectionEl) return
    const scroller = findScrollParent(sectionEl)
    if (!scroller) return

    const sectionTop = sectionEl.getBoundingClientRect().top
    const scrollerTop = scroller.getBoundingClientRect().top
    scroller.scrollTop += sectionTop - scrollerTop
  }, [activeSectionKey, currentPath])

  if (!docsNavData) {
    return null
  }

  return (
    <nav>
      {Object.keys(docsNavData).reduce((acc, key) => {
        const children = docsNavData[key]
        if (!children?.length) return acc

        acc.push(
          <div
            key={key}
            ref={(el) => {
              if (el) sectionRefs.current[key] = el
            }}
            className="mb-8"
          >
            <h3 className="mb-3 font-mono text-xs font-medium uppercase tracking-widest text-muted-foreground">
              {key}
            </h3>
            <ul className="border-l border-border space-y-1">
              {children.map((child) => {
                const childPath = normalizePath(child.href)
                const isActive = childPath && childPath === currentPath
                return (
                  <li key={child.href}>
                    <Link href={child.href} legacyBehavior>
                      <a
                        aria-current={isActive ? "page" : undefined}
                        className={cn(
                          "-ml-px block border-l py-1 pl-4 text-sm transition-colors",
                          isActive
                            ? "border-primary text-primary"
                            : "border-transparent text-muted-foreground hover:border-primary/50 hover:text-foreground"
                        )}
                      >
                        {child.title}
                      </a>
                    </Link>
                  </li>
                )
              })}
            </ul>
          </div>
        )

        return acc
      }, [])}
    </nav>
  )
}
