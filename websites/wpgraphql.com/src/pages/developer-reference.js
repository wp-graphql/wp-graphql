import { gql } from "@apollo/client"
import { getApolloClient, addApolloState } from "@faustwp/core/dist/mjs/client"

import { NavMenuFragment } from "../components/Site/SiteHeader"
import SiteLayout from "components/Site/SiteLayout"
import {BoltIcon, BookOpenIcon, CodeBracketIcon} from "@heroicons/react/20/solid";
import {FaFilter} from "react-icons/fa";

const DEVELOPER_REFERENCE_QUERY = gql`
  query NotFoundPageQuery {
    ...NavMenu
  }
  ${NavMenuFragment}
`

export default function DeveloperReference() {

  const references = [
    {
      name: "Recipes",
      description: "Tasty treats that will boost your productivity. WPGraphQL Recipes are snippets that showcase how to customize WPGraphQL in specific ways using the actions, filters and functions available to WPGraphQL.",
      icon: BookOpenIcon,
      link: '/recipes'
    },
    {
      name: "Functions",
      description: "Need to add a Field or Type to the GraphQL Schema? There's a function for that. Learn more about the functions available to make your WPGraphQL server work for you.",
      icon: CodeBracketIcon,
      link: '/functions'
    },
    {
      name: "Actions",
      description: "Actions allow 3rd party code to hook-into WPGraphQL at certain parts of GraphQL execution. Learn about the actions available to hook into.",
      icon: BoltIcon,
      link: "/actions",
    },
    {
      name: "Filters",
      description: "Filters allow 3rd party code to modify data at certain parts of GraphQL execution. Learn about the filters available to hook into.",
      icon: FaFilter,
      link: "/filters"
    }
  ];

  return (
    <SiteLayout>
      <main className="content max-w-8xl mx-auto my-10 px-4 sm:px-6 lg:px-8 prose dark:prose-dark">
        <header>
          <h1 className="text-5xl">Developer Reference</h1>
          <p className="text-3xl">WPGraphQL was built with customization in mind. In this section, developers can find information about how to interact with WPGraphQL to customize the GraphQL server and Schema.</p>
        </header>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
          {references.map((reference, i) => (
            <div key={i} className="box-shadow bg-slate-200 dark:bg-slate-900 rounded-lg drop-shadow-lg p-5 pb-7">
              { reference?.icon && <div className="flex h-12 w-12 items-center justify-center rounded-md bg-gradient-build text-white"><reference.icon className="h-6 w-6" aria-hidden="true" /></div> }
              <h3 className="">{reference.name}</h3>
              <p>{reference.description}</p>
              <div className="not-prose">
                <a
                  className="btn-primary-sm"
                  href={reference.link}
                  target="_blank"
                  rel="noreferrer"
                >Visit {reference.name}</a>
              </div>
            </div>
          ))}
        </div>
      </main>
    </SiteLayout>
  )
}

export async function getStaticProps(ctx) {
  const client = getApolloClient()
  await client.query({ query: DEVELOPER_REFERENCE_QUERY })
  return addApolloState(client, {
    props: {},
    revalidate: 30,
  })
}
