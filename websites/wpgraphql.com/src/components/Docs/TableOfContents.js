import Link from "next/link"

export default function TableOfContents({ toc }) {
  if (!toc || !Array.isArray(toc)) {
    return null
  }

  return (
    <nav className="">
      <h2 className="text-slate-900 font-semibold mb-4 text-sm leading-6 dark:text-slate-100">
        On this page
      </h2>
      <ul className="text-slate-700 text-sm leading-6">
        {toc.map((item) => (
          <li key={item.id} className={item.tagName === "h3" ? "ml-3" : ""}>
            <Link href={`#${item.id}`}>
              <a className="block py-1 font-medium hover:text-sky-500 dark:text-slate-400 dark:hover:text-sky-300">
                {item.title}
              </a>
            </Link>
          </li>
        ))}
      </ul>
    </nav>
  )
}
