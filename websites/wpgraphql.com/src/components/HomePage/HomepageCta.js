import Link from "next/link"
import { Button } from "@/components/ui/button"

export default function HomepageCta() {
  return (
    <section className="px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
      <div className="relative mx-auto max-w-4xl overflow-hidden rounded-2xl border border-border bg-card px-8 py-16 text-center shadow-elev-lg sm:px-16">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute left-1/2 -bottom-1/4 -z-10 h-[60%] w-[60%] -translate-x-1/2 rounded-full bg-primary/20 blur-3xl"
        />
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          Get Started
        </p>
        <h2 className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          Boost your productivity with{" "}
          <span className="text-primary">WPGraphQL</span>
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-base text-muted-foreground sm:text-lg">
          Free, open-source, and trusted by teams shipping WordPress at scale.
        </p>
        <div className="mt-10 flex flex-wrap justify-center gap-4">
          <Link href="https://wordpress.org/plugins/wp-graphql" legacyBehavior>
            <Button asChild size="lg">
              <a target="_blank" rel="noreferrer">Download the Plugin</a>
            </Button>
          </Link>
          <Link href="/docs/introduction" legacyBehavior>
            <Button asChild variant="secondary" size="lg">
              <a>Read the Docs</a>
            </Button>
          </Link>
        </div>
      </div>
    </section>
  )
}
