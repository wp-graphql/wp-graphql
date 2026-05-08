import Image from "next/image"

function Feature({ eyebrow, title, body, image, alt }) {
  return (
    <section className="py-20 sm:py-28">
      <div className="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:max-w-7xl lg:px-8">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          {eyebrow}
        </p>
        <h2 className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          {title}
        </h2>
        <p className="mx-auto mt-5 max-w-prose text-base text-muted-foreground sm:text-lg">
          {body}
        </p>
        <div className="mt-12 overflow-hidden rounded-xl border border-border bg-card shadow-elev-md">
          <Image
            src={image}
            alt={alt}
            width={1024}
            height={402}
            className="block h-auto w-full"
          />
        </div>
      </div>
    </section>
  )
}

export default function HomepageFeatures() {
  return (
    <>
      <Feature
        eyebrow="Efficient Data Fetching"
        title="Query what you need. Get exactly that."
        body="With GraphQL, the client makes declarative queries, asking for the exact data needed, and exactly what was asked for is given in response, nothing more. This allows the client to have control over their application, and lets the GraphQL server fetch only the resources requested."
        image="/images/graphiql-query-posts.png"
        alt="GraphiQL query posts"
      />
      <Feature
        eyebrow="Nested Resources"
        title="Fetch many resources in a single request"
        body="GraphQL queries allow access to multiple root resources, and smoothly follow references between connected resources. While a typical REST API would require round-trip requests to many endpoints, GraphQL APIs can get all the data your app needs in a single request — quick even on slow mobile network connections."
        image="/images/query-multiple-root-resources.png"
        alt="Query multiple root resources"
      />
    </>
  )
}
