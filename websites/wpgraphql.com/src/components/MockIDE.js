/**
 * Renders a miniature, always-dark "GraphiQL"-style IDE with a Query pane on
 * the left and a Response pane on the right. Used on the homepage to
 * illustrate WPGraphQL in action — replaces flat screenshot PNGs with a
 * crisper, scalable mock that reads at any size.
 *
 * Pass `query` and `response` as React children. Use the exported `Tok`
 * helper to syntax-tint individual tokens. Whitespace + newlines in the
 * children are preserved by `<pre>` so you can lay the code out exactly.
 */

const TOKEN_CLASSES = {
  // Field names / JSON object keys (warm blue, like Night Owl's ":methods")
  key:  "text-[#82AAFF]",
  // Strings (warm tan)
  str:  "text-[#ECC48D]",
  // Numbers / dates
  num:  "text-[#F78C6C]",
  // Keywords (true / false / null / GraphQL `query`)
  kw:   "text-[#C792EA]",
  // Punctuation: braces, brackets, commas, colons
  punc: "text-[#637777]",
  // Comments / muted hints
  cmt:  "text-[#637777] italic",
}

export function Tok({ kind = "key", children }) {
  return <span className={TOKEN_CLASSES[kind] || TOKEN_CLASSES.key}>{children}</span>
}

function PaneHeader({ children }) {
  return (
    <div className="border-b border-[#1E2D50] bg-[#0A0F1E]/60 px-4 py-2 font-mono text-[0.6rem] font-medium uppercase tracking-widest text-[#7189B0]">
      {children}
    </div>
  )
}

export default function MockIDE({
  query,
  response,
  label = "GraphiQL",
  className = "",
}) {
  return (
    <div
      className={`overflow-hidden rounded-xl border border-[#1E2D50] bg-[#0E1628] shadow-elev-lg ${className}`}
      role="img"
      aria-label="Mock GraphQL IDE showing a query and its response"
    >
      {/* Window chrome */}
      <div className="flex items-center justify-between border-b border-[#1E2D50] bg-[#162039] px-4 py-2.5">
        <div className="flex gap-1.5">
          <span className="size-2.5 rounded-full bg-[#FF5F57]" />
          <span className="size-2.5 rounded-full bg-[#FEBC2E]" />
          <span className="size-2.5 rounded-full bg-[#28C840]" />
        </div>
        <span className="font-mono text-[0.65rem] font-medium uppercase tracking-widest text-[#7189B0]">
          {label}
        </span>
      </div>

      {/* Two-pane body — stacks on mobile, side-by-side on sm+ */}
      <div className="grid grid-cols-1 sm:grid-cols-2 divide-y divide-[#1E2D50] sm:divide-y-0 sm:divide-x">
        <section>
          <PaneHeader>Query</PaneHeader>
          <pre className="overflow-x-auto px-4 py-4 font-mono text-[0.8rem] leading-relaxed text-[#D6DEEB]">
            {query}
          </pre>
        </section>
        <section>
          <PaneHeader>Response</PaneHeader>
          <pre className="overflow-x-auto px-4 py-4 font-mono text-[0.8rem] leading-relaxed text-[#D6DEEB]">
            {response}
          </pre>
        </section>
      </div>
    </div>
  )
}
