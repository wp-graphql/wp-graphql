import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import SiteLayout from "../../components/Site/SiteLayout"
import Link from "next/link"
import { Disclosure } from "@headlessui/react"
import {
  ChevronUpIcon,
  ArrowDownTrayIcon,
  BoltIcon,
} from "@heroicons/react/20/solid"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { WPGraphQLSmartCacheLogoMark } from "@/components/SmartCache/WPGraphQLSmartCacheLogo"
import {
  SectionHeading as BaseSectionHeading,
  VisualPanel,
} from "@/components/extensions/SectionHeading"

const WP_ORG_URL = "https://wordpress.org/plugins/wpgraphql-smart-cache/"
// Repo root — issues / security policy live at the monorepo level.
const GITHUB_URL = "https://github.com/wp-graphql/wp-graphql"
// The Smart Cache plugin's own directory in the monorepo — the "source" target.
const GITHUB_SC_URL =
  "https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-smart-cache"
const DOCS_URL =
  "https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-smart-cache#readme"

// Smart Cache sections use the bolt glyph in the eyebrow.
function SectionHeading(props) {
  return <BaseSectionHeading icon={BoltIcon} {...props} />
}

/* ── Rose mini-mocks — Smart Cache's own vocabulary (cache-key headers +
   tag-based purge), themed via the .ide-* classes. ─────────────────────── */
function CacheHitMock() {
  return (
    <VisualPanel>
      <div className="flex flex-wrap items-center gap-2">
        <span className="rounded bg-primary/15 px-2 py-0.5 text-primary">
          CACHE · HIT
        </span>
        <span className="ide-muted rounded bg-muted px-2 py-0.5">4 ms</span>
        <span className="ide-muted rounded bg-muted px-2 py-0.5">edge</span>
      </div>
      <div className="ide-muted mt-3 text-[0.7rem]">
        <span className="ide-muted">X-GraphQL-Keys:</span>{" "}
        <span className="text-primary">post:42</span>{" "}
        <span className="text-primary">author:9</span>{" "}
        <span className="text-primary">list:post</span>
      </div>
      <div className="mt-3">
        <div className="ide-muted mb-1 flex items-center justify-between text-[0.6rem] uppercase tracking-widest">
          <span>TTL</span>
          <span>03:41 left</span>
        </div>
        <div className="h-1 overflow-hidden rounded-full bg-muted">
          <div
            className="h-full rounded-full bg-gradient-to-r from-primary/60 to-primary"
            style={{ width: "62%" }}
          />
        </div>
      </div>
    </VisualPanel>
  )
}

function PurgeMock() {
  return (
    <VisualPanel>
      <div className="flex items-center gap-2">
        <span className="ide-text">Post</span>
        <span className="text-primary">#42</span>
        <span className="ide-muted">updated</span>
      </div>
      <div className="mt-2 flex items-start gap-2 border-l-2 border-primary pl-3">
        <div>
          <div className="ide-muted text-[0.6rem] uppercase tracking-widest">
            Purge tags
          </div>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {["post:42", "author:9", "list:post"].map((t) => (
              <span
                key={t}
                className="rounded bg-primary/10 px-1.5 py-0.5 text-primary"
              >
                {t}
              </span>
            ))}
          </div>
        </div>
      </div>
      <div className="ide-muted mt-3 text-[0.7rem]">
        → <span className="text-primary">12</span> cached responses invalidated
        everywhere
      </div>
    </VisualPanel>
  )
}

function Hero() {
  return (
    <section className="relative overflow-hidden border-b border-border">
      {/* Rose radial glow — matches the Smart Cache brand guide hero treatment */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 -z-10"
        style={{
          background: `
            radial-gradient(ellipse 900px 600px at 85% 0%, hsl(var(--primary) / 0.12) 0%, hsl(var(--primary) / 0.04) 40%, transparent 70%),
            radial-gradient(ellipse 500px 500px at 5% 85%, hsl(var(--primary) / 0.05) 0%, transparent 65%)
          `,
        }}
      />
      <div className="mx-auto max-w-7xl px-6 py-20 lg:py-28">
        <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
          <div>
            <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
              WPGraphQL Smart Cache
            </p>
            <h1 className="mt-3 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
              Fast API responses.
              <br />
              <span className="text-primary">Always accurate.</span>
            </h1>
            <p className="mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
              Cache WPGraphQL queries aggressively, then invalidate them
              precisely — so your API stays fast without ever serving stale
              data.
            </p>
            <div className="mt-10 flex flex-wrap items-center gap-4">
              <Button asChild size="lg">
                <a href={WP_ORG_URL} target="_blank" rel="noreferrer">
                  <ArrowDownTrayIcon className="size-4" aria-hidden="true" />
                  Download from WordPress.org
                </a>
              </Button>
              <Button asChild size="lg" variant="outline">
                <a href={GITHUB_SC_URL} target="_blank" rel="noreferrer">
                  View on GitHub
                </a>
              </Button>
            </div>
            <p className="mt-4 font-mono text-xs text-muted-foreground">
              Free &amp; open source · GPL-2.0 · Requires WPGraphQL
            </p>
          </div>
          {/* The Smart Cache logo mark is the hero element — large, with a rose glow + radar sweep. */}
          <div className="flex justify-center lg:justify-end">
            <WPGraphQLSmartCacheLogoMark
              size={340}
              showGlow
              showSweep
              className="h-auto w-full max-w-[260px] sm:max-w-[320px] lg:max-w-[360px]"
            />
          </div>
        </div>
      </div>
    </section>
  )
}

function HowItWorks() {
  const steps = [
    {
      title: "Cache aggressively",
      content:
        "Every response is tagged with the IDs of the nodes and lists it touched (via WPGraphQL's Query Analyzer). GET requests can then be cached at your host's network/edge layer and served in milliseconds.",
      visual: <CacheHitMock />,
    },
    {
      title: "Invalidate precisely",
      content:
        "When content changes, Smart Cache purges only the cached responses tagged with the affected nodes — no manual TTL guessing, no blanket flushes, no stale data. On-demand purges are available too, via graphql_purge.",
      visual: <PurgeMock />,
    },
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <SectionHeading
        eyebrow="How it works"
        lead="Cache aggressively."
        accent="Invalidate precisely."
        intro="Tag-aware caching means responses are cached the moment they're computed, and evicted the instant the data behind them changes."
      />
      <div className="mt-12 grid gap-5 md:grid-cols-2">
        {steps.map((step) => (
          <Card key={step.title}>
            <CardHeader>
              <CardTitle className="text-headline font-bold tracking-tight">
                {step.title}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-muted-foreground">{step.content}</p>
              {step.visual}
            </CardContent>
          </Card>
        ))}
      </div>
    </section>
  )
}

function Features() {
  const features = [
    {
      title: "Network Cache",
      content:
        "Serve GET queries straight from your host's network/edge cache for full-page-cache-fast API responses. Available on supported hosts, with a hosting guide for others.",
    },
    {
      title: "Object Cache",
      content:
        "A server-side object cache for environments without network caching — fast repeat responses without re-running the full resolver chain on every request.",
    },
    {
      title: "Persisted Queries",
      content:
        "Store queries as documents and send them by ID: smaller payloads, cacheable GET requests, and a tighter security surface with allow / deny lists.",
    },
    {
      title: "Smart Invalidation",
      content:
        "Tag-based, automatic purging keeps caches correct. When a node changes, only the responses that referenced it are evicted — everywhere they were cached.",
    },
  ]

  return (
    <section className="border-y border-border bg-card/40">
      <div className="mx-auto max-w-7xl px-6 py-20">
        <SectionHeading
          eyebrow="What's inside"
          lead="Everything you need to"
          accent="cache with confidence."
        />
        <div className="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
          {features.map((feature) => (
            <Card key={feature.title}>
              <CardHeader>
                <CardTitle className="text-headline font-bold tracking-tight">
                  {feature.title}
                </CardTitle>
              </CardHeader>
              <CardContent>
                <p className="text-muted-foreground">{feature.content}</p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  )
}

function OpenSource() {
  return (
    <section className="mx-auto max-w-3xl px-6 py-20 text-center">
      <SectionHeading
        eyebrow="Pricing & support"
        lead="Free and open source."
        accent="Built in the open."
      />
      <p className="mt-6 text-base text-muted-foreground sm:text-lg">
        WPGraphQL Smart Cache is a{" "}
        <strong className="text-foreground">FREE</strong> open-source plugin.
        The code is on{" "}
        <a
          className="text-primary hover:underline"
          href={GITHUB_SC_URL}
          rel="noreferrer"
          target="_blank"
        >
          GitHub
        </a>
        ; report bugs or request features through{" "}
        <a
          className="text-primary hover:underline"
          href={`${GITHUB_URL}/issues`}
          rel="noreferrer"
          target="_blank"
        >
          issues
        </a>
        , and visit the{" "}
        <Link href="/discord" className="text-primary hover:underline">
          WPGraphQL Discord
        </Link>{" "}
        for general questions.
      </p>
      <div className="mt-10">
        <Button asChild variant="outline">
          <a href={DOCS_URL} target="_blank" rel="noreferrer">
            Read the docs
          </a>
        </Button>
      </div>
    </section>
  )
}

function Faq() {
  const questions = [
    {
      question: "Do I need a special host to use it?",
      answer:
        "No — Smart Cache works on any WordPress host. The Network Cache feature needs specific support from the host's network/edge cache layer to work, so it's available on supported hosts; the Object Cache and Persisted Queries features work anywhere.",
    },
    {
      question: "How is cache invalidation handled?",
      answer:
        "Automatically and by tag. WPGraphQL's Query Analyzer tags every response with the IDs of the nodes and lists it returned. When content changes, Smart Cache purges only the responses carrying the affected tags — so you never serve stale data and never over-purge.",
    },
    {
      question: "What are persisted queries?",
      answer:
        "Instead of sending the full query string on every request, the query is stored as a document and referenced by ID. That means smaller requests, cacheable GET calls, and the option to allow only known queries for a tighter security surface.",
    },
    {
      question: "Does it work with my GraphQL client?",
      answer:
        "Yes. Smart Cache works with any GraphQL client. To benefit most from Network Caching, configure your client to send cacheable queries as GET requests; mutations and authenticated requests bypass the cache automatically.",
    },
    {
      question: "How much does it cost?",
      answer:
        "It's free and open source under GPL-2.0. Report bugs and request features on GitHub, and ask questions in the WPGraphQL Discord.",
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
          Make your API fast
          <br />
          <span className="text-primary">without going stale.</span>
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-base text-muted-foreground sm:text-lg">
          Install from WordPress.org and start caching WPGraphQL queries with
          automatic, tag-based invalidation.
        </p>
        <div className="mt-10 flex flex-wrap justify-center gap-4">
          <Button asChild size="lg">
            <a href={WP_ORG_URL} target="_blank" rel="noreferrer">
              <ArrowDownTrayIcon className="size-4" aria-hidden="true" />
              Download from WordPress.org
            </a>
          </Button>
          <Button asChild size="lg" variant="outline">
            <a href={GITHUB_SC_URL} target="_blank" rel="noreferrer">
              View on GitHub
            </a>
          </Button>
        </div>
      </div>
    </section>
  )
}

function WpGraphQLSmartCache({ layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <SiteLayout>
        {/* Sibling-brand scope: rose accent for the page body only.
            The shared SiteHeader/SiteFooter remain WPGraphQL-orange. */}
        <div className="theme-smart-cache">
          <Hero />
          <HowItWorks />
          <Features />
          <OpenSource />
          <Faq />
          <FooterCta />
        </div>
      </SiteLayout>
    </LayoutProvider>
  )
}

export default WpGraphQLSmartCache

export async function getStaticProps() {
  const layoutData = await getLayoutData()
  return {
    props: { layoutData },
    revalidate: 30,
  }
}
