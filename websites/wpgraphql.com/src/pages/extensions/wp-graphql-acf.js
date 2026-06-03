import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import SiteLayout from "../../components/Site/SiteLayout"
import Link from "next/link"
import { Disclosure } from "@headlessui/react"
import {
  ChevronUpIcon,
  ArrowDownTrayIcon,
  TableCellsIcon,
} from "@heroicons/react/20/solid"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { WPGraphQLACFLogoMark } from "@/components/ACF/WPGraphQLACFLogo"
import {
  SectionHeading as BaseSectionHeading,
  VisualPanel,
} from "@/components/extensions/SectionHeading"

const WP_ORG_URL = "https://wordpress.org/plugins/wpgraphql-acf/"
// Repo root — issues / security policy live at the monorepo level.
const GITHUB_URL = "https://github.com/wp-graphql/wp-graphql"
// The ACF plugin's own directory in the monorepo — the "source" / "View on GitHub" target.
const GITHUB_ACF_URL =
  "https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-acf"
const DOCS_URL = "https://acf.wpgraphql.com"

// ACF sections use the field-group-table glyph in the eyebrow.
function SectionHeading(props) {
  return <BaseSectionHeading icon={TableCellsIcon} {...props} />
}

/* ── Emerald mini-mocks — ACF's own UI vocabulary (field-group table + a
   "Show in GraphQL" toggle), themed via the .ide-* classes. ───────────── */
function FieldGroupMock() {
  const rows = [
    { name: "heroHeading", type: "Text", active: true },
    { name: "heroImage", type: "Image" },
    { name: "ctaButtons", type: "Repeater" },
    { name: "layout", type: "Flexible Content" },
  ]
  return (
    <VisualPanel>
      <div className="ide-muted mb-3 flex items-center justify-between text-[0.6rem] uppercase tracking-widest">
        <span>Field Group · Page Fields</span>
        <span className="text-primary">Show in GraphQL ✓</span>
      </div>
      <div className="ide-muted grid grid-cols-[1fr_auto] gap-2 border-b border-border pb-1.5 text-[0.6rem] uppercase tracking-widest">
        <span>Field name</span>
        <span>Type</span>
      </div>
      <div className="mt-1 space-y-0.5">
        {rows.map((r) => (
          <div
            key={r.name}
            className={`grid grid-cols-[1fr_auto] items-center gap-2 rounded px-2 py-1.5 ${
              r.active
                ? "border-l-2 border-primary bg-primary/[0.08]"
                : "border-l-2 border-transparent"
            }`}
          >
            <span className={r.active ? "text-primary" : "ide-text"}>
              {r.name}
            </span>
            <span className="ide-muted">{r.type}</span>
          </div>
        ))}
      </div>
    </VisualPanel>
  )
}

function AcfQueryMock() {
  return (
    <VisualPanel>
      <div>
        <span className="ide-tok-kw">query</span>{" "}
        <span className="ide-tok-key">PageFields</span>{" "}
        <span className="ide-tok-punc">{"{"}</span>
      </div>
      <div className="pl-4">
        <span className="ide-tok-key">page</span>
        <span className="ide-tok-punc">(</span>
        <span className="ide-tok-key">id</span>
        <span className="ide-tok-punc">: </span>
        <span className="ide-tok-str">&quot;/&quot;</span>
        <span className="ide-tok-punc">, </span>
        <span className="ide-tok-key">idType</span>
        <span className="ide-tok-punc">: </span>
        <span className="ide-tok-num">URI</span>
        <span className="ide-tok-punc">) {"{"}</span>
      </div>
      <div className="pl-8">
        <span className="ide-tok-key">pageFields</span>{" "}
        <span className="ide-tok-punc">{"{"}</span>
      </div>
      <div className="pl-12">
        <span className="ide-tok-key">heroHeading</span>
      </div>
      <div className="pl-12">
        <span className="ide-tok-key">ctaButtons</span>{" "}
        <span className="ide-tok-punc">{"{ label url }"}</span>
      </div>
      <div className="pl-8">
        <span className="ide-tok-punc">{"}"}</span>
      </div>
      <div className="pl-4">
        <span className="ide-tok-punc">{"}"}</span>
      </div>
      <div>
        <span className="ide-tok-punc">{"}"}</span>
      </div>
    </VisualPanel>
  )
}

function Hero() {
  return (
    <section className="relative overflow-hidden border-b border-border">
      {/* Emerald radial glow — matches the ACF brand guide hero treatment */}
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
              WPGraphQL for ACF
            </p>
            <h1 className="mt-3 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
              Your ACF fields,
              <br />
              <span className="text-primary">automatically in GraphQL.</span>
            </h1>
            <p className="mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
              Expose your Advanced Custom Fields groups and fields to the
              WPGraphQL schema with a single &ldquo;Show in GraphQL&rdquo;
              toggle — no resolvers, no glue code.
            </p>
            <div className="mt-10 flex flex-wrap items-center gap-4">
              <Button asChild size="lg">
                <a href={WP_ORG_URL} target="_blank" rel="noreferrer">
                  <ArrowDownTrayIcon className="size-4" aria-hidden="true" />
                  Download from WordPress.org
                </a>
              </Button>
              <Button asChild size="lg" variant="outline">
                <a href={GITHUB_ACF_URL} target="_blank" rel="noreferrer">
                  View on GitHub
                </a>
              </Button>
            </div>
            <p className="mt-4 font-mono text-xs text-muted-foreground">
              Free &amp; open source · Requires WPGraphQL + ACF
            </p>
          </div>
          {/* The ACF logo mark is the hero element — large, with an emerald glow. */}
          <div className="flex justify-center lg:justify-end">
            <WPGraphQLACFLogoMark
              size={340}
              showGlow
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
      title: "Flip the 'Show in GraphQL' toggle",
      content:
        "Build your Field Groups and Fields exactly as you do today — in the ACF UI, in PHP, or with local JSON. Each group and field gets a 'Show in GraphQL' setting; turn it on and the plugin maps it into the schema for you.",
      visual: <FieldGroupMock />,
    },
    {
      title: "Query your fields with GraphQL",
      content:
        "Exposed field groups appear on the types they're assigned to — posts, pages, taxonomies, users, options pages and more — ready to query like any other WPGraphQL field, with the correct GraphQL types inferred automatically.",
      visual: <AcfQueryMock />,
    },
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <SectionHeading
        eyebrow="How it works"
        lead="Flip a toggle."
        accent="Query your fields."
        intro="No custom resolvers, no register_graphql_field boilerplate — just your fields, in the schema."
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

function Why() {
  const features = [
    {
      title: "Skip the glue code",
      content:
        "Exposing fields to the schema by hand is tedious and error-prone. WPGraphQL for ACF maps your field groups automatically, so you spend time building features instead of wiring up resolvers.",
    },
    {
      title: "Nearly every field type",
      content:
        "Most ACF Free and Pro field types are supported out of the box — including Repeater, Flexible Content, Group, and Gallery — plus most field types from ACF Extended (Free and Pro).",
    },
    {
      title: "Fast, and client-agnostic",
      content:
        "Built on WPGraphQL, it brings the same query performance to your ACF data — and works with Apollo, Relay, urql, Faust.js, or any GraphQL client you already use.",
    },
  ]

  return (
    <section className="border-y border-border bg-card/40">
      <div className="mx-auto max-w-7xl px-6 py-20">
        <SectionHeading
          eyebrow="Why WPGraphQL for ACF"
          lead="Skip the glue code."
          accent="Ship content faster."
        />
        <div className="mt-12 grid gap-5 md:grid-cols-3">
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

function SupportedFields() {
  const fields = [
    "Text",
    "Text Area",
    "Number",
    "Range",
    "Email",
    "URL",
    "Password",
    "Image",
    "File",
    "WYSIWYG",
    "oEmbed",
    "Select",
    "Checkbox",
    "Radio Button",
    "Button Group",
    "True / False",
    "Link",
    "Post Object",
    "Page Link",
    "Relationship",
    "Taxonomy",
    "User",
    "Google Map",
    "Date Picker",
    "Date/Time Picker",
    "Time Picker",
    "Color Picker",
    "Group",
    "Repeater",
    "Flexible Content",
    "Gallery",
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <SectionHeading
        eyebrow="Supported fields"
        lead="Nearly every ACF field,"
        accent="out of the box."
      />
      <div className="mx-auto mt-12 grid max-w-5xl grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        {fields.map((field) => (
          <div
            key={field}
            className="rounded-lg border border-border bg-card px-4 py-3 text-center text-sm font-medium text-foreground transition-colors hover:border-primary/40"
          >
            {field}
          </div>
        ))}
      </div>
      <p className="mx-auto mt-10 max-w-3xl text-center text-sm text-muted-foreground">
        Support extends to most field types from ACF Extended (Free &amp; Pro)
        too. A few non-data fields — such as Accordion, Tab, and Message —
        aren&apos;t represented in the schema, and new field types can be added
        with the{" "}
        <code className="font-mono text-emerald-300">
          register_graphql_acf_field_type
        </code>{" "}
        API.
      </p>
    </section>
  )
}

function OpenSource() {
  return (
    <section className="border-y border-border bg-card/40">
      <div className="mx-auto max-w-3xl px-6 py-20 text-center">
        <SectionHeading
          eyebrow="Pricing & support"
          lead="Free and open source."
          accent="Built in the open."
        />
        <p className="mt-6 text-base text-muted-foreground sm:text-lg">
          WPGraphQL for ACF is a{" "}
          <strong className="text-foreground">FREE</strong> open-source plugin.
          The code is on{" "}
          <a
            className="text-primary hover:underline"
            href={GITHUB_ACF_URL}
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
      </div>
    </section>
  )
}

function Faq() {
  const questions = [
    {
      question: "Do I need WPGraphQL and ACF both active?",
      answer:
        "Yes. This is a 'bridge' plugin that brings ACF data to WPGraphQL, so both WPGraphQL and Advanced Custom Fields need to be installed and active in your WordPress site.",
    },
    {
      question: "Does it work with ACF Free, Pro, and Extended?",
      answer:
        "Yes to all three. It works great with ACF Free and Pro (including Pro-only fields like Flexible Content, Repeater, and Options Pages), and supports most field types from ACF Extended (Free and Pro) as well.",
    },
    {
      question: "Does it work with field groups registered in PHP or JSON?",
      answer:
        "Yes. You can register field groups via the Admin UI, PHP, or local JSON. When registering in PHP or JSON, set 'show_in_graphql' to true to expose the group and its fields to the schema.",
    },
    {
      question: "Can I filter or sort queries by ACF fields?",
      answer:
        "Not at this time. Meta queries are often very expensive to execute, so filtering/sorting by ACF fields isn't supported out of the box — but we're exploring ways to support it without the performance penalty.",
    },
    {
      question: "Are GraphQL mutations supported?",
      answer:
        "Not yet. We're waiting for the GraphQL '@oneOf' directive to land in the spec before adding mutation support for ACF fields.",
    },
    {
      question: "Do I have to use Faust.js?",
      answer:
        "No. While wpgraphql.com and acf.wpgraphql.com are built with Faust.js and Next.js, you can use WPGraphQL for ACF with any GraphQL client — Apollo, Relay, urql, and more.",
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
          Add ACF to your
          <br />
          <span className="text-primary">GraphQL schema today.</span>
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-base text-muted-foreground sm:text-lg">
          Install from WordPress.org and expose your Advanced Custom Fields in
          minutes.
        </p>
        <div className="mt-10 flex flex-wrap justify-center gap-4">
          <Button asChild size="lg">
            <a href={WP_ORG_URL} target="_blank" rel="noreferrer">
              <ArrowDownTrayIcon className="size-4" aria-hidden="true" />
              Download from WordPress.org
            </a>
          </Button>
          <Button asChild size="lg" variant="outline">
            <a href={GITHUB_ACF_URL} target="_blank" rel="noreferrer">
              View on GitHub
            </a>
          </Button>
        </div>
      </div>
    </section>
  )
}

function WpGraphQLAcf({ layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <SiteLayout>
        {/* Sibling-brand scope: emerald accent for the page body only.
            The shared SiteHeader/SiteFooter remain WPGraphQL-orange. */}
        <div className="theme-acf">
          <Hero />
          <HowItWorks />
          <Why />
          <SupportedFields />
          <OpenSource />
          <Faq />
          <FooterCta />
        </div>
      </SiteLayout>
    </LayoutProvider>
  )
}

export default WpGraphQLAcf

export async function getStaticProps() {
  const layoutData = await getLayoutData()
  return {
    props: { layoutData },
    revalidate: 30,
  }
}
