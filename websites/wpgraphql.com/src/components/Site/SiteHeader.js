import { Fragment, useState, useEffect } from "react"
import Link from "next/link"
import { gql, useQuery } from "@apollo/client"
import classNames from "clsx"

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
import { socialHeaderLinks } from "../../data/social"
import { SearchButton } from "./SearchButton";

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

  const { data } = useQuery(
    gql`
      {
        ...NavMenu
      }
      ${NavMenuFragment}
    `
  )

  const menuItems = flatListToHierarchical(data?.menu?.menuItems?.nodes, {
    idKey: "id",
    parentKey: "parentId",
    childrenKey: "children",
  })

  useEffect(() => {
    window.onscroll = function () {
      if (window.scrollY > 50) {
        setScrolled(true)
      } else {
        setScrolled(false)
      }
    }
  }, [])

  return (
    <Popover
      className={
        scrolled
          ? `relative bg-white sticky top-0 z-50 shadow-xl dark:bg-navy`
          : `relative bg-white sticky top-0 z-50 dark:bg-navy border-b-2 border-b-gray-100 dark:border-b-navy`
      }
      as={"header"}
    >
      <div className="max-w-8xl mx-auto flex justify-between items-center px-4 py-4 sm:px-6 md:justify-start md:space-x-10">
        <div className="flex justify-start lg:w-0 lg:flex-1">
          <Link href="/">
            <a>
              <span className="sr-only">WPGraphQL</span>
              <div className="relative h-full w-auto sm:h-10">
                <SiteLogo />
              </div>
            </a>
          </Link>
        </div>
        <div className="-mr-2 -my-2 md:hidden flex justify-items-end items-center">
          <SearchButton className="ml-auto text-slate-500 w-8 h-8 -my-1 flex items-center justify-center hover:text-slate-600 lg:hidden dark:text-slate-400 dark:hover:text-slate-300">
            <span className="sr-only">Search</span>
            <svg
              width="24"
              height="24"
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
          <Popover.Button className="bg-white rounded-md p-2 ml-3 inline-flex items-center justify-center text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-500 dark:bg-navy">
            <span className="sr-only">Open menu</span>
            <MenuIcon className="h-6 w-6" aria-hidden="true" />
          </Popover.Button>

        </div>
        <Popover.Group as="nav" className="hidden md:flex space-x-10">
          {menuItems &&
            menuItems.map((item) => {
              if (!item.children || !item.children.length) {
                return (
                  <Link key={item.id} href={item.path}>
                    <a className="text-base font-medium text-gray-700 hover:text-gray-900 dark:text-white dark:hover:text-gray-300">
                      {item.label}
                    </a>
                  </Link>
                )
              } else {
                return (
                  <Popover key={item.id} className="relative">
                    {({ open }) => (
                      <>
                        <Popover.Button
                          className={classNames(
                            open
                              ? "text-gray-900 dark:text-white"
                              : "text-gray-700 dark:text-gray-100",
                            "group bg-white dark:bg-navy rounded-md inline-flex items-center text-base font-medium hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-200"
                          )}
                        >
                          <span>{item.label}</span>
                          <ChevronDownIcon
                            className={classNames(
                              open ? "text-gray-600" : "text-gray-400",
                              "ml-2 h-5 w-5 group-hover:text-gray-500"
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
                          <Popover.Panel className="absolute z-50 -ml-4 mt-3 transform w-screen max-w-md lg:max-w-2xl lg:ml-0 lg:left-1/2 lg:-translate-x-1/2">
                            <div className="rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 overflow-hidden">
                              <div className="relative grid gap-6 bg-white dark:bg-slate-900 px-5 py-6 sm:gap-8 sm:p-8 lg:grid-cols-2">
                                {item.children?.map((menuItem) => {
                                  let icon = getIconNameFromMenuItem(menuItem)

                                  return (
                                    <a
                                      key={menuItem.id}
                                      href={menuItem.path}
                                      className="-m-3 p-3 flex items-start rounded-lg hover:bg-gray-100 dark:hover:bg-slate-600"
                                    >
                                      {icon && (
                                        <div className="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-gradient-build text-white sm:h-12 sm:w-12">
                                          <DynamicHeroIcon icon={icon} />
                                        </div>
                                      )}
                                      <div className="ml-4">
                                        <p className="text-base font-medium text-gray-900 dark:text-gray-200 font-lora">
                                          {menuItem.label}
                                        </p>
                                        <p className="mt-1 text-sm text-gray-700 dark:text-slate-100">
                                          {menuItem.description}
                                        </p>
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
              }
            })}
        </Popover.Group>

        <div className="hidden md:flex items-center gap-4 justify-end md:flex-1 lg:w-0">
          <SearchButton className="ml-auto text-slate-500 w-8 h-8 -my-1 flex items-center justify-center hover:text-slate-600 dark:text-slate-400 dark:hover:text-slate-300">
            <span className="sr-only">Search</span>
            <svg
              width="24"
              height="24"
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
              className="text-gray-600 dark:text-gray-300 hover:text-gray-500 dark:hover:text-gray-100"
              target={"_blank"}
              rel="noreferrer"
            >
              <span className="sr-only">{item.name}</span>
              <item.icon className="h-6 w-6" aria-hidden="true" />
            </a>
          ))}
          <ThemeToggle />
        </div>
      </div>

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
          className="absolute z-50 top-0 inset-x-0 p-2 transition transform origin-top-right md:hidden"
        >
          <div className="rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 bg-white divide-y-2 divide-gray-50 dark:bg-slate-800">
            <div className="pt-5 pb-6 px-5">
              <div className="flex items-center align-center justify-between">
                <div className="h-full w-auto">
                  <Link href="/">
                    <a>
                      <SiteLogo />
                    </a>
                  </Link>
                </div>
                <div className="-mr-2">
                  <Popover.Button className="bg-white rounded-md p-2 inline-flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-500 dark:bg-slate-900">
                    <span className="sr-only">Close menu</span>
                    <XIcon className="h-6 w-6" aria-hidden="true" />
                  </Popover.Button>
                </div>
              </div>
              <div className="mt-6">
                <nav className="grid grid-cols-1 gap-7">
                  {menuItems.map((menuItem) => {
                    const icon = getIconNameFromMenuItem(menuItem)

                    return (
                      <Link
                        key={menuItem.path}
                        href={menuItem.path}
                        className="-m-3 p-3 flex items-center rounded-lg hover:bg-gray-50 dark:hover:bg-slate-900"
                      >
                        <a className="-m-3 p-3 flex items-start rounded-lg hover:bg-gray-100 dark:hover:bg-slate-600">
                          <div className="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-md bg-gradient-build text-white">
                            <DynamicHeroIcon icon={icon} />
                          </div>
                          <div className="ml-4 text-base font-medium text-gray-900 dark:text-white">
                            {menuItem.label}
                          </div>
                        </a>
                      </Link>
                    )
                  })}
                </nav>
              </div>
            </div>
          </div>
        </Popover.Panel>
      </Transition>
    </Popover>
  )
}
