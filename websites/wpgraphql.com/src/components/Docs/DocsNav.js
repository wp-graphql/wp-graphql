import { useEffect, useRef } from "react"
import Link from "next/link"
import { useRouter } from "next/router"
import { cn } from "@/lib/utils"

export default function DocsNav({ docsNavData }) {
  const { asPath } = useRouter()
  const currentPath = asPath.split(/[?#]/)[0].replace(/\/$/, "")
  const activeRef = useRef(null)

  // On mount and on path change, bring the active nav item into view inside
  // the nav's scroll container. `block: "nearest"` is a no-op when the
  // element is already visible, so this only scrolls when needed.
  useEffect(() => {
    if (!activeRef.current) return
    activeRef.current.scrollIntoView({ block: "nearest", inline: "nearest" })
  }, [currentPath])

  if (!docsNavData) {
    return null
  }

  return (
    <nav>
      {Object.keys(docsNavData).reduce((acc, key) => {
        const children = docsNavData[key]

        if (children.length > 0) {
          acc.push(
            <div key={key} className="mb-8">
              <h3 className="mb-3 font-mono text-xs font-medium uppercase tracking-widest text-muted-foreground">
                {key}
              </h3>
              <ul className="border-l border-border space-y-1">
                {children.map((child) => {
                  const childPath = (child.href || "").replace(/\/$/, "")
                  const isActive = childPath && childPath === currentPath
                  return (
                    <li key={child.href}>
                      <Link href={child.href} legacyBehavior>
                        <a
                          ref={isActive ? activeRef : undefined}
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
        }

        return acc
      }, [])}
    </nav>
  )
}
