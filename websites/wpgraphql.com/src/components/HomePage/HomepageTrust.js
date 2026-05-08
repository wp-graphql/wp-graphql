import Image from "next/image"

const logos = [
  { src: "/logos/logo-apollo.png",         alt: "Apollo GraphQL", width: 80,  height: 80 },
  { src: "/logos/logo-credit-karma.png",   alt: "Credit Karma",   width: 80,  height: 80 },
  { src: "/logos/logo-denverpost.svg",     alt: "The Denver Post", width: 220, height: 80 },
  { src: "/logos/logo-dfuzr.png",          alt: "Dfuzr",          width: 260, height: 80 },
  { src: "/logos/logo-funkhaus.png",       alt: "Funkhaus",       width: 280, height: 80 },
  { src: "/logos/logo-harness.png",        alt: "Harness Software", width: 250, height: 75 },
  { src: "/logos/logo-webdev-studios.png", alt: "Web Dev Studios", width: 200, height: 75 },
  { src: "/logos/logo-quartz.jpg",         alt: "Quartz",         width: 80,  height: 80 },
  { src: "/logos/logo-hope-lab.png",       alt: "Hope Lab",       width: 120, height: 80 },
]

export default function HomePageTrust() {
  return (
    <section className="px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
      <div className="mx-auto max-w-7xl text-center">
        <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
          Trusted by
        </p>
        <h2 className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
          {`Who's using WPGraphQL?`}
        </h2>
        <p className="mx-auto mt-5 max-w-2xl text-base text-muted-foreground sm:text-lg">
          Digital agencies, product teams and freelancers around the world
          trust WPGraphQL in production to bridge modern front-end stacks with
          content managed in WordPress.
        </p>
        <div className="mt-12 grid grid-cols-2 gap-3 sm:grid-cols-3">
          {logos.map((logo) => (
            <div
              key={logo.alt}
              className="flex h-28 items-center justify-center rounded-xl border border-border bg-card px-8 transition-colors hover:border-primary/30"
            >
              <Image
                src={logo.src}
                alt={logo.alt}
                width={logo.width}
                height={logo.height}
                className="max-h-12 w-auto opacity-90 transition-opacity hover:opacity-100"
              />
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
