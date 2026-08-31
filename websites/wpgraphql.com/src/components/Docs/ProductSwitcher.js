import { Fragment } from "react"
import { useRouter } from "next/router"
import { Listbox, Transition } from "@headlessui/react"
import { CheckIcon, ChevronUpDownIcon } from "@heroicons/react/20/solid"

import { docsPortalProducts } from "../../data/docs-portal"

/**
 * The docs-portal product switcher: a select-style control that changes which
 * product's documentation you are reading (WPGraphQL, WPGraphQL for ACF,
 * Smart Cache, IDE). Rendered as a Listbox rather than a list of links so it
 * reads — visually and to assistive tech — as "switch context", not "more
 * nav items". Choosing a product navigates to that product's docs home.
 */
export default function ProductSwitcher({ product }) {
  const router = useRouter()
  const products = docsPortalProducts()

  if (products.length < 2) {
    return null
  }

  const current =
    products.find((item) => item.key === product?.key) ?? products[0]

  return (
    <div className="mb-6">
      <Listbox
        value={current.key}
        onChange={(key) => {
          const next = products.find((item) => item.key === key)
          if (next && next.key !== current.key) {
            router.push(next.basePath)
          }
        }}
      >
        <Listbox.Label className="mb-2 block text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          Viewing docs for
        </Listbox.Label>
        <div className="relative">
          <Listbox.Button className="flex w-full items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-left text-sm font-medium text-foreground shadow-sm transition-colors hover:border-primary/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            {current.Mark && (
              <current.Mark size={20} className="h-5 w-5 flex-shrink-0" />
            )}
            <span className="flex-1 truncate">{current.label}</span>
            <ChevronUpDownIcon
              className="h-4 w-4 flex-shrink-0 text-muted-foreground"
              aria-hidden="true"
            />
          </Listbox.Button>
          <Transition
            as={Fragment}
            leave="transition ease-in duration-100"
            leaveFrom="opacity-100"
            leaveTo="opacity-0"
          >
            <Listbox.Options className="absolute z-30 mt-1 w-full overflow-hidden rounded-md border border-border bg-card py-1 shadow-lg focus:outline-none">
              {products.map((item) => (
                <Listbox.Option
                  key={item.key}
                  value={item.key}
                  className={({ active }) =>
                    // Each option carries its product's theme scope so the
                    // selected/check accent renders in that product's hue.
                    `${item.theme ?? ""} flex cursor-pointer items-center gap-2 px-3 py-2 text-sm ${
                      active
                        ? "bg-accent text-foreground"
                        : "text-muted-foreground"
                    }`
                  }
                >
                  {({ selected }) => (
                    <>
                      {item.Mark && (
                        <item.Mark
                          size={20}
                          className="h-5 w-5 flex-shrink-0"
                        />
                      )}
                      <span
                        className={`flex-1 truncate ${
                          selected ? "font-semibold text-primary" : ""
                        }`}
                      >
                        {item.label}
                      </span>
                      {selected && (
                        <CheckIcon
                          className="h-4 w-4 flex-shrink-0 text-primary"
                          aria-hidden="true"
                        />
                      )}
                    </>
                  )}
                </Listbox.Option>
              ))}
            </Listbox.Options>
          </Transition>
        </div>
      </Listbox>
    </div>
  )
}
