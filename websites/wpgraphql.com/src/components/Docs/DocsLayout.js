import DocsNav from "./DocsNav"
import ProductSwitcher from "./ProductSwitcher"
import SiteLayout from "components/Site/SiteLayout"
import TableOfContents from "components/Docs/TableOfContents"
import DocsSidebar from "./DocsNavSideBar"

export default function DocsLayout({ children, toc, docsNavData, product }) {
  // Sibling-brand theme scope: each product's docs section re-tints the
  // accent tokens (emerald for ACF, rose for Smart Cache, violet for the
  // IDE); core renders the default orange with no scope class.
  const themeClass = product?.themeClass ?? ""

  return (
    <SiteLayout>
      <div className={themeClass}>
        {/* Mobile: floating button + slide-over panel */}
        <aside className="z-20 lg:hidden">
          <DocsSidebar>
            <ProductSwitcher product={product} />
            <DocsNav docsNavData={docsNavData} />
          </DocsSidebar>
        </aside>

        <div className="mx-auto w-full max-w-8xl px-6 lg:px-8">
          <div className="lg:grid lg:grid-cols-[14rem_minmax(0,1fr)_12rem] lg:gap-10">
            {/* Left rail: docs nav. sticky relative to the document scroll
                (not an internal overflow container) so it stays in view as the
                article scrolls. top-20 clears the sticky site header. */}
            <aside id="docs-nav" className="hidden lg:block">
              {/* top-16 matches the sticky site header's height so the rail's
                  pinned position equals its natural position at scroll zero —
                  keeping its heading top-aligned with the article content
                  (e.g. breadcrumbs). The py-10 inside the sticky element
                  provides the breathing room below the header when pinned. */}
              <div className="sticky top-16 max-h-[calc(100vh-4rem)] overflow-y-auto py-10 pr-4">
                <ProductSwitcher product={product} />
                <DocsNav docsNavData={docsNavData} />
              </div>
            </aside>

            {/* Article column: min-w-0 prevents long code blocks from forcing
                the grid track wider. Centered max-width inside. */}
            {/* py-10 matches the rails' sticky-wrapper padding so all three
                columns' content starts at the same height. */}
            <article id="doc-content" className="min-w-0 py-10">
              <div className="mx-auto max-w-3xl">{children}</div>
            </article>

            {/* Right rail: on-this-page TOC, sticky alongside the article. */}
            <aside id="doc-table-of-contents" className="hidden lg:block">
              <div className="sticky top-16 max-h-[calc(100vh-4rem)] overflow-y-auto py-10">
                {toc && <TableOfContents toc={toc} />}
              </div>
            </aside>
          </div>
        </div>
      </div>
    </SiteLayout>
  )
}
