import { useState } from "react"
import { CommandLineIcon, PlayCircleIcon } from "@heroicons/react/20/solid"
import { Button } from "@/components/ui/button"
import { SectionHeading as BaseSectionHeading } from "@/components/extensions/SectionHeading"
import { WPGraphQLIDELogoMark } from "@/components/IDE/WPGraphQLIDELogo"

// Blueprint mirrors the one in .github/workflows/playground-preview.yml so the
// public demo and the PR-preview demo share install steps. login / landingPage
// / networking are also set here, but the surrounding query string (`url=`,
// `login=yes`, `networking=yes`) takes precedence — that's the documented
// override path and is more reliable when plugins redirect on activation.
// Only the IDE source URL differs from the GH version (wp.org stable here,
// per-PR nightly.link artifact there).
const BLUEPRINT = {
  preferredVersions: { php: "8.3", wp: "latest" },
  features: { networking: true },
  login: true,
  landingPage: "/wp-admin/admin.php?page=graphql-ide",
  steps: [
    {
      step: "installPlugin",
      pluginData: {
        resource: "url",
        url: "https://downloads.wordpress.org/plugin/wp-graphql.latest-stable.zip",
      },
      options: { activate: true },
    },
    {
      step: "installPlugin",
      pluginData: {
        resource: "url",
        url: "https://downloads.wordpress.org/plugin/wpgraphql-ide.latest-stable.zip",
      },
      options: { activate: true },
    },
  ],
}

// mode=seamless          → hides Playground's own browser chrome (address bar,
//                          left toolbar) so the embed is just WordPress.
// storage=temp           → no persistence across sessions; every visitor gets a
//                          fresh install. Right call for a "try it" demo.
// networking=yes         → required to fetch the plugin zips at boot.
// login=yes              → auto-login as admin so the IDE is reachable.
// url=…                  → overrides the blueprint's landingPage; first-party
//                          plugin activation redirects (wp-graphql's settings
//                          tour) can otherwise win the race.
const PLAYGROUND_QS = new URLSearchParams({
  mode: "seamless",
  storage: "temp",
  networking: "yes",
  login: "yes",
  url: "/wp-admin/admin.php?page=graphql-ide",
}).toString()

function SectionHeading(props) {
  return <BaseSectionHeading icon={CommandLineIcon} {...props} />
}

function Placeholder({ onLaunch }) {
  return (
    <div className="ide-bg ide-border relative overflow-hidden rounded-2xl border shadow-elev-md">
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0"
        style={{
          background:
            "radial-gradient(ellipse 600px 400px at 50% 0%, hsl(var(--primary) / 0.18) 0%, transparent 65%)",
        }}
      />
      <div className="relative flex flex-col items-center px-6 py-20 text-center">
        <WPGraphQLIDELogoMark
          size={120}
          className="mb-8 h-auto w-[120px]"
          style={{
            filter:
              "drop-shadow(0 0 40px hsl(var(--primary) / 0.45)) drop-shadow(0 0 14px hsl(var(--primary) / 0.35))",
          }}
        />
        <p className="ide-text mb-3 text-xl font-semibold">
          WPGraphQL IDE, running in your browser.
        </p>
        <p className="ide-muted mb-10 max-w-md text-sm leading-relaxed">
          Click below to spin up a fresh WordPress with the IDE installed. Real
          wp-admin, real database, real GraphQL — entirely client-side.
        </p>
        <Button size="lg" onClick={onLaunch}>
          <PlayCircleIcon className="size-5" aria-hidden="true" />
          Launch live demo
        </Button>
        <p className="ide-muted mt-5 font-mono text-xs">
          Boots in ~20s · Powered by{" "}
          <a
            href="https://wordpress.org/playground/"
            target="_blank"
            rel="noreferrer"
            className="underline hover:text-primary"
          >
            WordPress Playground
          </a>
        </p>
      </div>
    </div>
  )
}

function LiveEmbed() {
  // Playground reads the blueprint from the URL fragment as base64-encoded JSON
  // — same convention as the GH workflow's sticky preview link.
  const url = `https://playground.wordpress.net/?${PLAYGROUND_QS}#${btoa(JSON.stringify(BLUEPRINT))}`
  return (
    <div className="ide-border overflow-hidden rounded-2xl border shadow-elev-md">
      <iframe
        src={url}
        title="WPGraphQL IDE — live demo running in WordPress Playground"
        loading="lazy"
        allow="clipboard-write"
        className="block aspect-[16/10] min-h-[760px] w-full border-0 bg-card"
      />
    </div>
  )
}

export default function PlaygroundEmbed() {
  const [launched, setLaunched] = useState(false)

  return (
    <section className="mx-auto max-w-7xl px-6 py-20">
      <SectionHeading
        eyebrow="Try it live"
        lead="A real WordPress."
        accent="Right in your browser."
        intro="Boots a fresh WordPress with WPGraphQL and the IDE installed, then drops you straight into the editor. No download, no setup."
      />
      <div className="mt-12">
        {launched ? (
          <LiveEmbed />
        ) : (
          <Placeholder onLaunch={() => setLaunched(true)} />
        )}
      </div>
    </section>
  )
}
