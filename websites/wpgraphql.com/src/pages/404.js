import { request } from "lib/wpgraphql-client"

import { ChevronRightIcon } from "@heroicons/react/20/solid"
import DynamicHeroIcon from "components/DynamicHeroIcon"
import { getIconNameFromMenuItem } from "lib/menu-helpers"
import SiteLogo from "components/Site/SiteLogo"
import Link from "next/link"
import { Button } from "@/components/ui/button"

const NOT_FOUND_QUERY = /* GraphQL */ `
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

export default function NotFound({ menu }) {
  const links = menu?.menuItems?.nodes ?? []

  return (
    <main className="mx-auto w-full max-w-3xl px-6 py-20 sm:py-28">
      <div className="flex justify-center pt-4">
        <SiteLogo size={56} />
      </div>
      <div className="mt-12 text-center">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          404 error
        </p>
        <h1 className="mt-3 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
          This page does not exist.
        </h1>
        <p className="mt-4 text-base text-muted-foreground sm:text-lg">
          The page you are looking for could not be found.
        </p>
        <div className="mt-8 flex justify-center">
          <Button asChild size="lg">
            <Link href="/">Go back home</Link>
          </Button>
        </div>
      </div>

      {links.length > 0 && (
        <div className="mt-16">
          <h2 className="font-mono text-xs font-medium uppercase tracking-widest text-muted-foreground">
            Popular pages
          </h2>
          <ul role="list" className="mt-4 divide-y divide-border border-y border-border">
            {links.map((link) => (
              <li key={link.path} className="relative flex items-start gap-4 py-5">
                <span className="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-md border border-border bg-muted text-primary">
                  <DynamicHeroIcon
                    icon={getIconNameFromMenuItem(link)}
                    className="h-5 w-5"
                    aria-hidden="true"
                  />
                </span>
                <div className="min-w-0 flex-1">
                  <h3 className="text-base font-semibold text-foreground">
                    <Link href={link.path} legacyBehavior>
                      <a className="focus:outline-none">
                        <span className="absolute inset-0" aria-hidden="true" />
                        {link.label}
                      </a>
                    </Link>
                  </h3>
                  <p className="text-sm text-muted-foreground">{link.description}</p>
                </div>
                <ChevronRightIcon
                  className="h-5 w-5 flex-shrink-0 self-center text-muted-foreground"
                  aria-hidden="true"
                />
              </li>
            ))}
          </ul>
        </div>
      )}
    </main>
  )
}

export async function getStaticProps() {
  const result = await request({ query: NOT_FOUND_QUERY })
  const data = result?.data ?? {}
  return {
    props: { menu: data.menu ?? null },
    revalidate: 30,
  }
}
