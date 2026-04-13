import { GetServerSideProps } from "next"
import { getServerSideSitemap } from "next-sitemap"
import { getAllDocUri } from "lib/parse-mdx-docs"

const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL

// Sitemap component, should be empty
export default function Sitemap() {}

export const getServerSideProps: GetServerSideProps = async (ctx) => {
  // collect all the docs
  const docUris = await getAllDocUri()

  // create
  const allDocsSitemap = docUris.map((docUri) => {
    return {
      loc: `${SITE_URL}${docUri}`,
    }
  })

  //  fetch all the post and pass into getServerSideSitemap. but make sure your allPasts in array.

  return await getServerSideSitemap(ctx, allDocsSitemap)
}
