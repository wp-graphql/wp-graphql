import Link from "next/link"

export default function TableOfContents({ toc }) {
  if (!toc || !Array.isArray(toc)) {
    return null
  }

  return (
    <nav>
      <h2 className="mb-3 font-mono text-xs font-medium uppercase tracking-widest text-muted-foreground">
        On this page
      </h2>
      <ul className="space-y-1 text-sm leading-6">
        {toc.map((item) => (
          <li key={item.id} className={item.tagName === "h3" ? "ml-3" : ""}>
            <Link href={`#${item.id}`} legacyBehavior>
              <a className="block py-1 text-muted-foreground transition-colors hover:text-primary">
                {item.title}
              </a>
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  )
}
