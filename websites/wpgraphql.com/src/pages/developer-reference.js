import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import SiteLayout from "components/Site/SiteLayout"
import { BoltIcon, BookOpenIcon, CodeBracketIcon } from "@heroicons/react/20/solid"
import { FaFilter } from "react-icons/fa"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"

const references = [
  {
    name: "Recipes",
    description: "Tasty treats that boost your productivity. WPGraphQL Recipes are snippets that showcase how to customize WPGraphQL using the actions, filters and functions available.",
    icon: BookOpenIcon,
    link: "/recipes",
  },
  {
    name: "Functions",
    description: "Need to add a Field or Type to the GraphQL Schema? There's a function for that. Learn more about the functions available to make your WPGraphQL server work for you.",
    icon: CodeBracketIcon,
    link: "/functions",
  },
  {
    name: "Actions",
    description: "Actions allow 3rd-party code to hook into WPGraphQL at certain parts of GraphQL execution. Learn about the actions available to hook into.",
    icon: BoltIcon,
    link: "/actions",
  },
  {
    name: "Filters",
    description: "Filters allow 3rd-party code to modify data at certain parts of GraphQL execution. Learn about the filters available to hook into.",
    icon: FaFilter,
    link: "/filters",
  },
]

export default function DeveloperReference({ layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <SiteLayout>
        <main className="mx-auto max-w-7xl px-6 pb-24">
          <header className="py-16 text-center sm:py-20">
            <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
              Developer Reference
            </p>
            <h1 className="mt-3 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
              Customize the schema. Shape the API.
            </h1>
            <p className="mx-auto mt-5 max-w-2xl text-base text-muted-foreground sm:text-lg">
              WPGraphQL was built with customization in mind. Find everything
              you need to interact with WPGraphQL — from recipes to actions and
              filters.
            </p>
          </header>
          <div className="grid gap-5 md:grid-cols-2">
            {references.map((ref) => (
              <Card key={ref.name}>
                <CardHeader>
                  {ref?.icon && (
                    <div className="flex h-12 w-12 items-center justify-center rounded-md border border-border bg-muted text-primary">
                      <ref.icon className="h-6 w-6" aria-hidden="true" />
                    </div>
                  )}
                  <CardTitle className="mt-4 text-display-sm font-bold tracking-tight">
                    {ref.name}
                  </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                  <p className="text-muted-foreground">{ref.description}</p>
                  <Button asChild className="self-start">
                    <a href={ref.link} target="_blank" rel="noreferrer">
                      Visit {ref.name}
                    </a>
                  </Button>
                </CardContent>
              </Card>
            ))}
          </div>
        </main>
      </SiteLayout>
    </LayoutProvider>
  )
}

export async function getStaticProps() {
  const layoutData = await getLayoutData()
  return {
    props: { layoutData },
    revalidate: 30,
  }
}
