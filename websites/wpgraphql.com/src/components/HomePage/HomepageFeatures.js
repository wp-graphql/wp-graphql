import MockIDE, { Tok } from "@/components/MockIDE"

// ─── "Query what you need" — pick exactly which fields come back ───────────
const exactFieldsQuery = (
  <>
    <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="key">user</Tok><Tok kind="punc">(</Tok><Tok kind="key">id</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"dXNlcjox\""}</Tok><Tok kind="punc">) {"{"}</Tok>{"\n"}
    {"    "}<Tok kind="key">name</Tok>{"\n"}
    {"    "}<Tok kind="key">email</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>
  </>
)
const exactFieldsResponse = (
  <>
    <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="str">{"\"data\""}</Tok><Tok kind="punc">: {"{"}</Tok>{"\n"}
    {"    "}<Tok kind="str">{"\"user\""}</Tok><Tok kind="punc">: {"{"}</Tok>{"\n"}
    {"      "}<Tok kind="str">{"\"name\""}</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"Jane Doe\""}</Tok><Tok kind="punc">,</Tok>{"\n"}
    {"      "}<Tok kind="str">{"\"email\""}</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"jane@example.com\""}</Tok>{"\n"}
    {"    "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>
  </>
)

// ─── "Nested resources" — follow connections in a single request ────────────
const nestedQuery = (
  <>
    <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="key">posts</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"    "}<Tok kind="key">nodes</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"      "}<Tok kind="key">title</Tok>{"\n"}
    {"      "}<Tok kind="key">author</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"        "}<Tok kind="key">node</Tok> <Tok kind="punc">{"{ "}</Tok><Tok kind="key">name</Tok><Tok kind="punc">{" }"}</Tok>{"\n"}
    {"      "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"      "}<Tok kind="key">categories</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"        "}<Tok kind="key">nodes</Tok> <Tok kind="punc">{"{ "}</Tok><Tok kind="key">name</Tok><Tok kind="punc">{" }"}</Tok>{"\n"}
    {"      "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"    "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>
  </>
)
const nestedResponse = (
  <>
    <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="str">{"\"data\""}</Tok><Tok kind="punc">: {"{"}</Tok>{"\n"}
    {"    "}<Tok kind="str">{"\"posts\""}</Tok><Tok kind="punc">: {"{"}</Tok>{"\n"}
    {"      "}<Tok kind="str">{"\"nodes\""}</Tok><Tok kind="punc">: [{"{"}</Tok>{"\n"}
    {"        "}<Tok kind="str">{"\"title\""}</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"Hello, world\""}</Tok><Tok kind="punc">,</Tok>{"\n"}
    {"        "}<Tok kind="str">{"\"author\""}</Tok><Tok kind="punc">: {"{ "}</Tok><Tok kind="str">{"\"node\""}</Tok><Tok kind="punc">: {"{ "}</Tok>{"\n"}
    {"          "}<Tok kind="str">{"\"name\""}</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"Jane\""}</Tok>{"\n"}
    {"        "}<Tok kind="punc">{"} }"}</Tok><Tok kind="punc">,</Tok>{"\n"}
    {"        "}<Tok kind="str">{"\"categories\""}</Tok><Tok kind="punc">: {"{ "}</Tok><Tok kind="str">{"\"nodes\""}</Tok><Tok kind="punc">: [{"{ "}</Tok>{"\n"}
    {"          "}<Tok kind="str">{"\"name\""}</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"News\""}</Tok>{"\n"}
    {"        "}<Tok kind="punc">{"} ] }"}</Tok>{"\n"}
    {"      "}<Tok kind="punc">{"}]"}</Tok>{"\n"}
    {"    "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>
  </>
)

function Feature({ eyebrow, title, body, query, response }) {
  return (
    <section className="py-20 sm:py-28">
      <div className="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:max-w-7xl lg:px-8">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          {eyebrow}
        </p>
        <h2 className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          {title}
        </h2>
        <p className="mx-auto mt-5 max-w-prose text-base text-muted-foreground sm:text-lg">
          {body}
        </p>
        <div className="mx-auto mt-12 max-w-4xl">
          <MockIDE query={query} response={response} />
        </div>
      </div>
    </section>
  )
}

export default function HomepageFeatures() {
  return (
    <>
      <Feature
        eyebrow="Efficient Data Fetching"
        title="Query what you need. Get exactly that."
        body="With GraphQL, the client makes declarative queries, asking for the exact data needed, and exactly what was asked for is given in response — nothing more. Clients have control over their application, and the GraphQL server only fetches what was requested."
        query={exactFieldsQuery}
        response={exactFieldsResponse}
      />
      <Feature
        eyebrow="Nested Resources"
        title="Fetch many resources in a single request"
        body="GraphQL queries access multiple root resources and smoothly follow references between connected ones. While a typical REST API would require round-trip requests to many endpoints, GraphQL can return everything your app needs in one round-trip — quick even on slow mobile connections."
        query={nestedQuery}
        response={nestedResponse}
      />
    </>
  )
}
