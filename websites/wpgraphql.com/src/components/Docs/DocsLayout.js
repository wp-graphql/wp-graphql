import DocsNav from "./DocsNav"
import SiteLayout from "components/Site/SiteLayout"
import TableOfContents from "components/Docs/TableOfContents"
import DocsSidebar from "./DocsNavSideBar"

export default function DocsLayout({ children, toc, docsNavData }) {
  return (
    <SiteLayout>
      <main className="mx-auto w-full max-w-8xl px-4 sm:px-6 lg:px-8">
        <aside className="z-20 lg:hidden">
          <DocsSidebar>
            <DocsNav docsNavData={docsNavData} />
          </DocsSidebar>
        </aside>
        <div className="grid grid-cols-1 items-start gap-8 py-6 lg:grid-cols-[18rem_minmax(0,1fr)_16rem]">
          <aside
            id="docs-nav"
            className="sticky top-6 hidden h-[90vh] overflow-y-auto pr-2 lg:block"
          >
            <DocsNav docsNavData={docsNavData} />
          </aside>

          <article
            id="doc-content"
            className="min-w-0 self-start"
          >
            {children}
          </article>
          <aside
            id="doc-table-of-contents"
            className="sticky top-6 hidden lg:block"
          >
            <div className="w-full text-sm font-semibold leading-6 text-slate-900 dark:text-slate-100">
              {toc && <TableOfContents toc={toc} />}
            </div>
          </aside>
        </div>
      </main>
    </SiteLayout>
  )
}
