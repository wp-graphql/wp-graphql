import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import SiteLayout from "../../components/Site/SiteLayout"
import Link from "next/link"
import { Disclosure } from "@headlessui/react"
import {
  ChevronUpIcon,
  ArrowDownTrayIcon,
  CommandLineIcon,
} from "@heroicons/react/20/solid"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { WPGraphQLIDELogoMark } from "@/components/IDE/WPGraphQLIDELogo"
import {
  SectionHeading as BaseSectionHeading,
  VisualPanel,
} from "@/components/extensions/SectionHeading"
import Constellation from "@/components/extensions/Constellation"

const WP_ORG_URL = "https://wordpress.org/plugins/wpgraphql-ide/"
// Repo root — used for issues / security policy (those live at the monorepo level).
const GITHUB_URL = "https://github.com/wp-graphql/wp-graphql"
// The IDE plugin's own directory in the monorepo — the "source" / "View on GitHub" target.
const GITHUB_IDE_URL =
  "https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-ide"
const DOCS_EXTENDING_URL =
  "https://github.com/wp-graphql/wp-graphql/blob/main/plugins/wp-graphql-ide/docs/extending-the-ide.md"

// IDE sections use the command-line glyph in the eyebrow.
function SectionHeading(props) {
  return <BaseSectionHeading icon={CommandLineIcon} {...props} />
}

function Hero() {
  return (
    <section className="relative overflow-hidden border-b border-border">
      {/* Violet radial glow — matches the IDE brand guide hero treatment */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 -z-10"
        style={{
          background: `
            radial-gradient(ellipse 900px 600px at 85% 0%, hsl(var(--primary) / 0.13) 0%, hsl(var(--primary) / 0.05) 40%, transparent 70%),
            radial-gradient(ellipse 500px 500px at 5% 85%, hsl(var(--primary) / 0.06) 0%, transparent 65%)
          `,
        }}
      />
      {/* Violet constellation field, faded toward the bottom edge. */}
      <Constellation
        variant={1}
        count={48}
        width={1440}
        height={720}
        opacity={0.75}
        intensity={1.6}
        className="-z-10 [mask-image:linear-gradient(to_bottom,black,transparent_85%)]"
      />
      <div className="mx-auto max-w-7xl px-6 py-20 lg:py-28">
        <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
          <div>
            <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
              WPGraphQL IDE
            </p>
            <h1 className="mt-3 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
              GraphQL IDE, reimagined.
              <br />
              <span className="text-primary">Native to WordPress.</span>
            </h1>
            <p className="mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
              Explore your schema, build and debug queries, and ship headless
              WordPress faster — a schema-aware GraphQL client that lives right
              in wp-admin, with no external tooling to set up.
            </p>
            <div className="mt-10 flex flex-wrap items-center gap-4">
              <Button asChild size="lg">
                <a href={WP_ORG_URL} target="_blank" rel="noreferrer">
                  <ArrowDownTrayIcon className="size-4" aria-hidden="true" />
                  Download from WordPress.org
                </a>
              </Button>
              <Button asChild size="lg" variant="outline">
                <a href={GITHUB_IDE_URL} target="_blank" rel="noreferrer">
                  View on GitHub
                </a>
              </Button>
            </div>
            <p className="mt-4 font-mono text-xs text-muted-foreground">
              Free &amp; open source · GPL-3.0 · Requires WPGraphQL
            </p>
          </div>
          {/* The IDE logo mark is the hero element — large, with a violet glow. */}
          <div className="flex justify-center lg:justify-end">
            <WPGraphQLIDELogoMark
              size={340}
              className="h-auto w-full max-w-[260px] sm:max-w-[320px] lg:max-w-[360px]"
              style={{
                filter:
                  "drop-shadow(0 0 56px hsl(var(--primary) / 0.45)) drop-shadow(0 0 18px hsl(var(--primary) / 0.35))",
              }}
            />
          </div>
        </div>
      </div>
    </section>
  )
}

function RenderModes() {
  const modes = [
    {
      title: "Dedicated page",
      content:
        "A full-screen workspace under GraphQL → GraphQL IDE — room to spread out for deep query-building sessions and schema exploration.",
    },
    {
      title: "Slide-up drawer",
      content:
        "Pull the IDE up from the admin bar on any wp-admin or front-end page to test a query without losing your place — no tab-switching, no context loss.",
    },
    {
      title: "Public endpoint mode",
      content:
        "Turn your GraphQL endpoint into a shareable, schema-aware explorer. Anonymous visitors get a read-only schema browser; signed-in admins get the full editor — ideal for onboarding front-end developers.",
    },
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <SectionHeading
        eyebrow="Fits your workflow"
        lead="Test queries anywhere."
        accent="No context switching."
        intro="Open it however the task calls for — a focused workspace, a quick check without leaving the page, or a schema explorer you can share with your whole team."
      />
      <div className="mt-12 grid gap-5 md:grid-cols-3">
        {modes.map((mode) => (
          <Card key={mode.title}>
            <CardHeader>
              <CardTitle className="text-headline font-bold tracking-tight">
                {mode.title}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-muted-foreground">{mode.content}</p>
            </CardContent>
          </Card>
        ))}
      </div>
    </section>
  )
}

/* ── Small presentational mocks — give the feature cards a splash of the
   IDE's own UI. They reuse the theme-aware .ide-* / .ide-tok-* classes from
   globals.css, so they adapt to dark/light automatically. ─────────────── */
function Kbd({ children }) {
  return (
    <kbd className="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[0.65rem] text-primary">
      {children}
    </kbd>
  )
}

function AutocompleteMock() {
  return (
    <VisualPanel>
      <div>
        <span className="ide-tok-key">posts</span>
        <span className="ide-tok-punc"> {"{"}</span>
      </div>
      <div className="pl-4">
        <span className="ide-tok-key">nodes</span>
        <span className="ide-tok-punc"> {"{"}</span>
      </div>
      <div className="flex items-center pl-8">
        <span className="ide-text">tit</span>
        <span className="ml-px inline-block h-3.5 w-0.5 animate-pulse bg-primary" />
      </div>
      <div className="ml-8 mt-1 w-48 overflow-hidden rounded-md border border-border bg-card shadow-elev-md">
        <div className="flex justify-between bg-primary/15 px-3 py-1.5 text-primary">
          <span>title</span>
          <span className="ide-muted">String</span>
        </div>
        <div className="flex justify-between px-3 py-1.5">
          <span className="ide-muted">date</span>
          <span className="ide-muted">String</span>
        </div>
        <div className="flex justify-between px-3 py-1.5">
          <span className="ide-muted">author</span>
          <span className="ide-muted">User</span>
        </div>
      </div>
    </VisualPanel>
  )
}

function PerfMock() {
  return (
    <VisualPanel>
      <div className="flex flex-wrap items-center gap-2">
        <span className="rounded bg-green-500/15 px-2 py-0.5 text-green-400">
          200 OK
        </span>
        <span className="ide-muted rounded bg-muted px-2 py-0.5">42 ms</span>
        <span className="ide-muted rounded bg-muted px-2 py-0.5">1.24 KB</span>
        <span className="rounded bg-primary/15 px-2 py-0.5 text-primary">
          5 resolvers
        </span>
      </div>
      <div className="mt-3 flex items-start gap-2 rounded-md border-l-2 border-amber-400 bg-amber-400/10 px-3 py-2 text-amber-300">
        <span aria-hidden="true">⚠</span>
        <span>
          N+1 detected · <span className="ide-muted">posts.author</span>{" "}
          resolved 5×
        </span>
      </div>
    </VisualPanel>
  )
}

function VariablesMock() {
  return (
    <VisualPanel>
      <div className="ide-muted mb-2 text-[0.6rem] uppercase tracking-widest">
        Variables
      </div>
      <div>
        <span className="ide-tok-punc">{"{"}</span>
      </div>
      <div className="pl-4">
        <span className="ide-tok-key">&quot;first&quot;</span>
        <span className="ide-tok-punc">: </span>
        <span className="ide-tok-num">5</span>
        <span className="ide-tok-punc">,</span>
      </div>
      <div className="pl-4">
        <span className="ide-tok-key">&quot;status&quot;</span>
        <span className="ide-tok-punc">: </span>
        <span className="ide-tok-str">&quot;PUBLISH&quot;</span>
      </div>
      <div>
        <span className="ide-tok-punc">{"}"}</span>
      </div>
    </VisualPanel>
  )
}

function FlowMock() {
  return (
    <VisualPanel>
      <div className="ide-muted flex items-center gap-1 border-b border-border pb-2">
        <span className="ide-text rounded-t border-t-2 border-primary bg-background px-2 py-1">
          GetPosts
        </span>
        <span className="px-2 py-1">GetPost</span>
        <span className="px-2 py-1">Menus</span>
        <span className="ml-auto">+3</span>
      </div>
      <div className="ide-muted mt-3 flex flex-wrap items-center gap-x-2 gap-y-1.5">
        <span>Run</span>
        <Kbd>⌘↵</Kbd>
        <span className="ml-2">Prettify</span>
        <Kbd>⌃⇧P</Kbd>
      </div>
    </VisualPanel>
  )
}

function EditorFeatures() {
  const features = [
    {
      title: "See your schema as you type",
      content:
        "Schema-aware autocomplete, hover-doc tooltips, and inline lint warnings show exactly what your schema supports — and Cmd-click any field or type to jump straight to its docs. No more guessing field names.",
      visual: <AutocompleteMock />,
    },
    {
      title: "Catch performance problems early",
      content:
        "Request tracing, resolver-count badges, and built-in N+1 detection — alongside status code, duration, and size, with responses you can read as JSON or a table — surface slow or over-fetching queries before they reach production.",
      visual: <PerfMock />,
    },
    {
      title: "Test queries as your app sends them",
      content:
        "Dedicated variables and headers editors, each with their own autocomplete, reproduce authenticated and personalized requests — so what you build in the IDE matches what your front-end ships.",
      visual: <VariablesMock />,
    },
    {
      title: "Built to keep you in flow",
      content:
        "Multi-tab editing with auto-save, full keyboard control, and per-user execution history let you move across many documents — and re-run or recover past work — without ever losing your place.",
      visual: <FlowMock />,
    },
  ]

  return (
    <section className="border-y border-border bg-card/40">
      <div className="mx-auto max-w-7xl px-6 py-20">
        <SectionHeading
          eyebrow="Built for headless development"
          lead="Build and debug queries."
          accent="Ship with confidence."
          intro="Every feature is in service of a tighter loop between writing a query and trusting the data it returns."
        />
        <div className="mt-12 grid gap-5 md:grid-cols-2">
          {features.map((feature) => (
            <Card key={feature.title}>
              <CardHeader>
                <CardTitle className="text-headline font-bold tracking-tight">
                  {feature.title}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-muted-foreground">{feature.content}</p>
                {feature.visual}
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  )
}

function SmartCacheIntegration() {
  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
        <div>
          <SectionHeading
            align="left"
            eyebrow="Better with Smart Cache"
            lead="Save and organize queries,"
            accent="share them with your team."
          />
          <p className="mt-5 text-base text-muted-foreground sm:text-lg">
            The IDE works as a standalone GraphQL client out of the box. Add{" "}
            <Link
              href="/extensions/wp-graphql-smart-cache"
              className="text-primary hover:underline"
            >
              WPGraphQL Smart Cache
            </Link>{" "}
            and it&apos;s detected automatically — no configuration — lighting
            up the saved-document features.
          </p>
          <ul className="mt-6 space-y-3 text-base text-muted-foreground">
            {[
              "Saved Queries panel with personal collections",
              "Share links for any saved document",
              "Per-document settings drawer (description, max-age, allow/deny)",
              "Documents stored in one canonical graphql_document primitive",
            ].map((item) => (
              <li key={item} className="flex items-start gap-3">
                <span
                  aria-hidden="true"
                  className="mt-2 size-1.5 flex-shrink-0 rounded-full bg-primary"
                />
                <span>{item}</span>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <div className="ide-bg ide-border ide-text overflow-hidden rounded-xl border shadow-elev-md">
            <div className="ide-muted flex items-center justify-between border-b border-border px-4 py-2.5 font-mono text-[0.6rem] uppercase tracking-widest">
              <span>Saved Queries</span>
              <span className="text-primary">Collections</span>
            </div>
            <ul className="space-y-1 p-3 font-mono text-xs">
              <li className="flex items-center justify-between rounded bg-primary/10 px-3 py-2">
                <span className="ide-text">▾ Homepage</span>
                <span className="ide-muted">3 queries</span>
              </li>
              <li className="flex items-center justify-between rounded px-3 py-2 pl-6">
                <span className="ide-text">GetPosts</span>
                <span className="text-primary">⇆ shared</span>
              </li>
              <li className="flex items-center justify-between rounded px-3 py-2 pl-6">
                <span className="ide-text">GetMenus</span>
                <span className="ide-muted">max-age 60s</span>
              </li>
              <li className="flex items-center justify-between rounded px-3 py-2">
                <span className="ide-text">▸ Marketing site</span>
                <span className="ide-muted">8 queries</span>
              </li>
            </ul>
          </div>
          <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
            Saved documents live in Smart Cache&apos;s{" "}
            <code className="font-mono text-violet-300">graphql_document</code>{" "}
            post type — one shared primitive for the whole WPGraphQL ecosystem.
            Without Smart Cache, the IDE simply hides the saved-document UI and
            keeps working as a fast, schema-aware client.
          </p>
        </div>
      </div>
    </section>
  )
}

function Extensible() {
  return (
    <section className="border-y border-border bg-card/40">
      <div className="mx-auto max-w-3xl px-6 py-20 text-center">
        <SectionHeading
          eyebrow="For developers extending WPGraphQL"
          lead="Build your own tooling"
          accent="right into the IDE."
        />
        <p className="mt-6 text-base text-muted-foreground sm:text-lg">
          If you ship a WPGraphQL extension, the IDE is a surface for your
          tooling too. A first-class extension API lets your plugin&apos;s
          types, fields, and insights show up right where developers write
          queries — turning the IDE into the home for your feature, not just a
          place queries happen to run.
        </p>
        <ul className="mx-auto mt-8 max-w-xl space-y-3 text-left text-base text-muted-foreground">
          {[
            <>
              Add your own{" "}
              <strong className="text-foreground">activity-bar panels</strong>{" "}
              and <strong className="text-foreground">status-bar items</strong>{" "}
              to put custom UI in front of every query author.
            </>,
            <>
              Store typed user and device settings with{" "}
              <code className="font-mono text-violet-300">
                registerPreference
              </code>
              , so your extension remembers state the way the IDE does.
            </>,
            <>
              Hook the execute lifecycle with{" "}
              <code className="font-mono text-violet-300">executeRequest</code>{" "}
              /{" "}
              <code className="font-mono text-violet-300">executeResponse</code>{" "}
              filters and the{" "}
              <code className="font-mono text-violet-300">
                wpgraphql-ide.afterExecute
              </code>{" "}
              action to inspect, augment, or react to every request.
            </>,
          ].map((item, i) => (
            <li key={i} className="flex items-start gap-3">
              <span
                aria-hidden="true"
                className="mt-2 size-1.5 flex-shrink-0 rounded-full bg-primary"
              />
              <span>{item}</span>
            </li>
          ))}
        </ul>
        <div className="mt-10">
          <Button asChild variant="outline">
            <a href={DOCS_EXTENDING_URL} target="_blank" rel="noreferrer">
              Read the extension docs
            </a>
          </Button>
        </div>
      </div>
    </section>
  )
}

function WhatsNew() {
  const items = [
    {
      title: "Rebuilt UI",
      content:
        "New interface on @wordpress/components, @wordpress/data, and CodeMirror 6. The legacy GraphiQL wrapper is gone.",
    },
    {
      title: "Smart Cache integration",
      content:
        "Saved documents now use Smart Cache's graphql_document post type — one canonical primitive for the ecosystem.",
    },
    {
      title: "Three render modes",
      content:
        "Dedicated admin page, slide-up drawer, and an opt-in public IDE at the GraphQL endpoint — each individually configurable.",
    },
    {
      title: "Full internationalization",
      content:
        "Every UI string passes through @wordpress/i18n under the wpgraphql-ide text domain.",
    },
    {
      title: "Auto-upgrade from 4.x",
      content:
        "Open tabs and query history saved by the legacy GraphiQL UI migrate forward on first 5.0 load.",
    },
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <SectionHeading
        eyebrow="What's new in 5.0"
        lead="Rebuilt from"
        accent="the ground up."
      />
      <div className="mt-12 grid gap-x-10 gap-y-8 md:grid-cols-2 lg:grid-cols-3">
        {items.map((item) => (
          <div key={item.title} className="border-l-2 border-primary/60 pl-5">
            <h3 className="text-base font-semibold text-foreground">
              {item.title}
            </h3>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
              {item.content}
            </p>
          </div>
        ))}
      </div>
    </section>
  )
}

function OpenSource() {
  return (
    <section className="mx-auto max-w-3xl px-6 py-20 text-center">
      <SectionHeading
        eyebrow="Pricing & support"
        lead="Free, open source,"
        accent="GPL-3.0 licensed."
      />
      <p className="mt-6 text-base text-muted-foreground sm:text-lg">
        WPGraphQL IDE is a <strong className="text-foreground">FREE</strong>{" "}
        open-source WordPress plugin. The code is on{" "}
        <a
          className="text-primary hover:underline"
          href={GITHUB_IDE_URL}
          rel="noreferrer"
          target="_blank"
        >
          GitHub
        </a>
        . Report bugs or request features through{" "}
        <a
          className="text-primary hover:underline"
          href={`${GITHUB_URL}/issues`}
          rel="noreferrer"
          target="_blank"
        >
          issues
        </a>
        . For general questions, visit the{" "}
        <Link href="/discord" className="text-primary hover:underline">
          WPGraphQL Discord
        </Link>
        .
      </p>
    </section>
  )
}

function Faq() {
  const questions = [
    {
      question: "How do I open the IDE?",
      answer:
        "Three entry points: a dedicated admin page under GraphQL → GraphQL IDE, a slide-up drawer triggered from the admin bar (on every wp-admin and front-end page), and an opt-in public endpoint mode that renders the IDE when you visit the GraphQL endpoint URL in a browser.",
    },
    {
      question: "Do I need WPGraphQL Smart Cache?",
      answer:
        "No — the IDE works as a standalone GraphQL client without it. Smart Cache is optional but unlocks the saved-document features: the Saved Queries panel, personal collections, share links, and the Document Settings drawer. Install it and the IDE detects it automatically; no configuration needed.",
    },
    {
      question: "How do I enable the public endpoint?",
      answer:
        "Under GraphQL → IDE Settings, check 'Public IDE at GraphQL endpoint'. Browser visits to the endpoint URL then render the IDE shell instead of returning JSON. API clients (curl, fetch with a JSON content type, GraphQL clients) keep getting JSON as before.",
    },
    {
      question: "What changed in 5.0?",
      answer:
        "5.0 rebuilds the UI on @wordpress/components and CodeMirror 6, moves saved-document storage onto Smart Cache's graphql_document post type, and ships full internationalization. Extension authors should consult UPGRADE-5.0.md (bundled with the plugin). Open tabs and query history from 4.x migrate forward automatically on first load.",
    },
    {
      question: "What are the major dependencies?",
      answer:
        "CodeMirror 6 (with cm6-graphql) for the editor surface, @wordpress/components and @wordpress/data for UI and state, @graphiql/toolkit for fragment merging, vaul for the slide-up drawer, and graphql-js for parsing.",
    },
    {
      question: "Where do I report bugs or request features?",
      answer:
        "Open an issue at github.com/wp-graphql/wp-graphql. For security issues, please follow the security policy instead of filing a public issue.",
    },
  ]

  return (
    <section className="mx-auto max-w-3xl px-6 py-20">
      <SectionHeading
        eyebrow="FAQ"
        lead="Frequently asked"
        accent="questions."
      />
      <div className="mt-12 flex flex-col gap-3">
        {questions.map((q) => (
          <Disclosure key={q.question}>
            {({ open }) => (
              <div className="overflow-hidden rounded-lg border border-border bg-card">
                <Disclosure.Button className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-base font-medium text-foreground transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                  <span>{q.question}</span>
                  <ChevronUpIcon
                    className={`size-5 shrink-0 text-muted-foreground transition-transform ${open ? "rotate-180" : ""}`}
                  />
                </Disclosure.Button>
                <Disclosure.Panel className="border-t border-border px-5 pb-5 pt-4 text-sm leading-relaxed text-muted-foreground">
                  {q.answer}
                </Disclosure.Panel>
              </div>
            )}
          </Disclosure>
        ))}
      </div>
    </section>
  )
}

function FooterCta() {
  return (
    <section className="border-t border-border">
      <div className="mx-auto max-w-7xl px-6 py-20 text-center">
        <h2 className="text-display-sm font-extrabold tracking-tight text-foreground sm:text-display-md">
          Try WPGraphQL IDE
          <br />
          <span className="text-primary">today.</span>
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-base text-muted-foreground sm:text-lg">
          Install from WordPress.org and open a schema-aware editor in wp-admin
          in seconds.
        </p>
        <div className="mt-10 flex flex-wrap justify-center gap-4">
          <Button asChild size="lg">
            <a href={WP_ORG_URL} target="_blank" rel="noreferrer">
              <ArrowDownTrayIcon className="size-4" aria-hidden="true" />
              Download from WordPress.org
            </a>
          </Button>
          <Button asChild size="lg" variant="outline">
            <a href={GITHUB_IDE_URL} target="_blank" rel="noreferrer">
              View on GitHub
            </a>
          </Button>
        </div>
      </div>
    </section>
  )
}

function WpGraphQLIde({ layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <SiteLayout>
        {/* Sibling-brand scope: violet accent for the page body only.
            The shared SiteHeader/SiteFooter remain WPGraphQL-orange. */}
        <div className="theme-ide">
          <Hero />
          <RenderModes />
          <EditorFeatures />
          <SmartCacheIntegration />
          <Extensible />
          <WhatsNew />
          <OpenSource />
          <Faq />
          <FooterCta />
        </div>
      </SiteLayout>
    </LayoutProvider>
  )
}

export default WpGraphQLIde

export async function getStaticProps() {
  const layoutData = await getLayoutData()
  return {
    props: { layoutData },
    revalidate: 30,
  }
}
