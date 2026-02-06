import { Octokit } from "@octokit/core"
import { serialize } from "next-mdx-remote/serialize"
import slugger from "slugger"
import { unified } from "unified"
import { visit } from "unist-util-visit"

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
// @TODO: Change to `master` for production
const DOCS_BRANCH = "develop"
const DOCS_FOLDER = "docs"
const DOCS_EXT_REG = new RegExp(`${DOCS_FOLDER}\/(?<slug>.*)\.md(x?)$`, "i")
const IMG_PATH_REG = /^(\.\/)?(?<slug>.+)$/i

const DOCS_PATH = `https://raw.githubusercontent.com/${DOCS_OWNER}/${DOCS_REPO}/${DOCS_BRANCH}/${DOCS_FOLDER}`

const DOCS_NAV_CONFIG_URL = `${DOCS_PATH}/docs_nav.json`

function docUrlFromSlug(slug) {
  return `${DOCS_PATH}/${slug}.md`
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
      acc.push(`/docs/${file.path.match(DOCS_EXT_REG).groups.slug}`)
    }

    return acc
  }, [])
}

export async function getDocContent(slug) {
  const resp = await fetch(docUrlFromSlug(slug))

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

  const [source, toc] = await Promise.all([
    getSourceFromMd(content),
    getTOCFromMd(content),
  ])

  return { source, toc }
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
        [rehypeExternalLinks, { target: "_blank" }],
        rehypeSlug,
        [rehypePrism, { ignoreMissing: true }],
      ],
    },
  })
}

async function getTOCFromMd(mdContent) {
  const toc = []
  let parentId = null

  await unified()
    .use(remarkParse)
    .use(remarkFm)
    .use(remarkRehype)
    .use(() => {
      return (tree) => {
        visit(tree, "element", (node: any) => {
          if (node.tagName === "h2" || node.tagName === "h3") {
            if (node.children[0].value) {
              let title = node.children[0]?.value
              let id = slugger(title)

              toc.push({
                tagName: node.tagName,
                id,
                title: title ?? "title",
                parentId: node.tagName === "h2" ? null : parentId,
              })
            }
          }
        })
      }
    })
    .use(remarkStringify)
    .process(mdContent)

  return toc
}
