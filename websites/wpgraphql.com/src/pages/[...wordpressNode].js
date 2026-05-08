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
  // 5 minutes. Acts as a safety net for any CMS changes Smart Cache
  // doesn't track; on-demand revalidation via /api/revalidate is the
  // primary path. Tight enough to keep stale-window bounded if the
  // webhook ever silently fails.
  return getTemplateStaticProps(ctx, { revalidate: 300 })
}

export async function getStaticPaths() {
  return {
    paths: [],
    fallback: "blocking",
  }
}
