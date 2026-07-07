import Link from "next/link"

import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"

import SiteLayout from "components/Site/SiteLayout"
import { Eyebrow } from "@/components/extensions/SectionHeading"
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
        <section className="relative overflow-hidden border-b border-border">
          <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 -z-10"
            style={{
              background:
                "radial-gradient(ellipse 900px 500px at 50% 0%, hsl(var(--primary) / 0.10) 0%, transparent 70%)",
            }}
          />
          <div className="mx-auto max-w-7xl px-6 py-20 lg:py-24">
            <div className="mx-auto max-w-3xl text-center">
              <Eyebrow icon={CodeBracketIcon}>Developer Reference</Eyebrow>
              <h1 className="mt-4 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
                Customize the schema.
                <br />
                <span className="text-primary">Shape the API.</span>
              </h1>
              <p className="mx-auto mt-5 max-w-2xl text-base text-muted-foreground sm:text-lg">
                WPGraphQL was built with customization in mind. Find everything
                you need to interact with WPGraphQL — from recipes to actions and
                filters.
              </p>
            </div>
          </div>
        </section>
        <main className="mx-auto max-w-7xl px-6 pb-24 pt-16">
          <div className="grid gap-5 md:grid-cols-2">
            {references.map((ref) => (
              <Card
                key={ref.name}
                className="group relative transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-glow-sm"
              >
                <CardHeader>
                  {ref?.icon && (
                    <div className="flex h-12 w-12 items-center justify-center rounded-md border border-border bg-muted text-primary">
                      <ref.icon className="h-6 w-6" aria-hidden="true" />
                    </div>
                  )}
                  <CardTitle className="mt-4 text-display-sm font-bold tracking-tight transition-colors group-hover:text-primary">
                    {/* Stretched link makes the whole card clickable — same
                        pattern as PreviewCard. */}
                    <Link
                      href={ref.link}
                      className="before:absolute before:inset-0 before:content-[''] before:rounded-xl focus-visible:outline-none focus-visible:before:ring-2 focus-visible:before:ring-ring"
                    >
                      {ref.name}
                    </Link>
                  </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                  <p className="text-muted-foreground">{ref.description}</p>
                  <Button asChild className="relative self-start">
                    <Link href={ref.link}>Visit {ref.name}</Link>
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
