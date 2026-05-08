import DocsNav from "./DocsNav"
import SiteLayout from "components/Site/SiteLayout"
import TableOfContents from "components/Docs/TableOfContents"
import DocsSidebar from "./DocsNavSideBar"

export default function DocsLayout({ children, toc, docsNavData }) {
  return (
    <SiteLayout>
      {/* Mobile: floating button + slide-over panel */}
      <aside className="z-20 lg:hidden">
        <DocsSidebar>
          <DocsNav docsNavData={docsNavData} />
        </DocsSidebar>
      </aside>

      <div className="mx-auto w-full max-w-8xl px-6 lg:px-8">
        <div className="lg:grid lg:grid-cols-[14rem_minmax(0,1fr)_12rem] lg:gap-10">
          {/* Left rail: docs nav. sticky relative to the document scroll
              (not an internal overflow container) so it stays in view as the
              article scrolls. top-20 clears the sticky site header. */}
          <aside id="docs-nav" className="hidden lg:block">
            <div className="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto py-10 pr-4">
              <DocsNav docsNavData={docsNavData} />
            </div>
          </aside>

          {/* Article column: min-w-0 prevents long code blocks from forcing
              the grid track wider. Centered max-width inside. */}
          <article id="doc-content" className="min-w-0 py-10 lg:py-12">
            <div className="mx-auto max-w-3xl">{children}</div>
          </article>

          {/* Right rail: on-this-page TOC, sticky alongside the article. */}
          <aside id="doc-table-of-contents" className="hidden lg:block">
            <div className="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto py-10">
              {toc && <TableOfContents toc={toc} />}
            </div>
          </aside>
        </div>
      </div>
    </SiteLayout>
  )
}
