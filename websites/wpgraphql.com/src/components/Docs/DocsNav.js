import Link from "next/link"

export default function DocsNav({ docsNavData }) {
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
                  return (
                    <li key={child.href}>
                      <Link href={child.href} legacyBehavior>
                        <a className="-ml-px block border-l border-transparent pl-4 py-1 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-foreground">
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
