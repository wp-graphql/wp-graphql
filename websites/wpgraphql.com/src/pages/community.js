import {gql} from "@apollo/client";
import {NavMenuFragment} from "../components/Site/SiteHeader";
import SiteLayout from "../components/Site/SiteLayout";
import {addApolloState, getApolloClient} from "@faustwp/core/dist/mjs/client";
import {FaDiscord, FaGithub, FaTwitter, FaYoutube} from "react-icons/fa";

const GET_NAV_MENU = gql`
query GetNavMenu {
   ...NavMenu
}
${NavMenuFragment}
`

function Community() {

  const communities = [
    {
      name: "Github",
      description: "Github is where the development of the WPGraphQL plugin happens. If you’ve found a bug or have a feature request, open an issue for discussion. If you want to contribute code, feel free to open a pull request.",
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
      icon: FaTwitter
    },
    {
      name: "Youtube",
      description: "Follow WPGraphQL on Youtube to see helpful videos, demos, and case-studies on how WPGraphQL can be used.",
      link: "http://www.youtube.com/channel/UCwav5UKLaEufn0mtvaFAkYw",
      icon: FaYoutube
    }

  ];

  return (
    <SiteLayout>
      <header className="w-full flex justify-center">
        <div className=" w-3/4 pt-5 pt-10 md:pt-20 prose dark:prose-dark">
          <h1 className="text-5xl">Community</h1>
          <p className="text-3xl">On this page, we’ve listed some WPGraphQL-related communities that you can be a part of, and tools and resources in the community that may benefit you as you use WPGraphQL.</p>
        </div>
      </header>
      <main className="content mx-auto w-3/4 pt-5 pb-5 pt-10 md:pb-20 prose dark:prose-dark">
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {communities.map((community, i) => (
            <div key={i} className="box-shadow bg-slate-200 dark:bg-slate-800 rounded-lg drop-shadow-lg p-5 pb-7">
              { community?.icon && <div className="flex h-12 w-12 items-center justify-center rounded-md bg-indigo-500 text-white"><community.icon className="h-6 w-6" aria-hidden="true" /></div> }
              <h3 className="">{community.name}</h3>
              <p>{community.description}</p>
              <a
                className="bg-slate-500 hover:bg-slate-400 text-white dark:text-white font-bold py-2 px-4 rounded inline-flex items-center border-b-0"
                href={community.link}
                target="_blank"
                rel="noreferrer"
              >Visit {community.name}</a>
            </div>
          ))}
        </div>
      </main>
    </SiteLayout>
  )
}

export default Community;

export async function getStaticProps() {
  const client = getApolloClient()
  await client.query({query: GET_NAV_MENU})
  return addApolloState( client, {
    props: {
      revalidate: 30
    }
  })
}
