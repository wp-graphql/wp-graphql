import Link from "next/link"
import { socialFooterLinks } from "data/social"
import SiteLogo from "./SiteLogo"

export default function Footer() {
  const year = new Date().getFullYear()
  return (
    <footer className="mt-24 border-t border-border bg-card/50">
      <div className="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-12 md:flex-row md:items-center md:justify-between">
        <div className="flex flex-col gap-4">
          <Link href="/" legacyBehavior>
            <a className="flex items-center">
              <span className="sr-only">WPGraphQL</span>
              <SiteLogo size={32} />
            </a>
          </Link>
          <p className="font-mono text-xs text-muted-foreground">
            &copy; {year} WPGraphQL · GPL-3 Licensed
          </p>
        </div>
        <div className="flex items-center gap-2">
          {socialFooterLinks.map((item) => (
            <a
              key={item.name}
              href={item.href}
              className="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
              target="_blank"
              rel="noreferrer"
            >
              <span className="sr-only">{item.name}</span>
              <item.icon className="h-5 w-5" aria-hidden="true" />
            </a>
          ))}
        </div>
      </div>
    </footer>
  )
}
