import NextDocument, { Html, Head, Main, NextScript } from "next/document"
import { bricolage, dmMono } from "lib/fonts"

const FAVICON_VERSION = 5
const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL

function v(href) {
  return `${href}?v=${FAVICON_VERSION}`
}

export default class Document extends NextDocument {
  static async getInitialProps(ctx) {
    const initialProps = await NextDocument.getInitialProps(ctx)
    return { ...initialProps }
  }

  render() {
    return (
      <Html
        lang="en"
        suppressHydrationWarning
        className={`${bricolage.variable} ${dmMono.variable}`}
      >
        <Head>
          <link
            href={`${SITE_URL}/api/feeds/feed.json`}
            rel="alternate"
            type="application/feed+json"
            title="WPGraphQL Blog JSON Feed"
          />
          <link
            href={`${SITE_URL}/api/feeds/rss.xml`}
            rel="alternate"
            type="application/rss+xml"
            title="WPGraphQL Blog XML Feed"
          />
          <link
            href={`${SITE_URL}/api/feeds/feed.atom`}
            rel="alternate"
            type="application/atom+xml"
            title="WPGraphQL Blog Atom Feed"
          />
          <link
            rel="icon"
            type="image/svg+xml"
            href={v("/favicons/favicon.svg")}
          />
          <link
            rel="apple-touch-icon"
            sizes="180x180"
            href={v("/favicons/apple-touch-icon.png")}
          />
          <link
            rel="icon"
            type="image/png"
            sizes="96x96"
            href={v("/favicons/favicon-96x96.png")}
          />
          <link
            rel="icon"
            type="image/png"
            sizes="32x32"
            href={v("/favicons/favicon-32x32.png")}
          />
          <link
            rel="icon"
            type="image/png"
            sizes="16x16"
            href={v("/favicons/favicon-16x16.png")}
          />
          <link rel="manifest" href={v("/favicons/site.webmanifest")} />
          <link
            rel="mask-icon"
            href={v("/favicons/safari-pinned-tab.svg")}
            color="#FF8C1A"
          />
          <link rel="shortcut icon" href={v("/favicons/favicon.ico")} />
          <meta name="apple-mobile-web-app-title" content="WPGraphQL" />
          <meta name="application-name" content="WPGraphQL" />
          <meta name="theme-color" content="#0A0F1E" />
        </Head>
        <body className="bg-background text-foreground antialiased">
          <Main />
          <NextScript />
        </body>
      </Html>
    )
  }
}
