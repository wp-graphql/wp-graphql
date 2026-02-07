import clsx from "clsx"
import NextDocument, { Html, Head, Main, NextScript } from "next/document"

const FAVICON_VERSION = 4
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
        className="dark [--scroll-mt:9.875rem] lg:[--scroll-mt:6.3125rem]"
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
            rel="apple-touch-icon"
            sizes="180x180"
            href={v("/favicons/apple-touch-icon.png")}
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
            color="#38bdf8"
          />
          <link rel="shortcut icon" href={v("/favicon.ico")} />
          <meta name="apple-mobile-web-app-title" content="WPGraphQL" />
          <meta name="application-name" content="WPGraphQL" />
          <meta name="theme-color" content="#172A53" />
          <script
            dangerouslySetInnerHTML={{
              __html: `
                    try {
                      if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark')
                      } else {
                        document.documentElement.classList.remove('dark')
                      }
                    } catch (_) {}
                  `,
            }}
          />
        </Head>
        <body
          className={clsx("antialiased text-slate-500 dark:text-slate-400 font-inter", {
            "bg-white dark:bg-navy":
              !this.props.dangerousAsPath.startsWith("/examples/"),
          })}
        >
          <Main />
          <NextScript />
        </body>
      </Html>
    )
  }
}
