/**
 * Renders a miniature, theme-aware "GraphiQL"-style IDE with a Query pane on
 * the left and a Response pane on the right. Used on the homepage to
 * illustrate WPGraphQL in action — replaces flat screenshot PNGs with a
 * crisper, scalable mock that reads at any size.
 *
 * Colours come from the `.ide-*` semantic classes defined in globals.css,
 * which swap between dark and light values via the `.light` override.
 *
 * Pass `query` and `response` as React children. Use the exported `Tok`
 * helper to syntax-tint individual tokens. Whitespace + newlines in the
 * children are preserved by `<pre>` so you can lay the code out exactly.
 */

const TOKEN_CLASSES = {
  key:  "ide-tok-key",
  str:  "ide-tok-str",
  num:  "ide-tok-num",
  kw:   "ide-tok-kw",
  punc: "ide-tok-punc",
  cmt:  "ide-tok-cmt",
}

export function Tok({ kind = "key", children }) {
  return <span className={TOKEN_CLASSES[kind] || TOKEN_CLASSES.key}>{children}</span>
}

function PaneHeader({ children }) {
  return (
    <div className="ide-pane-bg ide-muted border-b ide-border px-4 py-2 text-left font-mono text-[0.6rem] font-medium uppercase tracking-widest">
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
      className={`ide-bg ide-text overflow-hidden rounded-xl border ide-border text-left shadow-elev-lg ${className}`}
      role="img"
      aria-label="Mock GraphQL IDE showing a query and its response"
    >
      {/* Window chrome */}
      <div className="ide-header-bg flex items-center justify-between border-b ide-border px-4 py-2.5">
        <div className="flex gap-1.5">
          <span className="size-2.5 rounded-full bg-[#FF5F57]" />
          <span className="size-2.5 rounded-full bg-[#FEBC2E]" />
          <span className="size-2.5 rounded-full bg-[#28C840]" />
        </div>
        <span className="ide-muted font-mono text-[0.65rem] font-medium uppercase tracking-widest">
          {label}
        </span>
      </div>

      {/* Two-pane body — stacks on mobile, side-by-side on sm+ */}
      <div className="ide-divide grid grid-cols-1 sm:grid-cols-2 [&>*+*]:border-t sm:[&>*+*]:border-t-0 sm:[&>*+*]:border-l">
        <section>
          <PaneHeader>Query</PaneHeader>
          <pre className="overflow-x-auto px-4 py-4 text-left font-mono text-[0.8rem] leading-relaxed">
            {query}
          </pre>
        </section>
        <section>
          <PaneHeader>Response</PaneHeader>
          <pre className="overflow-x-auto px-4 py-4 text-left font-mono text-[0.8rem] leading-relaxed">
            {response}
          </pre>
        </section>
      </div>
    </div>
  )
}
