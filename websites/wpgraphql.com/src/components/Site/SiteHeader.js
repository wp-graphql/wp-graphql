import { Fragment, useState, useEffect } from "react"
import Link from "next/link"
import gql from "graphql-tag"
import { Popover, Transition } from "@headlessui/react"
import {
  Bars3Icon as MenuIcon,
  XMarkIcon as XIcon,
} from "@heroicons/react/24/outline"
import { ChevronDownIcon } from "@heroicons/react/20/solid"

import DynamicHeroIcon from "../DynamicHeroIcon"
import SiteLogo from "./SiteLogo"
import ThemeToggle from "components/Site/ThemeToggle"
import {
  flatListToHierarchical,
  getIconNameFromMenuItem,
} from "lib/menu-helpers"
import { useLayoutData } from "lib/wpgraphql-client"
import { socialHeaderLinks } from "../../data/social"
import { SearchButton } from "./SearchButton"
import { cn } from "@/lib/utils"

export const NavMenuFragment = gql`
  fragment NavMenu on RootQuery {
    menu(id: "Primary Nav", idType: NAME) {
      id
      name
      menuItems(first: 100) {
        nodes {
          id
          label
          description
          url
          target
          path
          parentId
          cssClasses
        }
      }
    }
  }
`

export default function SiteHeader() {
  const [scrolled, setScrolled] = useState(false)

  const layoutData = useLayoutData()
  const menuItems = flatListToHierarchical(layoutData?.menu?.menuItems?.nodes, {
    idKey: "id",
    parentKey: "parentId",
    childrenKey: "children",
  })

  useEffect(() => {
    const handleScroll = () => setScrolled(window.scrollY > 50)
    window.addEventListener("scroll", handleScroll, { passive: true })
    handleScroll()
    return () => window.removeEventListener("scroll", handleScroll)
  }, [])

  return (
    <Popover
      as="header"
      className={cn(
        "sticky top-0 z-50 w-full border-b border-border bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60 transition-shadow",
        scrolled && "shadow-elev-md"
      )}
    >
      <div className="mx-auto flex max-w-8xl items-center justify-between gap-6 px-4 py-3 sm:px-6 md:justify-start md:space-x-10">
        <div className="flex flex-1 justify-start lg:w-0">
          <Link href="/" legacyBehavior>
            <a className="flex items-center">
              <span className="sr-only">WPGraphQL</span>
              <SiteLogo size={36} />
            </a>
          </Link>
        </div>

        {/* Mobile controls */}
        <div className="-my-2 -mr-2 flex items-center md:hidden">
          <SearchButton className="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
            <span className="sr-only">Search</span>
            <svg
              width="20"
              height="20"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="m19 19-3.5-3.5" />
              <circle cx="11" cy="11" r="6" />
            </svg>
          </SearchButton>
          <ThemeToggle />
          <Popover.Button className="ml-1 inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <span className="sr-only">Open menu</span>
            <MenuIcon className="h-5 w-5" aria-hidden="true" />
          </Popover.Button>
        </div>

        {/* Desktop nav */}
        <Popover.Group as="nav" className="hidden md:flex items-center gap-8">
          {menuItems &&
            menuItems.map((item) => {
              if (!item.children || !item.children.length) {
                return (
                  <Link key={item.id} href={item.path} legacyBehavior>
                    <a className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                      {item.label}
                    </a>
                  </Link>
                )
              }
              return (
                <Popover key={item.id} className="relative">
                  {({ open }) => (
                    <>
                      <Popover.Button
                        className={cn(
                          "group inline-flex items-center gap-1 rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                          open ? "text-foreground" : "text-muted-foreground hover:text-foreground"
                        )}
                      >
                        <span>{item.label}</span>
                        <ChevronDownIcon
                          className={cn(
                            "h-4 w-4 transition-transform",
                            open && "rotate-180"
                          )}
                          aria-hidden="true"
                        />
                      </Popover.Button>

                      <Transition
                        as={Fragment}
                        enter="transition ease-out duration-200"
                        enterFrom="opacity-0 translate-y-1"
                        enterTo="opacity-100 translate-y-0"
                        leave="transition ease-in duration-150"
                        leaveFrom="opacity-100 translate-y-0"
                        leaveTo="opacity-0 translate-y-1"
                      >
                        <Popover.Panel className="absolute z-50 -ml-4 mt-3 w-screen max-w-md transform lg:left-1/2 lg:ml-0 lg:max-w-2xl lg:-translate-x-1/2">
                          <div className="overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-elev-lg">
                            <div className="grid gap-2 p-4 sm:gap-4 sm:p-6 lg:grid-cols-2">
                              {item.children?.map((menuItem) => {
                                const icon = getIconNameFromMenuItem(menuItem)
                                return (
                                  <a
                                    key={menuItem.id}
                                    href={menuItem.path}
                                    className="-m-2 flex items-start gap-4 rounded-lg p-3 transition-colors hover:bg-accent"
                                  >
                                    {icon && (
                                      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md border border-border bg-muted text-primary">
                                        <DynamicHeroIcon icon={icon} />
                                      </div>
                                    )}
                                    <div>
                                      <p className="text-sm font-semibold text-foreground">
                                        {menuItem.label}
                                      </p>
                                      {menuItem.description && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                          {menuItem.description}
                                        </p>
                                      )}
                                    </div>
                                  </a>
                                )
                              })}
                            </div>
                          </div>
                        </Popover.Panel>
                      </Transition>
                    </>
                  )}
                </Popover>
              )
            })}
        </Popover.Group>

        {/* Desktop right rail */}
        <div className="hidden flex-1 items-center justify-end gap-3 md:flex lg:w-0">
          <SearchButton className="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
            <span className="sr-only">Search</span>
            <svg
              width="18"
              height="18"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden="true"
            >
              <path d="m19 19-3.5-3.5" />
              <circle cx="11" cy="11" r="6" />
            </svg>
          </SearchButton>
          {socialHeaderLinks.map((item) => (
            <a
              key={item.name}
              href={item.href}
              className="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
              target="_blank"
              rel="noreferrer"
            >
              <span className="sr-only">{item.name}</span>
              <item.icon className="h-5 w-5" aria-hidden="true" />
            </a>
          ))}
          <ThemeToggle />
        </div>
      </div>

      {/* Mobile menu panel */}
      <Transition
        as={Fragment}
        enter="duration-200 ease-out"
        enterFrom="opacity-0 scale-95"
        enterTo="opacity-100 scale-100"
        leave="duration-100 ease-in"
        leaveFrom="opacity-100 scale-100"
        leaveTo="opacity-0 scale-95"
      >
        <Popover.Panel
          focus
          className="absolute inset-x-0 top-0 z-50 origin-top-right transform p-2 transition md:hidden"
        >
          <div className="rounded-xl border border-border bg-popover text-popover-foreground shadow-elev-lg">
            <div className="px-5 pt-5 pb-6">
              <div className="flex items-center justify-between">
                <Link href="/" legacyBehavior>
                  <a>
                    <SiteLogo size={32} />
                  </a>
                </Link>
                <Popover.Button className="-mr-2 inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                  <span className="sr-only">Close menu</span>
                  <XIcon className="h-5 w-5" aria-hidden="true" />
                </Popover.Button>
              </div>
              <nav className="mt-6 grid grid-cols-1 gap-2">
                {menuItems.map((menuItem) => {
                  const icon = getIconNameFromMenuItem(menuItem)
                  return (
                    <Link
                      key={menuItem.path}
                      href={menuItem.path}
                      legacyBehavior
                    >
                      <a className="flex items-center gap-4 rounded-lg p-3 transition-colors hover:bg-accent">
                        {icon ? (
                          <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-md border border-border bg-muted text-primary">
                            <DynamicHeroIcon icon={icon} />
                          </div>
                        ) : null}
                        <div className="text-sm font-semibold text-foreground">
                          {menuItem.label}
                        </div>
                      </a>
                    </Link>
                  )
                })}
              </nav>
            </div>
          </div>
        </Popover.Panel>
      </Transition>
    </Popover>
  )
}
