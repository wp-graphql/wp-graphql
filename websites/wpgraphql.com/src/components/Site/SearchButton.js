import { createPortal } from "react-dom";
import {createContext, useCallback, useContext, useEffect, useRef, useState} from "react";
import { useRouter } from "next/router";
import Head from 'next/head'
import { DocSearchModal } from "@docsearch/react";
import clsx from 'clsx'
import Link from 'next/link'
import useActionKey from "../../hooks/useActionKey";

const INDEX_NAME = 'wpgraphql';
const API_KEY = '0c11d662dad18e8a18d20c969b25c65f';
const APP_ID = 'HB50HVJDY8';

const SearchContext = createContext();

export function SearchProvider({ children }) {
  const router = useRouter();
  const [isOpen, setIsOpen] = useState(false)
  const [initialQuery, setInitialQuery] = useState(null)

  const onOpen = useCallback(() => {
    setIsOpen(true)
  }, [setIsOpen])

  const onClose = useCallback(() => {
    setIsOpen(false)
  }, [setIsOpen])

  const onInput = useCallback((e) => {
    setIsOpen(true)
    setInitialQuery(e.key)
  }, [setIsOpen, setInitialQuery])

  useDocSearchKeyboardEvents({
    isOpen,
    onOpen,
    onClose
  })

  return (
    <>
      <Head>
        <link rel="preconnect" href={`https://${APP_ID}-dsn.algolia.net`} crossOrigin="true" />
      </Head>
      <SearchContext.Provider value={{ isOpen, onOpen, onClose, onInput }}>
        {children}
      </SearchContext.Provider>
      {isOpen && createPortal(
       <DocSearchModal
        initialQuery={initialQuery}
        initialScrollY={window.scrollY}
        searchParameters={{
          distinct: 1
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
       />, document.body
      )}
    </>
  )
}

function Hit({ hit, children }) {
  return (
    <Link href={hit.url}>
      <a
        className={clsx({
          'DocSearch-Hit--Result': hit.__is_result?.(),
          'DocSearch-Hit--Parent': hit.__is_parent?.(),
          'DocSearch-Hit--FirstChild': hit.__is_first?.(),
          'DocSearch-Hit--LastChild': hit.__is_last?.(),
          'DocSearch-Hit--Child': hit.__is_child?.(),
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
      if (searchButtonRef && searchButtonRef.current === document.activeElement && onInput) {
        if (/[a-zA-Z0-9]/.test(String.fromCharCode(event.keyCode))) {
          onInput(event)
        }
      }
    }
    window.addEventListener('keydown', onKeyDown)
    return () => {
      window.removeEventListener('keydown', onKeyDown)
    }
  }, [onInput, searchButtonRef])

  return (
    <button type="button" ref={searchButtonRef} onClick={onOpen} {...props}>
      {typeof children === 'function' ? children({ actionKey }) : children}
    </button>
  )
}

function useDocSearchKeyboardEvents({ isOpen, onOpen, onClose }) {
  useEffect(() => {
    function onKeyDown(event) {
      function open() {
        // We check that no other DocSearch modal is showing before opening
        // another one.
        if (!document.body.classList.contains('DocSearch--active')) {
          onOpen()
        }
      }

      if (
        (event.keyCode === 27 && isOpen) ||
        (event.key === 'k' && (event.metaKey || event.ctrlKey)) ||
        (!isEditingContent(event) && event.key === '/' && !isOpen)
      ) {
        event.preventDefault()

        if (isOpen) {
          onClose()
        } else if (!document.body.classList.contains('DocSearch--active')) {
          open()
        }
      }
    }

    window.addEventListener('keydown', onKeyDown)
    return () => {
      window.removeEventListener('keydown', onKeyDown)
    }
  }, [isOpen, onOpen, onClose])
}

function isEditingContent(event) {
  let element = event.target
  let tagName = element.tagName
  return (
    element.isContentEditable ||
    tagName === 'INPUT' ||
    tagName === 'SELECT' ||
    tagName === 'TEXTAREA'
  )
}
