import { getTemplateStaticProps, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import templates from "wp-templates"

export default function Page({ template, data, layoutData, seed, uri }) {
  const Template = templates[template]
  if (!Template) return null
  return (
    <LayoutProvider value={layoutData}>
      <Template data={data} seed={seed} uri={uri} />
    </LayoutProvider>
  )
}

export async function getStaticProps(ctx) {
  return getTemplateStaticProps(ctx, { revalidate: 5 })
}

export async function getStaticPaths() {
  return {
    paths: [],
    fallback: "blocking",
  }
}
