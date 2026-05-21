import { getLayoutData, LayoutProvider } from "lib/wpgraphql-client"
import "lib/wpgraphql-client-config"
import SiteLayout from "../components/Site/SiteLayout"
import Image from "next/image"
import Link from "next/link"
import { Disclosure } from "@headlessui/react"
import { ChevronUpIcon, ArrowDownTrayIcon } from "@heroicons/react/20/solid"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"

function AcfHero() {
  return (
    <section className="relative overflow-hidden border-b border-border">
      <div
        aria-hidden="true"
        className="pointer-events-none absolute left-1/2 top-0 -z-10 h-[55vh] w-[55vh] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/15 blur-3xl"
      />
      <div className="mx-auto max-w-7xl px-6 py-20 lg:py-28">
        <div className="grid gap-12 lg:grid-cols-2 lg:items-center">
          <div>
            <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
              WPGraphQL for ACF
            </p>
            <h1 className="mt-3 text-display-md font-extrabold tracking-tight text-foreground sm:text-display-lg">
              Interact with your{" "}
              <span className="text-primary">Advanced Custom Fields</span>{" "}
              data using GraphQL queries
            </h1>
            <p className="mt-5 max-w-xl text-base text-muted-foreground sm:text-lg">
              Automatically expose your ACF Field Groups and Fields to the
              WPGraphQL Schema — no extra glue code.
            </p>
            <div className="mt-10">
              <Button asChild size="lg">
                <a
                  href="https://github.com/wp-graphql/wp-graphql-acf"
                  target="_blank"
                  rel="noreferrer"
                >
                  <ArrowDownTrayIcon className="size-4" aria-hidden="true" />
                  Download the Plugin
                </a>
              </Button>
            </div>
          </div>
          <div className="overflow-hidden rounded-xl border border-border bg-card shadow-elev-lg">
            <iframe
              src="https://www.youtube.com/embed/rIg4MHc8elg"
              title="WPGraphQL for Advanced Custom Fields"
              allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
              frameBorder="0"
              allowFullScreen
              className="block aspect-video w-full"
            />
          </div>
        </div>
      </div>
    </section>
  )
}

function HowItWorks() {
  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <div className="mx-auto max-w-3xl text-center">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          How it works
        </p>
        <h2 className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          ACF fields, exposed automatically
        </h2>
      </div>

      <div className="mt-14 grid gap-12 lg:grid-cols-2 lg:items-center">
        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-elev-md">
          <Image
            src="/images/acf-fields.jpeg"
            alt="Screenshot of Advanced Custom Fields field group user interface"
            width={710}
            height={628}
            className="block h-auto w-full"
          />
        </div>
        <div>
          <h3 className="text-display-sm font-bold tracking-tight text-foreground">
            Create your ACF Fields
          </h3>
          <p className="mt-4 text-base text-muted-foreground sm:text-lg">
            Create your ACF Field Groups and Fields the way you normally would
            — using the ACF UI, registering fields with PHP, or using ACF
            local-json. Each field group and field can be configured to
            &quot;Show in GraphQL&quot;.
          </p>
        </div>
      </div>

      <div className="mt-14 grid gap-12 lg:grid-cols-2 lg:items-center">
        <div className="lg:order-2 overflow-hidden rounded-xl border border-border bg-card shadow-elev-md">
          <Image
            src="/images/acf-query-fields.png"
            alt="Querying ACF fields with GraphiQL"
            width={1738}
            height={832}
            className="block h-auto w-full"
          />
        </div>
        <div className="lg:order-1 lg:text-right">
          <h3 className="text-display-sm font-bold tracking-tight text-foreground">
            Query with GraphQL
          </h3>
          <p className="mt-4 text-base text-muted-foreground sm:text-lg">
            Once your field groups have been configured to &quot;Show in
            GraphQL&quot;, they&apos;re available in the GraphQL Schema and
            ready for querying.
          </p>
        </div>
      </div>
    </section>
  )
}

function SupportedFields() {
  const fields = [
    "Text", "Text Area", "Number", "Range", "Email", "URL", "Password",
    "Image", "File", "WYSIWYG", "oEmbed", "Select", "Checkbox", "Radio Button",
    "Button Group", "True False", "Link", "Post Object", "Page Link",
    "Relationship", "Taxonomy", "User", "Google Map", "Date Picker",
    "Date/Time Picker", "Time Picker", "Color Picker", "Group", "Repeater",
    "Flex Field", "Gallery",
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <div className="mx-auto max-w-3xl text-center">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          Supported Fields
        </p>
        <h2 id="supported-fields" className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          Nearly every ACF field — out of the box
        </h2>
      </div>
      <div className="mx-auto mt-12 grid max-w-5xl grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        {fields.map((field) => (
          <div
            key={field}
            className="rounded-lg border border-border bg-card px-4 py-3 text-center text-sm font-medium text-foreground"
          >
            {field}
          </div>
        ))}
      </div>
      <p className="mx-auto mt-10 max-w-3xl text-center text-sm text-muted-foreground">
        WPGraphQL for Advanced Custom Fields supports nearly all of the ACF
        (free &amp; pro) fields. Some fields, such as Accordion and Tab, are
        not data fields and are not supported. The Clone field needs more
        assessment. Fields from 3rd-party extensions are not supported out
        of the box.
      </p>
    </section>
  )
}

function Why() {
  const features = [
    {
      title: "Time",
      content:
        "WPGraphQL is highly extendable, but exposing fields to the Schema can be time-consuming. This plugin saves you heaps of it.",
    },
    {
      title: "Performance",
      content:
        "WPGraphQL is one of the fastest ways to query data in WordPress, and we bring that performance to ACF data too.",
    },
    {
      title: "Support",
      content:
        "Receive the same great community support as the core WPGraphQL plugin through Github and Discord.",
    },
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <div className="mx-auto max-w-3xl text-center">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          Why WPGraphQL for ACF?
        </p>
        <h2 id="why-wpgraphql-for-acf" className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          Built for teams shipping content
        </h2>
      </div>
      <div className="mt-12 grid gap-5 md:grid-cols-3">
        {features.map((feature) => (
          <Card key={feature.title}>
            <CardHeader>
              <CardTitle className="text-display-sm font-bold tracking-tight">
                {feature.title}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-muted-foreground">{feature.content}</p>
            </CardContent>
          </Card>
        ))}
      </div>
    </section>
  )
}

function WorksWithJS() {
  const frameworks = [
    { name: "React",   logo: "/logos/logo-react.png" },
    { name: "Vue",     logo: "/logos/logo-vue.png" },
    { name: "Next.js", logo: "/logos/logo-nextjs.png" },
    { name: "Gatsby",  logo: "/logos/logo-gatsby.png" },
    { name: "Ember",   logo: "/logos/logo-ember.png" },
    { name: "Angular", logo: "/logos/logo-angular.png" },
  ]

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <div className="mx-auto max-w-3xl text-center">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          Framework Agnostic
        </p>
        <h2 id="frameworks" className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          Works with popular JavaScript frameworks
        </h2>
      </div>
      <div className="mx-auto mt-14 grid max-w-5xl grid-cols-3 gap-4 md:grid-cols-6">
        {frameworks.map((fw) => (
          <div
            key={fw.name}
            className="flex flex-col items-center gap-2 rounded-xl border border-border bg-card p-5 transition-all hover:border-primary/40 hover:shadow-glow-sm"
          >
            <Image src={fw.logo} alt={`${fw.name} logo`} width={48} height={48} className="h-12 w-auto" />
            <span className="font-mono text-[0.65rem] uppercase tracking-widest text-muted-foreground">
              {fw.name}
            </span>
          </div>
        ))}
      </div>
    </section>
  )
}

function Pricing() {
  return (
    <section className="mx-auto max-w-3xl px-6 py-20 text-center">
      <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
        Pricing &amp; Support
      </p>
      <h2 id="pricing-support" className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
        Free, open source, MIT licensed
      </h2>
      <p className="mt-6 text-base text-muted-foreground sm:text-lg">
        WPGraphQL for Advanced Custom Fields is a <strong className="text-foreground">FREE</strong>{" "}
        open-source WordPress plugin. The code is available on{" "}
        <a className="text-primary hover:underline" href="https://github.com/wp-graphql/wp-graphql-acf" rel="noreferrer" target="_blank">Github</a>.
        Support and feature requests are handled through{" "}
        <a className="text-primary hover:underline" href="https://github.com/wp-graphql/wp-graphql-acf/issues" rel="noreferrer" target="_blank">issues</a>.
        For general questions about the plugin, visit the{" "}
        <Link href="/discord" className="text-primary hover:underline">WPGraphQL Discord</Link>.
      </p>
    </section>
  )
}

function Faq() {
  const questions = [
    {
      question: "What is included in support?",
      answer:
        "Support is limited to usage of WPGraphQL for Advanced Custom Fields. If you need help with broader topics — best practices for GraphQL at your organization, expert consulting on a project, or advice on caching clients like Apollo — get in touch and we can pair you with an expert.",
    },
    {
      question: "Where can I get support?",
      answer:
        "Support and feature requests are handled through Github issues. For general questions about the plugin, visit the WPGraphQL Discord.",
    },
    {
      question: "What are the supported ACF Field Locations?",
      answer:
        "WPGraphQL for ACF attempts to automatically map ACF Field Groups assigned to Post Types, Taxonomies, Users, Comments and Menu Items to the Schema. More specific rules — such as a Field Group assigned to one specific post — cannot be automatically mapped, but the ACF Field Group level has settings to configure which Type(s) in the GraphQL Schema the field group should be associated with.",
    },
    {
      question: "Are GraphQL Mutations supported?",
      answer: "GraphQL Mutations for ACF Fields are not currently supported.",
    },
    {
      question: "Are there any dependencies?",
      answer:
        "WPGraphQL for Advanced Custom Fields requires the latest versions of WPGraphQL and Advanced Custom Fields. It likely works with older versions, but we only officially support compatibility with the latest.",
    },
  ]

  return (
    <section className="mx-auto max-w-3xl px-6 py-20">
      <div className="text-center">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">FAQ</p>
        <h2 id="faq" className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          Frequently asked questions
        </h2>
      </div>
      <div className="mt-12 flex flex-col gap-3">
        {questions.map((q) => (
          <Disclosure key={q.question}>
            {({ open }) => (
              <div className="overflow-hidden rounded-lg border border-border bg-card">
                <Disclosure.Button className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-base font-medium text-foreground transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                  <span>{q.question}</span>
                  <ChevronUpIcon
                    className={`size-5 text-muted-foreground transition-transform ${open ? "rotate-180" : ""}`}
                  />
                </Disclosure.Button>
                <Disclosure.Panel className="px-5 pb-5 text-sm leading-relaxed text-muted-foreground">
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

function Acf({ layoutData }) {
  return (
    <LayoutProvider value={layoutData}>
      <SiteLayout>
        <AcfHero />
        <HowItWorks />
        <SupportedFields />
        <Why />
        <WorksWithJS />
        <Pricing />
        <Faq />
      </SiteLayout>
    </LayoutProvider>
  )
}

export default Acf

export async function getStaticProps() {
  const layoutData = await getLayoutData()
  return {
    props: { layoutData },
    revalidate: 30,
  }
}
