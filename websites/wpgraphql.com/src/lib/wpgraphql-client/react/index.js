// React adapter. Provides a context-based mechanism for layout data so
// shared chrome (nav menu, footer, site settings) doesn't have to be
// passed through every template by hand.
//
// React-specific but not Next.js-specific — this works in any React
// framework (Next.js, Remix, Astro w/ React, Gatsby, etc.).

export { LayoutProvider, useLayoutData } from "./layout.js"
