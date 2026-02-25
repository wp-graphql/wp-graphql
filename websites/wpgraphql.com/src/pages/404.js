import { gql, useQuery } from "@apollo/client"
import { getApolloClient, addApolloState } from "@faustwp/core/dist/mjs/client"

import { ChevronRightIcon } from "@heroicons/react/20/solid"
import DynamicHeroIcon from "components/DynamicHeroIcon"
import { getIconNameFromMenuItem } from "lib/menu-helpers"
import SiteLogo from "components/Site/SiteLogo"
import Link from "next/link"

const NOT_FOUND_QUERY = gql`
  query NotFoundQuery {
    menu(id: "Primary Nav", idType: NAME) {
      id
      name
      menuItems(where: { parentId: 0 }) {
        nodes {
          label
          description
          url
          path
          cssClasses
        }
      }
    }
  }
`

export default function NotFound() {
  const { data } = useQuery(NOT_FOUND_QUERY)
  const links = data.menu.menuItems.nodes

  return (
    <div className="bg-white">
      <main className="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex-shrink-0 pt-16 text-center">
          <SiteLogo width={100} height={100} />
        </div>
        <div className="max-w-xl mx-auto py-16 sm:py-24">
          <div className="text-center">
            <p className="text-sm font-semibold text-indigo-600 uppercase tracking-wide">
              404 error
            </p>
            <h1 className="mt-2 text-4xl font-extrabold text-gray-900 tracking-tight sm:text-5xl">
              This page does not exist.
            </h1>
            <p className="mt-2 text-lg text-gray-500">
              The page you are looking for could not be found.
            </p>
          </div>
          <div className="mt-12">
            <h2 className="text-sm font-semibold text-gray-500 tracking-wide uppercase">
              Popular pages
            </h2>
            <ul
              role="list"
              className="mt-4 border-t border-b border-gray-200 divide-y divide-gray-200"
            >
              {links.map((link) => (
                <li
                  key={link.path}
                  className="relative py-6 flex items-start space-x-4"
                >
                  <div className="flex-shrink-0">
                    <span className="flex items-center justify-center h-12 w-12 rounded-lg bg-indigo-50">
                      <DynamicHeroIcon
                        icon={getIconNameFromMenuItem(link)}
                        className="h-6 w-6 text-indigo-700"
                        aria-hidden="true"
                      />
                    </span>
                  </div>
                  <div className="min-w-0 flex-1">
                    <h3 className="text-base font-medium text-gray-900">
                      <span className="rounded-sm focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                        <Link href={link.path}>
                          <a className="focus:outline-none">
                            <span
                              className="absolute inset-0"
                              aria-hidden="true"
                            />
                            {link.label}
                          </a>
                        </Link>
                      </span>
                    </h3>
                    <p className="text-base text-gray-500">
                      {link.description}
                    </p>
                  </div>
                  <div className="flex-shrink-0 self-center">
                    <ChevronRightIcon
                      className="h-5 w-5 text-gray-400"
                      aria-hidden="true"
                    />
                  </div>
                </li>
              ))}
            </ul>
            <div className="mt-8">
              <Link href="/">
                <a className="text-base font-medium text-indigo-600 hover:text-indigo-500">
                  Or go back home<span aria-hidden="true"> &rarr;</span>
                </a>
              </Link>
            </div>
          </div>
        </div>
      </main>
    </div>
  )
}

export async function getStaticProps(ctx) {
  const client = getApolloClient()

  const { data } = await client.query({ query: NOT_FOUND_QUERY })

  return addApolloState(client, {
    props: {},
    revalidate: 30,
  })
}
