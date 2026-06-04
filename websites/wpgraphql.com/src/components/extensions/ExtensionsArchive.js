import Link from "next/link"
import { Squares2X2Icon } from "@heroicons/react/20/solid"
import { SectionHeading } from "@/components/extensions/SectionHeading"
import ExtensionPreview from "@/components/Preview/ExtensionPreview"
import { featuredExtensions, isFeaturedExtension } from "../../data/extensions"

/**
 * The /extensions archive body: a branded "Featured Extensions" section (the
 * first-party, sibling-branded plugins) above the headless "Community
 * Extensions" list sourced from the WordPress `ExtensionPlugin` post type.
 *
 * `nodes` are the ExtensionPlugin nodes from the archive query; featured
 * extensions are filtered out so they aren't shown twice.
 */
export default function ExtensionsArchive({ nodes = [] }) {
  const community = nodes.filter((n) => !isFeaturedExtension(n?.title))

  return (
    <>
      <section className="relative overflow-hidden border-b border-border">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-0 -z-10"
          style={{
            background:
              "radial-gradient(ellipse 900px 500px at 50% 0%, hsl(var(--primary) / 0.10) 0%, transparent 70%)",
          }}
        />
        <div className="mx-auto max-w-7xl px-6 py-20 lg:py-24">
          <SectionHeading
            icon={Squares2X2Icon}
            eyebrow="Featured Extensions"
            lead="Built by the makers"
            accent="of WPGraphQL."
            intro="First-party extensions from the WPGraphQL team — deeply integrated, actively maintained, and held to the same standard as core."
          />
          <div className="mt-14 grid gap-6 md:grid-cols-3">
            {featuredExtensions.map(
              ({ name, href, description, theme, Mark }) => (
                <Link key={href} href={href} legacyBehavior>
                  <a
                    className={`${theme} group flex flex-col rounded-2xl border border-border bg-card p-8 transition-all hover:-translate-y-1 hover:border-primary/40 hover:shadow-glow-md`}
                  >
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
                  </a>
                </Link>
              )
            )}
          </div>
        </div>
      </section>

      {community.length > 0 && (
        <section className="bg-card/40">
          <div className="mx-auto max-w-7xl px-6 py-20">
            <SectionHeading
              icon={Squares2X2Icon}
              eyebrow="Community Extensions"
              lead="Built by the"
              accent="wider community."
              intro="Plugins that extend WPGraphQL, maintained by the wider community."
            />
            <div className="mx-auto mt-14 grid max-w-5xl gap-5">
              {community.map((node) => (
                <ExtensionPreview key={node.id} extension={node} />
              ))}
            </div>
          </div>
        </section>
      )}
    </>
  )
}
