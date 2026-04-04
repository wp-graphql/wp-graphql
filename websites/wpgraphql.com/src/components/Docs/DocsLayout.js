import DocsNav from "./DocsNav"
import SiteLayout from "components/Site/SiteLayout"
import TableOfContents from "components/Docs/TableOfContents"
import DocsSidebar from "./DocsNavSideBar"

export default function DocsLayout({ children, toc, docsNavData }) {
  return (
    <SiteLayout>
      <main className="flex justify-center">
        <aside className="z-20 lg:hidden">
          <DocsSidebar>
            <DocsNav docsNavData={docsNavData} />
          </DocsSidebar>
        </aside>
        <div
          className="max-w-8xl grid gap-6 grid-rows-1 items-start p-6 overflow-y-scroll"
          style={{
            gridTemplateColumns:
              "max-content minmax(auto, max-content) max-content",
          }}
        >
          <aside
            id="docs-nav"
            className="sticky top-6 w-[40ch] col-start-1 col-end-2 hidden lg:block overflow-y-scroll h-[90vh] z-30"
          >
            <DocsNav docsNavData={docsNavData} />
          </aside>

          <article
            id="doc-content"
            className="col-start-2 col-end-3 max-w-[80ch] z-10 justify-self-center align self-center "
          >
            {children}
          </article>
          <aside
            id="doc-table-of-contents"
            className="sticky top-6 col-start-3 col-end-4 hidden md:block max-w-30ch z-20"
          >
            <div className="w-[30ch] text-slate-900 font-semibold mb-4 text-sm leading-6 dark:text-slate-100 ">
              {toc && <TableOfContents toc={toc} />}
            </div>
          </aside>
        </div>
      </main>
    </SiteLayout>
  )
}
