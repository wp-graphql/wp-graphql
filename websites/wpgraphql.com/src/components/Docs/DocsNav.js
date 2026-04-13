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
            <div key={key}>
              <h3 className="mb-8 lg:mb-3 font-semibold text-slate-900 dark:text-slate-200 font-lora">
                {key}
              </h3>
              <ul className="mb-6 space-y-6 lg:space-y-2 border-l border-slate-100 dark:border-slate-800">
                {children.map((child) => {
                  return (
                    <li key={child.href}>
                      <Link href={child.href}>
                        <a className="block border-l pl-4 -ml-px border-transparent hover:border-slate-400 dark:hover:border-slate-500 text-slate-700 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-300">
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
