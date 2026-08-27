import { Octokit } from "@octokit/core"
import { serialize } from "next-mdx-remote/serialize"
import GithubSlugger from "github-slugger"
import { unified } from "unified"
import { visit } from "unist-util-visit"
import fs from "node:fs/promises"
import path from "node:path"

import fetch from "cross-fetch"

import { CORE_PRODUCT, type DocsProduct } from "./docs-products"

// remark/rehype markdown plugins
import remarkGfm from "remark-gfm"
import remarkRehype from "remark-rehype"
import remarkParse from "remark-parse"
import remarkFm from "remark-frontmatter"
import withSmartQuotes from "remark-smartypants"
import remarkStringify from "rehype-stringify"
import rehypeSlug from "rehype-slug"
import rehypePrism from "rehype-prism-plus"
import rehypeExternalLinks from "rehype-external-links"

// Only pass `auth` when the token is a non-empty string. Passing a missing
// or revoked token causes GitHub to 401 ("Bad credentials") on every request;
// without auth we still get 60 unauth req/hr, which is plenty for the docs
// sitemap and dev-time doc fetches.
const githubToken = process.env.GITHUB_TOKEN?.trim()
const octokit = new Octokit(githubToken ? { auth: githubToken } : {})
// Unauthenticated fallback client, used when an authenticated request is
// rejected with "Bad credentials" (e.g. a revoked token).
const publicOctokit = new Octokit()

const DOCS_REPO = "wp-graphql"
const DOCS_OWNER = "wp-graphql"
// Using main branch now that docs are in monorepo
const DOCS_BRANCH = "main"
const IMG_PATH_REG = /^(\.\/)?(?<slug>.+)$/i

// The docs pipeline serves every product in the portal registry; all
// per-product paths derive from the product's monorepo docs folder. Every
// exported function takes an optional product and defaults to core, so
// existing callers (recipes, actions/filters/functions, sitemaps) are
// unchanged.
function docsFolderFor(product: DocsProduct): string {
  return product.docsFolder
}

function docsExtRegFor(product: DocsProduct): RegExp {
  return new RegExp(`${docsFolderFor(product)}/(?<slug>.*)\\.md(x?)$`, "i")
}

function docsPathFor(product: DocsProduct): string {
  return `https://raw.githubusercontent.com/${DOCS_OWNER}/${DOCS_REPO}/${DOCS_BRANCH}/${docsFolderFor(product)}`
}

function docsNavConfigUrlFor(product: DocsProduct): string {
  return `${docsPathFor(product)}/docs_nav.json`
}

function localDocsDirFor(product: DocsProduct): string {
  return path.resolve(process.cwd(), "..", "..", docsFolderFor(product))
}

// Doc subtrees that have dedicated top-level routes (the Developer
// Reference). Their markdown lives under plugins/wp-graphql/docs/<root>/ but
// their canonical URLs are /<root>/... — the /docs/<root>/... variants
// redirect there so the docs catch-all never renders them with the wrong nav.
const DEVELOPER_REFERENCE_ROOTS = ["actions", "filters", "functions", "recipes"]

export function toCanonicalDocUri(uri: string): string {
  const match = uri.match(/^\/docs\/([^/]+)(\/.*)?$/)
  if (!match || !DEVELOPER_REFERENCE_ROOTS.includes(match[1])) {
    return uri
  }

  const rest = !match[2] || match[2] === "/index" ? "" : match[2]
  return `/${match[1]}${rest}`
}

export function isDeveloperReferenceDocUri(uri: string): boolean {
  return toCanonicalDocUri(uri) !== uri
}

function sanitizeMarkdownForMdx(mdContent: string) {
  return mdContent.replace(/^\uFEFF?[\s\r\n]*(?:<!--[\s\S]*?-->\s*)+/u, "")
}

function hasTopLevelHeading(mdContent: string) {
  return /^\s*#\s+\S+/m.test(mdContent)
}

function normalizeSlug(rawSlug: unknown): string {
  if (typeof rawSlug !== "string") {
    throw { notFound: true }
  }

  // Strip a leading "/docs/" prefix if present (as used elsewhere in this file)
  let slug = rawSlug.replace(/^\/?docs\//i, "").replace(/^\/+/, "")

  // Decode any percent-encoded characters once, to prevent encoded path traversal
  try {
    slug = decodeURIComponent(slug)
  } catch {
    // Malformed encoding results in a notFound to avoid leaking errors
    throw { notFound: true }
  }

  // Basic validation: no path traversal, no protocol, no query/fragment
  if (
    slug.length === 0 ||
    slug.includes("..") ||
    slug.startsWith("/") ||
    slug.includes("\\") ||
    slug.includes("://") ||
    slug.includes("?") ||
    slug.includes("#")
  ) {
    throw { notFound: true }
  }

  // Allow only expected characters in slugs: lowercase letters, numbers,
  // forward slashes, underscores, and dashes.
  if (!/^[a-z0-9\/_-]+$/.test(slug)) {
    throw { notFound: true }
  }

  return slug
}

function docUrlFromSlug(slug: string, product: DocsProduct) {
  return `${docsPathFor(product)}/${slug}.md`
}

function localDocPathFromSlug(slug: string, product: DocsProduct) {
  const localDocsDir = localDocsDirFor(product)
  const localPath = path.resolve(localDocsDir, `${slug}.md`)

  if (!localPath.startsWith(localDocsDir)) {
    throw { notFound: true }
  }

  return localPath
}

function localDocsNavPath(product: DocsProduct) {
  return path.resolve(localDocsDirFor(product), "docs_nav.json")
}

function imgUrlFromPath(path, product: DocsProduct) {
  return `${docsPathFor(product)}/${path}`
}

export function getRemoteImgUrl(
  localPath,
  product: DocsProduct = CORE_PRODUCT
) {
  return imgUrlFromPath(localPath.match(IMG_PATH_REG).groups.slug, product)
}

export async function getAllDocMeta(product: DocsProduct = CORE_PRODUCT) {
  const requestOptions = {
    owner: DOCS_OWNER,
    repo: DOCS_REPO,
    path: docsFolderFor(product),
    ref: DOCS_BRANCH, // This makes it so only released features show up in the docs.
  }

  try {
    const { status, data } = await octokit.request(
      "GET /repos/{owner}/{repo}/contents/{path}",
      requestOptions
    )

    if (status !== 200) {
      throw new Error(String(status))
    }

    return data
  } catch (error) {
    // If token auth fails (bad credentials), fallback to unauthenticated GitHub API.
    if (
      error?.status === 401 ||
      /Bad credentials/i.test(error?.message || "")
    ) {
      const { status, data } = await publicOctokit.request(
        "GET /repos/{owner}/{repo}/contents/{path}",
        requestOptions
      )
      if (status !== 200) {
        throw new Error(String(status))
      }
      return data
    }

    throw error
  }
}

/**
 * Map a doc file slug (path relative to the docs folder, extension stripped)
 * onto the URI slug it serves: an index file serves its parent directory's
 * URI ("field-types/index" → "field-types"), matching getDocContent's
 * `<slug>/index.md` fallback. A root-level index maps to the product's base
 * path (empty slug).
 */
function uriSlugFromDocFileSlug(fileSlug: string): string {
  if (fileSlug === "index") {
    return ""
  }
  return fileSlug.replace(/\/index$/, "")
}

async function getLocalDocUris(
  product: DocsProduct = CORE_PRODUCT
): Promise<string[]> {
  const uris = []
  const localDocsDir = localDocsDirFor(product)

  const walk = async (currentDir: string, relativePrefix = "") => {
    const entries = await fs.readdir(currentDir, { withFileTypes: true })

    for (const entry of entries) {
      if (entry.name.startsWith(".")) {
        continue
      }

      const nextRelative = relativePrefix
        ? `${relativePrefix}/${entry.name}`
        : entry.name
      const absolutePath = path.join(currentDir, entry.name)

      if (entry.isDirectory()) {
        await walk(absolutePath, nextRelative)
        continue
      }

      if (!entry.isFile() || !entry.name.endsWith(".md")) {
        continue
      }

      const slug = uriSlugFromDocFileSlug(nextRelative.replace(/\.md$/, ""))
      uris.push(slug ? `${product.basePath}/${slug}` : product.basePath)
    }
  }

  if (
    await fs
      .stat(localDocsDir)
      .then(() => true)
      .catch(() => false)
  ) {
    await walk(localDocsDir)
  }

  return uris
}

/**
 * List the doc slugs in a developer-reference subdirectory (e.g. "actions"),
 * excluding the generated index. Used to build prev/next navigation. Returns
 * an empty array when the local docs aren't available.
 */
export async function listDocSlugs(
  subdir: string,
  product: DocsProduct = CORE_PRODUCT
): Promise<string[]> {
  const dir = path.join(localDocsDirFor(product), subdir)
  try {
    const entries = await fs.readdir(dir, { withFileTypes: true })
    return entries
      .filter(
        (entry) =>
          entry.isFile() &&
          entry.name.endsWith(".md") &&
          entry.name !== "index.md"
      )
      .map((entry) => entry.name.replace(/\.md$/, ""))
  } catch (_error) {
    return []
  }
}

export async function getDocsNav(product: DocsProduct = CORE_PRODUCT) {
  try {
    const nav = await fs.readFile(localDocsNavPath(product), "utf8")
    return JSON.parse(nav)
  } catch (_error) {
    // Fallback to remote docs nav.
  }

  const resp = await fetch(docsNavConfigUrlFor(product))

  if (!resp.ok) {
    throw Error(resp.statusText)
  }

  return resp.json()
}

/**
 * Flatten the grouped docs nav (`{ [section]: [{ title, href }] }`) into a
 * single ordered list of `{ href, label }` items, preserving the section and
 * item order. This is the front-to-back reading sequence used to build the
 * prev/next footer on docs pages.
 */
export function flattenDocsNav(
  nav: Record<string, Array<{ title?: string; href?: string }>>
): Array<{ href: string; label: string }> {
  if (!nav || typeof nav !== "object") {
    return []
  }

  const items: Array<{ href: string; label: string }> = []
  for (const group of Object.values(nav)) {
    if (!Array.isArray(group)) {
      continue
    }
    for (const item of group) {
      if (item && typeof item.href === "string") {
        items.push({ href: item.href, label: item.title ?? item.href })
      }
    }
  }
  return items
}

export async function getAllDocUri(
  product: DocsProduct = CORE_PRODUCT
): Promise<string[]> {
  try {
    const localUris = await getLocalDocUris(product)
    if (localUris.length > 0) {
      // Dedupe: a directory that has both `<name>.md` and `<name>/index.md`
      // maps to a single URI.
      return [...new Set(localUris)].sort((a, b) => a.localeCompare(b))
    }
  } catch (_error) {
    // Fallback to GitHub API listing.
  }

  const data = await getAllDocMeta(product)

  if (!Array.isArray(data)) {
    console.error(data)
    throw new Error("GitHub response should be an array")
  }

  const docsExtReg = docsExtRegFor(product)
  return data.reduce((acc, file) => {
    if (docsExtReg.test(file.path)) {
      // Extract slug from a path like "plugins/wp-graphql/docs/introduction.md";
      // the regex captures everything after the product docs folder.
      const match = file.path.match(docsExtReg)
      if (match && match.groups?.slug) {
        const slug = uriSlugFromDocFileSlug(match.groups.slug)
        acc.push(slug ? `${product.basePath}/${slug}` : product.basePath)
      }
    }

    return acc
  }, [])
}

export async function getDocContent(slug, product: DocsProduct = CORE_PRODUCT) {
  // Normalize and validate the incoming slug before constructing the URL
  const safeSlug = normalizeSlug(slug)

  // A directory's landing page is stored as its index file (e.g. the ACF
  // field-types overview lives at field-types/index.md but is served at
  // /docs/acf/field-types), so each source tries `<slug>.md` first and
  // `<slug>/index.md` second.
  const candidates = [safeSlug, `${safeSlug}/index`]

  for (const candidate of candidates) {
    try {
      return await fs.readFile(localDocPathFromSlug(candidate, product), "utf8")
    } catch (_error) {
      // Fall through to the next candidate, then the remote source.
    }
  }

  for (const candidate of candidates) {
    const resp = await fetch(docUrlFromSlug(candidate, product))

    if (resp.ok) {
      return resp.text()
    }

    // 4xx means this candidate doesn't exist — try the next; anything else
    // (5xx, network-level) is a real failure worth surfacing.
    if (resp.status < 400 || resp.status >= 500) {
      throw new Error(resp.statusText)
    }
  }

  throw { notFound: true }
}

export async function getParsedDoc(url, product: DocsProduct = CORE_PRODUCT) {
  const content = await getDocContent(url, product)
  const normalizedContent = sanitizeMarkdownForMdx(content)

  // An empty markdown file (e.g. a placeholder committed before the content
  // was ported) would render a blank page; treat it as missing content.
  if (normalizedContent.trim().length === 0) {
    throw { notFound: true }
  }

  const hasMarkdownH1 = hasTopLevelHeading(normalizedContent)

  const [source, toc] = await Promise.all([
    getSourceFromMd(normalizedContent, product),
    getTOCFromMd(normalizedContent),
  ])

  return { source, toc, hasMarkdownH1 }
}

/**
 * Rewrite relative `<img src>` paths to their raw GitHub URL. Relative image
 * paths are stored alongside the markdown; absolute URLs (e.g. recipe
 * screenshots hosted on content.wpgraphql.com) and data URIs are already
 * resolvable, so rewriting them would prepend the docs path and break them.
 *
 * Replaces the unmaintained `@jsdevtools/rehype-url-inspector` (last released
 * 2021) with an equivalent local rehype plugin built on `unist-util-visit`,
 * which is already a direct dependency. Behavior matches the previous
 * `selectors: ["img[src]"]` / `inspectEach` configuration exactly.
 */
function rehypeRewriteRelativeImageSrc(options: { product: DocsProduct }) {
  const { product } = options
  return (tree) => {
    visit(tree, "element", (node: any) => {
      if (node.tagName !== "img") {
        return
      }

      const src = node.properties?.src
      if (typeof src !== "string" || src.length === 0) {
        return
      }

      if (/^(?:https?:)?\/\//i.test(src) || src.startsWith("data:")) {
        return
      }

      node.properties.src = getRemoteImgUrl(src, product)
    })
  }
}

async function getSourceFromMd(mdContent, product: DocsProduct = CORE_PRODUCT) {
  return serialize(mdContent, {
    parseFrontmatter: true,
    mdxOptions: {
      remarkPlugins: [[remarkGfm, { singleTilde: false }], withSmartQuotes],
      rehypePlugins: [
        [rehypeRewriteRelativeImageSrc, { product }],
        [
          rehypeExternalLinks,
          { target: "_blank", rel: ["noopener", "noreferrer"] },
        ],
        rehypeSlug,
        [rehypePrism, { ignoreMissing: true }],
      ],
    },
  })
}

async function getTOCFromMd(mdContent) {
  const toc = []
  let parentId = null
  // Use github-slugger (the same library rehype-slug uses to assign heading
  // ids in the rendered content) so the TOC anchor links match the real
  // heading ids exactly — including its per-document duplicate handling.
  const slugs = new GithubSlugger()

  const getNodeText = (node) => {
    if (!node) {
      return ""
    }

    if (typeof node.value === "string") {
      return node.value
    }

    if (!Array.isArray(node.children)) {
      return ""
    }

    return node.children.map((child) => getNodeText(child)).join("")
  }

  await unified()
    .use(remarkParse)
    .use(remarkFm)
    .use(remarkGfm)
    .use(remarkRehype)
    .use(() => {
      return (tree) => {
        visit(tree, "element", (node: any) => {
          if (node.tagName === "h2" || node.tagName === "h3") {
            const title = getNodeText(node).trim()
            if (!title) {
              return
            }

            const id = slugs.slug(title)
            if (node.tagName === "h2") {
              parentId = id
            }

            toc.push({
              tagName: node.tagName,
              id,
              title,
              parentId: node.tagName === "h2" ? null : parentId,
            })
          }
        })
      }
    })
    .use(remarkStringify)
    .process(mdContent)

  return toc
}
