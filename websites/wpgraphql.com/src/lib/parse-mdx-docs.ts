import { Octokit } from "@octokit/core"
import { serialize } from "next-mdx-remote/serialize"
import slugger from "slugger"
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
import rehypeUrlInspector from "@jsdevtools/rehype-url-inspector"

const octokit = new Octokit({
  auth: process.env.GITHUB_TOKEN,
})

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
  const { status, data } = await octokit.request(
    "GET /repos/{owner}/{repo}/contents/{path}",
    {
      owner: DOCS_OWNER,
      repo: DOCS_REPO,
      path: DOCS_FOLDER,
      ref: DOCS_BRANCH, // This makes it so only released features show up in the docs.
    }
  )

  if (status != 200) {
    throw new Error(status)
  }

  return data
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

export async function getAllDocUri(): Promise<string[]> {
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

async function getSourceFromMd(mdContent) {
  return serialize(mdContent, {
    parseFrontmatter: true,
    mdxOptions: {
      remarkPlugins: [[remarkGfm, { singleTilde: false }], withSmartQuotes],
      rehypePlugins: [
        [
          rehypeUrlInspector,
          {
            selectors: ["img[src]"],
            inspectEach: ({ url, node }) => {
              node.properties.src = getRemoteImgUrl(url)
            },
          },
        ],
        [rehypeExternalLinks, { target: "_blank", rel: ["noopener", "noreferrer"] }],
        rehypeSlug,
        [rehypePrism, { ignoreMissing: true }],
      ],
    },
  })
}

async function getTOCFromMd(mdContent) {
  const toc = []
  let parentId = null
  const slugCounts = {}

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

  const getUniqueHeadingId = (title: string) => {
    const baseSlug = slugger(title)
    const count = slugCounts[baseSlug] ?? 0
    slugCounts[baseSlug] = count + 1
    return count === 0 ? baseSlug : `${baseSlug}-${count}`
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

            const id = getUniqueHeadingId(title)
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
