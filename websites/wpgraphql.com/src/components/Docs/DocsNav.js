import { useEffect, useMemo, useRef, useState } from "react"
import Link from "next/link"
import { useRouter } from "next/router"
import { ChevronRightIcon } from "@heroicons/react/20/solid"
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

/** Whether the current path is this item's page or inside its subtree. */
function containsPath(item, currentPath) {
  if (!item) return false
  const itemPath = normalizePath(item.href)
  if (itemPath && itemPath === currentPath) return true
  return Array.isArray(item.items)
    ? item.items.some((child) => containsPath(child, currentPath))
    : false
}

function NavLink({ item, currentPath, className }) {
  const itemPath = normalizePath(item.href)
  const isActive = itemPath && itemPath === currentPath
  return (
    <Link href={item.href} legacyBehavior>
      <a
        aria-current={isActive ? "page" : undefined}
        className={cn(
          "-ml-px block border-l py-1 pl-4 text-sm transition-colors",
          isActive
            ? "border-primary text-primary"
            : "border-transparent text-muted-foreground hover:border-primary/50 hover:text-foreground",
          className
        )}
      >
        {item.title}
      </a>
    </Link>
  )
}

/**
 * A nav entry with nested children (e.g. the ACF field types under "ACF
 * Field Types"): the entry itself stays a link, with a disclosure toggle
 * beside it. The subtree starts open when the reader is inside it and
 * re-opens if they navigate into it.
 */
function NavGroup({ item, currentPath }) {
  const isWithin = containsPath(item, currentPath)
  const [open, setOpen] = useState(isWithin)

  useEffect(() => {
    if (isWithin) {
      setOpen(true)
    }
  }, [isWithin, currentPath])

  return (
    <>
      <div className="flex items-center">
        <div className="min-w-0 flex-1">
          <NavLink item={item} currentPath={currentPath} />
        </div>
        <button
          type="button"
          aria-expanded={open}
          aria-label={`${open ? "Collapse" : "Expand"} ${item.title}`}
          onClick={() => setOpen((value) => !value)}
          className="flex h-6 w-6 flex-none items-center justify-center rounded text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <ChevronRightIcon
            aria-hidden="true"
            className={cn("h-4 w-4 transition-transform", open && "rotate-90")}
          />
        </button>
      </div>
      {open && (
        <ul className="ml-4 border-l border-border">
          {item.items.map((child) => (
            <li key={child.href}>
              <NavLink item={child} currentPath={currentPath} />
            </li>
          ))}
        </ul>
      )}
    </>
  )
}

export default function DocsNav({ docsNavData }) {
  const { asPath } = useRouter()
  const currentPath = normalizePath(asPath)

  // Which section (Getting Started / Beginner Guides / ...) contains the
  // current page (nested subtrees included). We scroll this section to the
  // top of the nav so the section header is always visible above the
  // active link.
  const activeSectionKey = useMemo(() => {
    if (!docsNavData) return null
    for (const [key, children] of Object.entries(docsNavData)) {
      if (children?.some((c) => containsPath(c, currentPath))) {
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
              {children.map((child) => (
                <li key={child.href ?? child.title}>
                  {Array.isArray(child.items) && child.items.length > 0 ? (
                    <NavGroup item={child} currentPath={currentPath} />
                  ) : (
                    <NavLink item={child} currentPath={currentPath} />
                  )}
                </li>
              ))}
            </ul>
          </div>
        )

        return acc
      }, [])}
    </nav>
  )
}
