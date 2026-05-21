import Image from "next/image"

const frameworks = [
  { name: "Gatsby", src: "/logos/logo-gatsby.png" },
  { name: "Next.js", src: "/logos/logo-nextjs.png" },
  { name: "Vue",    src: "/logos/logo-vue.png" },
  { name: "Svelte", src: "/logos/logo-svelte.png" },
]

export default function HomepageFrameworks() {
  return (
    <section className="px-4 py-20 sm:px-6 lg:px-8 lg:py-28">
      <div className="mx-auto max-w-7xl">
        <div className="mx-auto max-w-3xl text-center">
          <p className="font-mono text-xs font-medium uppercase tracking-widest text-primary">
            Framework Agnostic
          </p>
          <h2 className="mt-3 text-display-sm font-bold tracking-tight text-foreground sm:text-display-md">
            Build rich JavaScript applications with{" "}
            <span className="text-primary">WordPress &amp; GraphQL</span>
          </h2>
          <p className="mx-auto mt-5 max-w-prose text-base text-muted-foreground sm:text-lg">
            WPGraphQL separates your CMS from your presentation layer. Content
            creators use the CMS they know; developers use the frameworks and
            tools they love.
          </p>
        </div>
        <div className="mx-auto mt-14 grid max-w-4xl grid-cols-2 gap-3 sm:grid-cols-4">
          {frameworks.map((fw) => (
            <div
              key={fw.name}
              className="group flex flex-col items-center gap-3 rounded-xl border border-border bg-card p-6 transition-all hover:-translate-y-1 hover:border-primary/40 hover:shadow-glow-sm"
            >
              <Image
                src={fw.src}
                alt={fw.name}
                height={56}
                width={56}
                className="h-14 w-auto"
              />
              <span className="font-mono text-xs uppercase tracking-widest text-muted-foreground group-hover:text-foreground">
                {fw.name}
              </span>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}
