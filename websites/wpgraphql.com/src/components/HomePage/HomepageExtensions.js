import Link from "next/link"
import { Button } from "@/components/ui/button"
import Constellation from "@/components/extensions/Constellation"
import { featuredExtensions } from "../../data/extensions"

/**
 * Homepage "Extensions" section — highlights the first-party, sibling-branded
 * plugins (IDE, ACF, Smart Cache) and links to the full /extensions archive.
 *
 * Reuses the canonical `featuredExtensions` data so it stays in sync with the
 * header dropdown and the /extensions archive. Each card carries its product's
 * `theme-*` scope, so its logo mark, constellation field, and glow all render
 * in the sibling brand's accent color — echoing the WordPress.org plugin
 * banners — while the surrounding homepage chrome stays WPGraphQL-orange.
 */

export default function HomepageExtensions() {
  return (
    <section className="px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
      <div className="mx-auto max-w-7xl">
        <div className="mx-auto max-w-3xl text-center">
          <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
            Extensions
          </p>
          <h2 className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
            Take your graph <span className="text-primary">further</span>
          </h2>
          <p className="mx-auto mt-5 max-w-prose text-base text-muted-foreground sm:text-lg">
            Build queries faster, bring every custom field into your schema, and
            serve responses at the edge — first-party extensions from the team
            behind WPGraphQL, held to the same standard as core.
          </p>
        </div>

        <div className="mt-14 grid gap-6 md:grid-cols-3">
          {featuredExtensions.map(
            ({ name, href, description, theme, Mark }, i) => (
              <Link key={href} href={href} legacyBehavior>
                <a
                  className={`${theme} group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card p-8 transition-all hover:-translate-y-1 hover:border-primary/40 hover:shadow-glow-md`}
                >
                  {/* Brand-tinted constellation field + a soft glow behind the
                      logo mark — a mini echo of the wp.org plugin banner. */}
                  <Constellation variant={i} />
                  <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -left-4 -top-4 h-40 w-40 rounded-full bg-primary/20 blur-2xl transition-opacity group-hover:bg-primary/30"
                  />
                  <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-card/30 to-card"
                  />

                  <div className="relative flex flex-1 flex-col">
                    <Mark size={64} className="h-16 w-16 rounded-xl" />
                    <h3 className="mt-6 text-headline font-bold tracking-tight text-foreground">
                      {name}
                    </h3>
                    <p className="mt-2 flex-1 text-base text-muted-foreground">
                      {description}
                    </p>
                    <span className="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-primary">
                      Explore {name}
                      <span
                        aria-hidden="true"
                        className="transition-transform group-hover:translate-x-0.5"
                      >
                        →
                      </span>
                    </span>
                  </div>
                </a>
              </Link>
            )
          )}
        </div>

        <div className="mt-12 flex justify-center">
          <Button asChild variant="outline" size="lg">
            <Link href="/extensions">Browse all extensions</Link>
          </Button>
        </div>
      </div>
    </section>
  )
}
