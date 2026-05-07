import { getWordPressProps, WordPressTemplate } from "@faustwp/core"
import { getTemplateStaticProps, LayoutProvider } from "lib/next-wpgraphql"
import { shouldUseNextWpGraphQL } from "lib/next-wpgraphql-config"
import templates from "wp-templates"

export default function Page(props) {
  if (props && typeof props.template === "string" && props.data) {
    const Template = templates[props.template]
    if (!Template) return null
    return (
      <LayoutProvider value={props.layoutData}>
        <Template data={props.data} seed={props.seed} uri={props.uri} />
      </LayoutProvider>
    )
  }
  return <WordPressTemplate {...props} />
}

export async function getStaticProps(ctx) {
  if (shouldUseNextWpGraphQL(ctx)) {
    return getTemplateStaticProps(ctx, { revalidate: 5 })
  }
  return { ...(await getWordPressProps({ ctx })), revalidate: 5 }
}

export async function getStaticPaths() {
  return {
    paths: [],
    fallback: "blocking",
  }
}
