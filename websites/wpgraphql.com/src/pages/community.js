import SiteLayout from "../components/Site/SiteLayout";
import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import { FaDiscord, FaGithub, FaTwitter, FaYoutube } from "react-icons/fa";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";

const communities = [
  {
    name: "Github",
    description: "Github is where development of the WPGraphQL plugin happens. If you've found a bug or have a feature request, open an issue for discussion. If you want to contribute code, feel free to open a pull request.",
    link: "https://github.com/wp-graphql/wp-graphql",
    icon: FaGithub,
  },
  {
    name: "Discord",
    description: "The WPGraphQL Discord is a great place to communicate in real-time. Ask questions, discuss features, get to know other folks using WPGraphQL.",
    link: "/discord",
    icon: FaDiscord,
  },
  {
    name: "Twitter",
    description: "Follow WPGraphQL on Twitter to keep up with the latest news about the plugin and the WordPress and GraphQL ecosystems. Only occasional trolling.",
    link: "https://twitter.com/wpgraphql",
    icon: FaTwitter,
  },
  {
    name: "Youtube",
    description: "Follow WPGraphQL on Youtube to see helpful videos, demos, and case-studies on how WPGraphQL can be used.",
    link: "http://www.youtube.com/channel/UCwav5UKLaEufn0mtvaFAkYw",
    icon: FaYoutube,
  },
]

function Community({ layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <SiteLayout>
        <header className="mx-auto max-w-4xl px-6 py-16 text-center sm:py-20">
          <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
            Community
          </p>
          <h1 className="mt-3 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
            Join the conversation
          </h1>
          <p className="mx-auto mt-5 max-w-2xl text-base text-muted-foreground sm:text-lg">
            Communities, tools, and resources around WPGraphQL — places to ask
            questions, share work, and stay current with the ecosystem.
          </p>
        </header>
        <main className="mx-auto max-w-5xl px-6 pb-24">
          <div className="grid gap-5 md:grid-cols-2">
            {communities.map((community) => (
              <Card key={community.name}>
                <CardHeader>
                  {community?.icon && (
                    <div className="flex h-12 w-12 items-center justify-center rounded-md border border-border bg-muted text-primary">
                      <community.icon className="h-6 w-6" aria-hidden="true" />
                    </div>
                  )}
                  <CardTitle className="mt-4 text-display-sm font-bold tracking-tight">
                    {community.name}
                  </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                  <p className="text-muted-foreground">{community.description}</p>
                  <Button asChild variant="secondary" className="self-start">
                    <a href={community.link} target="_blank" rel="noreferrer">
                      Visit {community.name}
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

export default Community

export async function getStaticProps() {
  const layoutData = await getLayoutData()
  return {
    props: { layoutData },
    revalidate: 30,
  }
}
