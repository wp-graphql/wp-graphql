import gql from "graphql-tag"
import Head from "next/head"

/**
 * SEO metadata for a content node, colocated with the fields it needs.
 *
 * The `seo` field comes from the WPGraphQL Yoast SEO Addon, which registers it
 * on the `ContentNode` interface, so this one fragment covers posts, pages, and
 * every custom post type.
 *
 * Deliberately does NOT use Yoast's `canonical`, `opengraphUrl`, or
 * `metaRobots*` values. Those describe the WordPress host, not this site:
 *
 *   - Yoast stores computed permalinks in its indexables table and only
 *     recomputes them when the permalink structure changes, so its URLs keep
 *     pointing at the CMS hostname even though home_url() is filtered.
 *   - `metaRobotsNoindex` reflects the CMS's own `blog_public` setting, which
 *     is about whether the *backend* should be indexed.
 *
 * Canonical and robots are therefore derived here, from the front end's own
 * URL, which is the only thing that actually knows where this page lives.
 */
export const SeoFragment = gql`
  fragment Seo on ContentNode {
    uri
    seo {
      title
      metaDesc
      opengraphTitle
      opengraphDescription
      opengraphSiteName
      opengraphPublishedTime
      opengraphModifiedTime
      opengraphImage {
        altText
        sourceUrl
      }
      twitterTitle
      twitterDescription
      twitterImage {
        sourceUrl
      }
      schema {
        raw
      }
    }
  }
`

/**
 * The same shape for a taxonomy term.
 *
 * The Yoast addon registers `seo` per-taxonomy rather than on the `TermNode`
 * interface, so this is `on Category` rather than something shared. Its type is
 * `TaxonomySEO`, which carries the same field names as the post-type version.
 */
export const SeoCategoryFragment = gql`
  fragment SeoCategory on Category {
    uri
    seo {
      title
      metaDesc
      opengraphTitle
      opengraphDescription
      opengraphSiteName
      opengraphImage {
        altText
        sourceUrl
      }
      twitterTitle
      twitterDescription
      twitterImage {
        sourceUrl
      }
      schema {
        raw
      }
    }
  }
`

/**
 * And for an author archive.
 *
 * `SEOUser` is a narrower type than the other two: it has no
 * `opengraphSiteName`, `opengraphPublishedTime`, or `opengraphModifiedTime`,
 * so those are absent here. The component treats all three as optional.
 */
export const SeoUserFragment = gql`
  fragment SeoUser on User {
    uri
    seo {
      title
      metaDesc
      opengraphTitle
      opengraphDescription
      opengraphImage {
        altText
        sourceUrl
      }
      twitterTitle
      twitterDescription
      twitterImage {
        sourceUrl
      }
      schema {
        raw
      }
    }
  }
`

const SITE_URL = (process.env.NEXT_PUBLIC_SITE_URL || "").replace(/\/+$/, "")

/**
 * The WordPress origin baked into Yoast's JSON-LD, so we can swap it for the
 * public origin on the way out.
 *
 * Derived from the payload itself where possible: Yoast builds the page's `@id`
 * as `<wp-origin><uri>`, so finding the node's own uri inside the graph pins the
 * origin exactly. That keeps this correct even if NEXT_PUBLIC_WORDPRESS_URL is
 * unset or points at a caching proxy rather than the origin.
 *
 * Falls back to NEXT_PUBLIC_WORDPRESS_URL when the uri isn't present, which
 * happens for nodes Yoast renders without a self-referencing @id.
 */
function wpOrigin(raw, uri) {
  if (raw && uri) {
    const escaped = uri.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")
    const match = raw.match(
      new RegExp(`https?://[a-z0-9.-]+(?=${escaped})`, "i")
    )
    if (match) return match[0]
  }
  try {
    return new URL(process.env.NEXT_PUBLIC_WORDPRESS_URL).origin
  } catch {
    return null
  }
}

/** Absolute front-end URL for a WordPress uri. */
export function absoluteUrl(uri) {
  if (!uri) return SITE_URL || null
  if (/^https?:\/\//i.test(uri)) return uri
  return `${SITE_URL}${uri.startsWith("/") ? uri : `/${uri}`}`
}

/** Replace CMS-origin URLs with front-end URLs. */
function rehost(value, uri) {
  const origin = wpOrigin(value, uri)
  if (!value || !origin || !SITE_URL) return value
  // split/join rather than replace, so a `$&` in the replacement is literal.
  return value.split(origin).join(SITE_URL)
}

/**
 * @param {object} props
 * @param {object} [props.node]        A ContentNode matching the Seo fragment.
 * @param {string} [props.title]       Overrides the node's SEO title.
 * @param {string} [props.description] Overrides the node's description.
 * @param {string} [props.uri]         Overrides the node's uri, for routes with no node.
 * @param {boolean} [props.noindex]    Opt this route out of indexing.
 */
export default function Seo({
  node,
  title,
  description,
  uri,
  noindex = false,
}) {
  const seo = node?.seo ?? {}

  const resolvedTitle = title ?? seo.opengraphTitle ?? seo.title
  // Yoast leaves metaDesc empty unless an editor fills it in, but it always
  // generates an opengraph description from the excerpt, so prefer whichever
  // is present rather than shipping a page with no description at all.
  const resolvedDescription =
    description ?? seo.metaDesc ?? seo.opengraphDescription ?? null

  const canonical = absoluteUrl(uri ?? node?.uri)
  const ogImage = seo.opengraphImage?.sourceUrl
  const twitterImage = seo.twitterImage?.sourceUrl ?? ogImage

  return (
    <Head>
      {resolvedTitle && <title key="title">{resolvedTitle}</title>}
      {resolvedDescription && (
        <meta
          key="description"
          name="description"
          content={resolvedDescription}
        />
      )}
      {canonical && <link key="canonical" rel="canonical" href={canonical} />}
      <meta
        key="robots"
        name="robots"
        content={noindex ? "noindex, follow" : "index, follow"}
      />

      {resolvedTitle && (
        <meta key="og:title" property="og:title" content={resolvedTitle} />
      )}
      {resolvedDescription && (
        <meta
          key="og:description"
          property="og:description"
          content={resolvedDescription}
        />
      )}
      {canonical && <meta key="og:url" property="og:url" content={canonical} />}
      <meta
        key="og:type"
        property="og:type"
        content={node ? "article" : "website"}
      />
      {seo.opengraphSiteName && (
        <meta
          key="og:site_name"
          property="og:site_name"
          content={seo.opengraphSiteName}
        />
      )}
      {seo.opengraphPublishedTime && (
        <meta
          key="article:published_time"
          property="article:published_time"
          content={seo.opengraphPublishedTime}
        />
      )}
      {seo.opengraphModifiedTime && (
        <meta
          key="article:modified_time"
          property="article:modified_time"
          content={seo.opengraphModifiedTime}
        />
      )}
      {ogImage && <meta key="og:image" property="og:image" content={ogImage} />}
      {seo.opengraphImage?.altText && (
        <meta
          key="og:image:alt"
          property="og:image:alt"
          content={seo.opengraphImage.altText}
        />
      )}

      <meta
        key="twitter:card"
        name="twitter:card"
        content={twitterImage ? "summary_large_image" : "summary"}
      />
      {(seo.twitterTitle || resolvedTitle) && (
        <meta
          key="twitter:title"
          name="twitter:title"
          content={seo.twitterTitle || resolvedTitle}
        />
      )}
      {(seo.twitterDescription || resolvedDescription) && (
        <meta
          key="twitter:description"
          name="twitter:description"
          content={seo.twitterDescription || resolvedDescription}
        />
      )}
      {twitterImage && (
        <meta key="twitter:image" name="twitter:image" content={twitterImage} />
      )}

      {seo.schema?.raw && (
        <script
          key="ld-json"
          type="application/ld+json"
          dangerouslySetInnerHTML={{
            __html: rehost(seo.schema.raw, node?.uri),
          }}
        />
      )}
    </Head>
  )
}
