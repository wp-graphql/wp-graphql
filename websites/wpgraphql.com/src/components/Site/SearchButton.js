import { createPortal } from "react-dom"
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react"
import { useRouter } from "next/router"
import Head from "next/head"
import { DocSearchModal } from "@docsearch/react"
import clsx from "clsx"
import Link from "next/link"
import useActionKey from "../../hooks/useActionKey"
import { CORE_PRODUCT_KEY, enabledProducts } from "lib/docs-products"

const INDEX_NAME = "wpgraphql"
const API_KEY = "0c11d662dad18e8a18d20c969b25c65f"
const APP_ID = "HB50HVJDY8"

// Paths whose search records carry the core product attribute: the core docs
// plus the developer reference (which renders in the docs chrome).
const CORE_SEARCH_SECTIONS =
  /^\/(docs|actions|filters|functions|recipes|developer-reference)(\/|$)/

/**
 * Which product's docs the given URL path is inside, for preseeding the
 * search modal's product filter. Outside the docs area there is no preseed
 * (family-wide search).
 */
function productKeyFromPath(path) {
  const withoutQuery = path.split(/[?#]/)[0]
  const docsMatch = withoutQuery.match(/^\/docs\/([^/]+)/)
  if (
    docsMatch &&
    enabledProducts().some(
      (product) =>
        product.key !== CORE_PRODUCT_KEY && product.key === docsMatch[1]
    )
  ) {
    return docsMatch[1]
  }
  return CORE_SEARCH_SECTIONS.test(withoutQuery) ? CORE_PRODUCT_KEY : null
}

const SearchContext = createContext()

export function SearchProvider({ children }) {
  const router = useRouter()
  const [isOpen, setIsOpen] = useState(false)
  const [initialQuery, setInitialQuery] = useState(null)
  // null = family-wide (no facet filter); otherwise a product key. Preseeded
  // from the section the reader opened search from, switchable via the chip
  // row in the modal.
  const [productFilter, setProductFilter] = useState(null)

  const onOpen = useCallback(() => {
    setProductFilter(productKeyFromPath(router.asPath))
    setIsOpen(true)
  }, [setIsOpen, router.asPath])

  const onClose = useCallback(() => {
    setIsOpen(false)
  }, [setIsOpen])

  const onInput = useCallback(
    (e) => {
      setProductFilter(productKeyFromPath(router.asPath))
      setIsOpen(true)
      setInitialQuery(e.key)
    },
    [setIsOpen, setInitialQuery, router.asPath]
  )

  // Switching product mid-search remounts the modal (the filter is baked
  // into its search client), so carry the typed query over.
  const onFilterChange = useCallback(
    (key) => {
      const query = document.querySelector(".DocSearch-Input")?.value ?? ""
      setInitialQuery(query)
      setProductFilter(key)
    },
    [setInitialQuery, setProductFilter]
  )

  useDocSearchKeyboardEvents({
    isOpen,
    onOpen,
    onClose,
  })

  return (
    <>
      <Head>
        <link
          rel="preconnect"
          href={`https://${APP_ID}-dsn.algolia.net`}
          crossOrigin="true"
        />
      </Head>
      <SearchContext.Provider value={{ isOpen, onOpen, onClose, onInput }}>
        {children}
      </SearchContext.Provider>
      {isOpen &&
        createPortal(
          // Keyed by the product filter: the filter is baked into the modal's
          // search parameters at mount, so switching products remounts it
          // (initialQuery carries the typed query across).
          <DocSearchModal
            key={productFilter ?? "all"}
            initialQuery={initialQuery}
            initialScrollY={window.scrollY}
            searchParameters={{
              distinct: 1,
              ...(productFilter
                ? { facetFilters: [`product:${productFilter}`] }
                : {}),
            }}
            onClose={onClose}
            indexName={INDEX_NAME}
            apiKey={API_KEY}
            appId={APP_ID}
            placeholder="Search..."
            navigator={{
              navigate({ itemUrl }) {
                setIsOpen(false)
                router.push(itemUrl)
              },
            }}
            hitComponent={Hit}
          />,
          document.body
        )}
      {isOpen && (
        <ProductFilterChips
          key={`chips-${productFilter ?? "all"}`}
          value={productFilter}
          onChange={onFilterChange}
        />
      )}
    </>
  )
}

/**
 * The product filter chips, injected into the DocSearch modal below its
 * search bar. DocSearch has no slot for filter UI, so this renders into a
 * container inserted after the modal's search bar; it mounts alongside each
 * modal instance (both are keyed by the active filter).
 */
function ProductFilterChips({ value, onChange }) {
  // The container is created up front (this only renders client-side, with
  // the modal open) and attached into the modal's DOM after mount, so the
  // effect only synchronizes with the external DOM — no state involved.
  const [container] = useState(() => {
    const el = document.createElement("div")
    el.className = "DocSearch-ProductFilter"
    return el
  })

  useEffect(() => {
    const searchBar = document.querySelector(
      ".DocSearch-Modal .DocSearch-SearchBar"
    )
    if (!searchBar) {
      return undefined
    }
    searchBar.insertAdjacentElement("afterend", container)
    return () => {
      container.remove()
    }
  }, [container])

  const options = [
    { key: null, shortLabel: "All" },
    ...enabledProducts().map(({ key, shortLabel }) => ({ key, shortLabel })),
  ]

  return createPortal(
    <div
      role="group"
      aria-label="Filter results by product"
      className="DocSearch-ProductFilter-Chips"
    >
      {options.map((option) => (
        <button
          key={option.key ?? "all"}
          type="button"
          aria-pressed={value === option.key}
          className={clsx("DocSearch-ProductFilter-Chip", {
            "DocSearch-ProductFilter-Chip--active": value === option.key,
          })}
          onClick={() => {
            if (value !== option.key) {
              onChange(option.key)
            }
          }}
        >
          {option.shortLabel}
        </button>
      ))}
    </div>,
    container
  )
}

function Hit({ hit, children }) {
  return (
    <Link href={hit.url} legacyBehavior>
      <a
        className={clsx({
          "DocSearch-Hit--Result": hit.__is_result?.(),
          "DocSearch-Hit--Parent": hit.__is_parent?.(),
          "DocSearch-Hit--FirstChild": hit.__is_first?.(),
          "DocSearch-Hit--LastChild": hit.__is_last?.(),
          "DocSearch-Hit--Child": hit.__is_child?.(),
        })}
      >
        {children}
      </a>
    </Link>
  )
}

export function SearchButton({ children, ...props }) {
  let searchButtonRef = useRef()
  let actionKey = useActionKey()
  let { onOpen, onInput } = useContext(SearchContext)

  useEffect(() => {
    function onKeyDown(event) {
      if (
        searchButtonRef &&
        searchButtonRef.current === document.activeElement &&
        onInput
      ) {
        if (/[a-zA-Z0-9]/.test(String.fromCharCode(event.keyCode))) {
          onInput(event)
        }
      }
    }
    window.addEventListener("keydown", onKeyDown)
    return () => {
      window.removeEventListener("keydown", onKeyDown)
    }
  }, [onInput, searchButtonRef])

  return (
    <button type="button" ref={searchButtonRef} onClick={onOpen} {...props}>
      {typeof children === "function" ? children({ actionKey }) : children}
    </button>
  )
}

function useDocSearchKeyboardEvents({ isOpen, onOpen, onClose }) {
  useEffect(() => {
    function onKeyDown(event) {
      function open() {
        // We check that no other DocSearch modal is showing before opening
        // another one.
        if (!document.body.classList.contains("DocSearch--active")) {
          onOpen()
        }
      }

      if (
        (event.keyCode === 27 && isOpen) ||
        (event.key === "k" && (event.metaKey || event.ctrlKey)) ||
        (!isEditingContent(event) && event.key === "/" && !isOpen)
      ) {
        event.preventDefault()

        if (isOpen) {
          onClose()
        } else if (!document.body.classList.contains("DocSearch--active")) {
          open()
        }
      }
    }

    window.addEventListener("keydown", onKeyDown)
    return () => {
      window.removeEventListener("keydown", onKeyDown)
    }
  }, [isOpen, onOpen, onClose])
}

function isEditingContent(event) {
  let element = event.target
  let tagName = element.tagName
  return (
    element.isContentEditable ||
    tagName === "INPUT" ||
    tagName === "SELECT" ||
    tagName === "TEXTAREA"
  )
}
