import { MDXRemote } from "next-mdx-remote"

import DocsHub from "components/Docs/DocsHub"
import DocsLayout from "components/Docs/DocsLayout"
import PrevNext from "components/Docs/PrevNext"
import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import {
  flattenDocsNav,
  getAllDocUri,
  getDocsNav,
  getParsedDoc,
  isDeveloperReferenceDocUri,
  toCanonicalDocUri,
} from "lib/parse-mdx-docs"
import {
  CORE_PRODUCT_KEY,
  DOCS_PRODUCTS,
  enabledProducts,
  normalizeDocsNav,
  productFromSlugParts,
} from "lib/docs-products"
import { orderedSiblings } from "lib/sibling-nav"

import components from "components/Docs/MdxComponents"

function toSlugParts(slugParam) {
  if (Array.isArray(slugParam)) {
    return slugParam
  }

  if (typeof slugParam === "string") {
    return [slugParam]
  }

  return []
}

function toSlugParams(uri) {
  if (typeof uri !== "string") {
    return null
  }

  const normalized = uri.replace(/^\/+|\/+$/g, "")
  if (!normalized.startsWith("docs/")) {
    return null
  }

  const slug = normalized.replace(/^docs\//, "")
  if (!slug) {
    return { params: { slug: [] } }
  }

  return { params: { slug: slug.split("/") } }
}

export default function Doc({
  source,
  toc,
  docsNavData,
  layoutData,
  hasMarkdownH1,
  nav,
  productKey,
  isHub,
}) {
  const product = DOCS_PRODUCTS[productKey] ?? DOCS_PRODUCTS[CORE_PRODUCT_KEY]

  if (isHub) {
    return (
      <LayoutProvider value={layoutData}>
        <DocsLayout docsNavData={docsNavData} product={product}>
          <DocsHub />
        </DocsLayout>
      </LayoutProvider>
    )
  }

  return (
    <LayoutProvider value={layoutData}>
      <DocsLayout toc={toc} docsNavData={docsNavData} product={product}>
        <div id="content-wrapper" className="relative z-20 mt-8 prose">
          {source?.frontmatter?.title && !hasMarkdownH1 && (
            <header className="relative z-20 -mt-8">
              <h1>{source.frontmatter.title}</h1>
            </header>
          )}
          <MDXRemote {...source} components={components} />
          <PrevNext prev={nav?.prev} next={nav?.next} />
        </div>
      </DocsLayout>
    </LayoutProvider>
  )
}

export async function getStaticProps({ params }) {
  const slugParts = toSlugParts(params?.slug)

  // Resolve which product in the portal this request belongs to. Reserved
  // first segments (acf, smart-cache, ide) route to that product's docs;
  // everything else is a core doc slug. Disabled products 404 rather than
  // falling through, so a core doc can never shadow a product key.
  const resolved = productFromSlugParts(slugParts)
  if (!resolved) {
    return { notFound: true, revalidate: 30 }
  }

  const { product, restParts } = resolved
  const docSlug = restParts.join("/")
  const isCore = product.key === CORE_PRODUCT_KEY

  // Bare /docs renders the family hub: the ecosystem layer above the
  // product hubs, listing every enabled product's documentation.
  if (isCore && !docSlug) {
    try {
      const docsNavData = normalizeDocsNav(product, await getDocsNav(product))
      const layoutData = await getLayoutData()
      return {
        props: {
          isHub: true,
          docsNavData,
          layoutData,
          productKey: product.key,
        },
        revalidate: 30,
      }
    } catch (e) {
      console.error("docs hub failed to render", e)
      return { notFound: true, revalidate: 30 }
    }
  }

  // Developer Reference subtrees (actions/filters/functions/recipes) have
  // dedicated top-level routes for core; send /docs/<root>/... to the
  // canonical URL. Extensions keep those roots as regular doc subpages.
  if (isCore) {
    const requestedUri = `/docs/${docSlug}`
    if (isDeveloperReferenceDocUri(requestedUri)) {
      return {
        redirect: {
          destination: toCanonicalDocUri(requestedUri),
          permanent: true,
        },
      }
    }
  }

  // A product whose nav can't be fetched (e.g. its docs aren't on main yet)
  // is a 404 that self-heals via ISR once the content lands — not a 500.
  let docsNavData
  try {
    docsNavData = normalizeDocsNav(product, await getDocsNav(product))
  } catch (e) {
    console.error("docs nav unavailable", { product: product.key }, e)
    return { notFound: true, revalidate: 30 }
  }

  try {
    // A product's bare base path (e.g. /docs/acf) lands on its first nav
    // item until product hub pages exist.
    if (!docSlug) {
      const first = flattenDocsNav(docsNavData)[0]
      if (!first?.href) {
        return { notFound: true, revalidate: 30 }
      }
      return {
        redirect: { destination: first.href, permanent: false },
      }
    }

    const { source, toc, hasMarkdownH1 } = await getParsedDoc(docSlug, product)
    const layoutData = await getLayoutData()

    // Prev/next follows the sidebar nav's front-to-back reading order rather
    // than an alphabetical sort — the docs are meant to be read in sequence.
    const requestedUri = `${product.basePath}/${docSlug}`
    const nav = orderedSiblings(flattenDocsNav(docsNavData), requestedUri)

    return {
      props: {
        toc,
        source,
        docsNavData,
        hasMarkdownH1,
        layoutData,
        nav,
        productKey: product.key,
      },
      revalidate: 30,
    }
  } catch (e) {
    // Literal first argument so route-controlled params can't be
    // interpreted as console format directives.
    console.error(
      e.notFound ? "doc not found" : "doc failed to render",
      { params },
      e
    )
    // Every failure returns a revalidating 404 rather than throwing: a
    // throw during prerender fails the ENTIRE site build, which would let a
    // single MDX-hostile character in any product's markdown take the whole
    // site down (observed: raw { } or <text,text> in prose). A 404 with
    // revalidate isolates the bad page, keeps the error in the logs, and
    // self-heals once the content (or a transient fetch failure) is fixed.
    return { notFound: true, revalidate: 30 }
  }
}

export async function getStaticPaths() {
  // Pre-render paths sourced from the actual .md files in each enabled
  // product's docs folder, not from the WordPress Primary Nav menu. The menu
  // only references ~4 docs out of ~50, and any drift between menu URIs and
  // real files produced permanent static 404s for the menu-linked docs.
  const paths = [{ params: { slug: [] } }]
  for (const product of enabledProducts()) {
    try {
      const uris = await getAllDocUri(product)
      for (const uri of uris) {
        if (
          product.key === CORE_PRODUCT_KEY &&
          isDeveloperReferenceDocUri(uri)
        ) {
          continue
        }
        const params = toSlugParams(uri)
        if (params) {
          paths.push(params)
        }
      }
    } catch (e) {
      // A product whose docs can't be enumerated (e.g. content not yet on
      // main) prerenders nothing; fallback: "blocking" serves it once the
      // content lands.
      console.error(
        "getStaticPaths: failed to enumerate docs",
        { product: product.key },
        e
      )
    }
  }

  return {
    paths,
    fallback: "blocking",
  }
}
