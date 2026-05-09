import Link from "next/link"
import { Button } from "@/components/ui/button"
import MockIDE, { Tok } from "@/components/MockIDE"

const heroQuery = (
  <>
    <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="key">posts</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"    "}<Tok kind="key">nodes</Tok> <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"      "}<Tok kind="key">title</Tok>{"\n"}
    {"      "}<Tok kind="key">date</Tok>{"\n"}
    {"    "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>
  </>
)

const heroResponse = (
  <>
    <Tok kind="punc">{"{"}</Tok>{"\n"}
    {"  "}<Tok kind="str">{"\"data\""}</Tok><Tok kind="punc">: {"{"}</Tok>{"\n"}
    {"    "}<Tok kind="str">{"\"posts\""}</Tok><Tok kind="punc">: {"{"}</Tok>{"\n"}
    {"      "}<Tok kind="str">{"\"nodes\""}</Tok><Tok kind="punc">: [</Tok>{"\n"}
    {"        "}<Tok kind="punc">{"{"}</Tok>{"\n"}
    {"          "}<Tok kind="str">{"\"title\""}</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"Hello, world\""}</Tok><Tok kind="punc">,</Tok>{"\n"}
    {"          "}<Tok kind="str">{"\"date\""}</Tok><Tok kind="punc">: </Tok><Tok kind="str">{"\"2025-12-08\""}</Tok>{"\n"}
    {"        "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"      "}<Tok kind="punc">]</Tok>{"\n"}
    {"    "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    {"  "}<Tok kind="punc">{"}"}</Tok>{"\n"}
    <Tok kind="punc">{"}"}</Tok>
  </>
)

export default function HomepageHero() {
  return (
    <section className="relative overflow-hidden">
      <div className="mx-auto max-w-8xl px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <div className="lg:max-w-xl">
            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-card/60 px-3 py-1 font-mono text-xs text-muted-foreground">
              <span className="size-1.5 rounded-full bg-primary animate-glow-pulse" />
              Free · Open Source · GPL-3
            </span>
            <h1 className="mt-6 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
              GraphQL API for{" "}
              <span className="text-primary">every WordPress site</span>
            </h1>
            <p className="mt-6 max-w-lg text-base leading-relaxed text-muted-foreground sm:text-lg">
              WPGraphQL is a free, open-source WordPress plugin that
              provides an extendable GraphQL schema and API for any
              WordPress site.
            </p>
            <div className="mt-10 flex flex-wrap items-center gap-4">
              <Button asChild size="lg">
                <a
                  href="https://wordpress.org/plugins/wp-graphql"
                  target="_blank"
                  rel="noreferrer"
                >
                  Download the Plugin
                </a>
              </Button>
              <Button asChild variant="secondary" size="lg">
                <Link href="/docs/introduction">Read the Docs</Link>
              </Button>
            </div>
          </div>

          <div className="relative">
            <MockIDE query={heroQuery} response={heroResponse} />
            <div
              aria-hidden="true"
              className="pointer-events-none absolute right-0 top-0 -z-10 h-[55vh] w-[55vh] -translate-y-1/3 translate-x-1/3 rounded-full bg-primary/15 blur-3xl"
            />
          </div>
        </div>
      </div>
    </section>
  )
}
