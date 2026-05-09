import { Tok } from "@/components/MockIDE"

/**
 * Demonstrates GraphQL fragment colocation: each component declares the
 * data it needs as a fragment next to its own JSX, and the page-level
 * query composes those fragments.
 *
 * Visual: left pane shows a mock UI of a "Recent Posts" feed with three
 * PostCard items. Right pane shows the corresponding fragment + query
 * pairs. Same window chrome as MockIDE for visual cohesion.
 */

const POSTS = [
  { title: "Hello, world", author: "Jane Doe",   date: "Dec 8, 2025" },
  { title: "Welcome to wpgraphql.blog", author: "Alex Chen", date: "Dec 4, 2025" },
  { title: "Querying sticky posts with GraphQL", author: "Sam Patel", date: "Nov 27, 2025" },
]

function PaneHeader({ children }) {
  return (
    <div className="ide-pane-bg ide-muted border-b ide-border px-4 py-2 text-left font-mono text-[0.6rem] font-medium uppercase tracking-widest">
      {children}
    </div>
  )
}

function PostCard({ title, author, date }) {
  return (
    <article className="ide-pane-bg group relative rounded-lg border ide-border p-3">
      <span
        aria-hidden="true"
        className="absolute -top-2 left-3 rounded-sm border ide-border ide-pane-bg ide-tok-key px-1.5 py-0.5 font-mono text-[0.55rem] uppercase tracking-widest opacity-0 transition-opacity group-hover:opacity-100"
      >
        &lt;PostCard /&gt;
      </span>
      <h4 className="ide-text text-[0.85rem] font-semibold leading-tight">{title}</h4>
      <p className="ide-muted mt-1 font-mono text-[0.65rem]">
        by <span className="ide-tok-str">{author}</span>{" · "}{date}
      </p>
    </article>
  )
}

const colocatedCode = (
  <>
    <Tok kind="cmt">{"// pages/index.tsx"}</Tok>{"\n"}
    <Tok kind="kw">query</Tok> <Tok kind="key">GetRecentPosts</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="key">posts</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"    "}<Tok kind="key">nodes</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"      "}<Tok kind="punc">...</Tok><Tok kind="key">PostCard</Tok>{"\n"}
    {"    "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>{"\n"}
    {"\n"}
    <Tok kind="cmt">{"// components/PostCard.tsx"}</Tok>{"\n"}
    <Tok kind="kw">fragment</Tok> <Tok kind="key">PostCard</Tok> <Tok kind="kw">on</Tok> <Tok kind="key">Post</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="key">id</Tok>{"\n"}
    {"  "}<Tok kind="key">title</Tok>{"\n"}
    {"  "}<Tok kind="key">date</Tok>{"\n"}
    {"  "}<Tok kind="key">author</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"    "}<Tok kind="key">node</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"      "}<Tok kind="key">name</Tok>{"\n"}
    {"    "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>
  </>
)

export default function ColocationDemo() {
  return (
    <div
      className="ide-bg ide-text overflow-hidden rounded-xl border ide-border text-left shadow-elev-lg"
      role="img"
      aria-label="Data colocation demo: a posts feed UI with co-located GraphQL fragments"
    >
      {/* Window chrome */}
      <div className="ide-header-bg flex items-center justify-between border-b ide-border px-4 py-2.5">
        <div className="flex gap-1.5">
          <span className="size-2.5 rounded-full bg-[#FF5F57]" />
          <span className="size-2.5 rounded-full bg-[#FEBC2E]" />
          <span className="size-2.5 rounded-full bg-[#28C840]" />
        </div>
        <span className="ide-muted font-mono text-[0.65rem] font-medium uppercase tracking-widest">
          GraphQL · React
        </span>
      </div>

      {/* Two-pane body */}
      <div className="grid grid-cols-1 sm:grid-cols-2 [&>*+*]:border-t [&>*+*]:ide-border sm:[&>*+*]:border-t-0 sm:[&>*+*]:border-l">
        {/* Left: mock UI */}
        <section>
          <PaneHeader>Preview</PaneHeader>
          <div className="space-y-3 p-4">
            <h3 className="ide-text font-mono text-[0.7rem] uppercase tracking-widest">
              Recent Posts
            </h3>
            {POSTS.map((post) => (
              <PostCard key={post.title} {...post} />
            ))}
            <p className="ide-muted pt-1 text-center font-mono text-[0.55rem]">
              Hover a card to see its component name
            </p>
          </div>
        </section>

        {/* Right: colocated code */}
        <section>
          <PaneHeader>Co-located Fragments</PaneHeader>
          <pre className="overflow-x-auto px-4 py-4 text-left font-mono text-[0.78rem] leading-relaxed">
            {colocatedCode}
          </pre>
        </section>
      </div>
    </div>
  )
}
