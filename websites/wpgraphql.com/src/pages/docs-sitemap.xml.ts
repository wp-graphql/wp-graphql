import { GetServerSideProps } from "next"
import { getServerSideSitemapLegacy } from "next-sitemap"
import { getAllDocUri, toCanonicalDocUri } from "lib/parse-mdx-docs"
import { CORE_PRODUCT_KEY, enabledProducts } from "lib/docs-products"

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL

// Sitemap component, should be empty
export default function Sitemap() {}

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  // Fetching the doc list hits GitHub's contents API and can fail at
  // runtime (rate-limit, revoked token, network error). Don't let that
  // tank the response — log the error and emit an empty <urlset>, which
  // sitemap consumers handle gracefully.
  const docUris: string[] = []
  for (const product of enabledProducts()) {
    try {
      const uris = await getAllDocUri(product)
      // Developer Reference docs live at top-level canonical URLs
      // (/actions/..., /filters/..., etc.) — advertise those, not the
      // redirecting /docs/... variants. The rewrite only applies to core;
      // extensions keep those roots as regular doc subpages.
      docUris.push(
        ...(product.key === CORE_PRODUCT_KEY
          ? uris.map((uri) => toCanonicalDocUri(uri))
          : uris)
      )
    } catch (err) {
      console.error(
        "[docs-sitemap] failed to fetch doc URIs:",
        product.key,
        err
      )
    }
  }

  const canonicalUris = [...new Set(docUris)]

  const allDocsSitemap = canonicalUris.map((docUri) => ({
    loc: `${SITE_URL}${docUri}`,
  }))

  return await getServerSideSitemapLegacy(ctx, allDocsSitemap)
}
