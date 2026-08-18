import { Octokit } from "@octokit/core"
import { serialize } from "next-mdx-remote/serialize"
import GithubSlugger from "github-slugger"
import { unified } from "unified"
import { visit } from "unist-util-visit"
import fs from "node:fs/promises"
import path from "node:path"

import fetch from "cross-fetch"

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
// Docs are now in plugins/wp-graphql/docs/ in the monorepo
const DOCS_FOLDER = "plugins/wp-graphql/docs"
const DOCS_EXT_REG = new RegExp(`${DOCS_FOLDER}/(?<slug>.*)\\.md(x?)$`, "i")
const IMG_PATH_REG = /^(\.\/)?(?<slug>.+)$/i

const DOCS_PATH = `https://raw.githubusercontent.com/${DOCS_OWNER}/${DOCS_REPO}/${DOCS_BRANCH}/${DOCS_FOLDER}`

const DOCS_NAV_CONFIG_URL = `${DOCS_PATH}/docs_nav.json`
const LOCAL_DOCS_DIR = path.resolve(process.cwd(), "..", "..", DOCS_FOLDER)

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

function docUrlFromSlug(slug: string) {
  return `${DOCS_PATH}/${slug}.md`
}

function localDocPathFromSlug(slug: string) {
  const localPath = path.resolve(LOCAL_DOCS_DIR, `${slug}.md`)

  if (!localPath.startsWith(LOCAL_DOCS_DIR)) {
    throw { notFound: true }
  }

  return localPath
}

function localDocsNavPath() {
  return path.resolve(LOCAL_DOCS_DIR, "docs_nav.json")
}

function imgUrlFromPath(path) {
  return `${DOCS_PATH}/${path}`
}

export function getRemoteImgUrl(localPath) {
  return imgUrlFromPath(localPath.match(IMG_PATH_REG).groups.slug)
}

export async function getAllDocMeta() {
  const requestOptions = {
    owner: DOCS_OWNER,
    repo: DOCS_REPO,
    path: DOCS_FOLDER,
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

async function getLocalDocUris(): Promise<string[]> {
  const uris = []

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

      const slug = nextRelative.replace(/\.md$/, "")
      uris.push(`/docs/${slug}`)
    }
  }

  if (
    await fs
      .stat(LOCAL_DOCS_DIR)
      .then(() => true)
      .catch(() => false)
  ) {
    await walk(LOCAL_DOCS_DIR)
  }

  return uris
}

/**
 * List the doc slugs in a developer-reference subdirectory (e.g. "actions"),
 * excluding the generated index. Used to build prev/next navigation. Returns
 * an empty array when the local docs aren't available.
 */
export async function listDocSlugs(subdir: string): Promise<string[]> {
  const dir = path.join(LOCAL_DOCS_DIR, subdir)
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

export async function getDocsNav() {
  try {
    const nav = await fs.readFile(localDocsNavPath(), "utf8")
    return JSON.parse(nav)
  } catch (_error) {
    // Fallback to remote docs nav.
  }

  const resp = await fetch(DOCS_NAV_CONFIG_URL)

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

export async function getAllDocUri(): Promise<string[]> {
  try {
    const localUris = await getLocalDocUris()
    if (localUris.length > 0) {
      return localUris.sort((a, b) => a.localeCompare(b))
    }
  } catch (_error) {
    // Fallback to GitHub API listing.
  }

  const data = await getAllDocMeta()

  if (!Array.isArray(data)) {
    console.error(data)
    throw new Error("GitHub response should be an array")
  }

  return data.reduce((acc, file) => {
    if (DOCS_EXT_REG.test(file.path)) {
      // Extract slug from path like "plugins/wp-graphql/docs/introduction.md"
      // The regex captures everything after the docs folder
      const match = file.path.match(DOCS_EXT_REG)
      if (match && match.groups?.slug) {
        acc.push(`/docs/${match.groups.slug}`)
      }
    }

    return acc
  }, [])
}

export async function getDocContent(slug) {
  // Normalize and validate the incoming slug before constructing the URL
  const safeSlug = normalizeSlug(slug)

  try {
    return await fs.readFile(localDocPathFromSlug(safeSlug), "utf8")
  } catch (_error) {
    // Fallback to remote source when local docs are unavailable.
  }

  const resp = await fetch(docUrlFromSlug(safeSlug))

  if (!resp.ok) {
    if (resp.status >= 400 && resp.status < 500) {
      throw { notFound: true }
    }

    throw new Error(resp.statusText)
  }

  return resp.text()
}

export async function getParsedDoc(url) {
  const content = await getDocContent(url)
  const normalizedContent = sanitizeMarkdownForMdx(content)
  const hasMarkdownH1 = hasTopLevelHeading(normalizedContent)

  const [source, toc] = await Promise.all([
    getSourceFromMd(normalizedContent),
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
function rehypeRewriteRelativeImageSrc() {
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

      node.properties.src = getRemoteImgUrl(src)
    })
  }
}

async function getSourceFromMd(mdContent) {
  return serialize(mdContent, {
    parseFrontmatter: true,
    mdxOptions: {
      remarkPlugins: [[remarkGfm, { singleTilde: false }], withSmartQuotes],
      rehypePlugins: [
        rehypeRewriteRelativeImageSrc,
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
