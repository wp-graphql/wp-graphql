import { createContext, createElement, useContext } from "react"

const LayoutContext = createContext(null)

export function LayoutProvider({ value, children }) {
  return createElement(LayoutContext.Provider, { value }, children)
}

export function useLayoutData() {
  return useContext(LayoutContext)
}
