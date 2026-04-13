import "../../faust.config"
import { useRouter } from "next/router"
import { FaustProvider } from "@faustwp/core"
import Script from "next/script"
import * as gtag from "../lib/gtag";

import "../styles/globals.css"
import "../styles/docs.css"
import { SearchProvider } from "../components/Site/SearchButton";
import { useEffect } from "react";

export default function MyApp({ Component, pageProps }) {
  const router = useRouter()

  // track page views with google analytics
  useEffect(() => {
    const handleRouteChange = (url) => {
      gtag.pageview(url);
    };
    router.events.on( "routeChangeComplete", handleRouteChange);
    return () => {
      router.events.off("routeChangeComplete", handleRouteChange);
    };
  }, [router.events]);

  return (
    <SearchProvider>
      <FaustProvider pageProps={pageProps}>
        <Script
          strategy="afterInteractive"
          src={`https://www.googletagmanager.com/gtag/js?id=${gtag.GA_TRACKING_ID}`}
        />
        <Script
          id="google-analytics"
          strategy="afterInteractive"
          dangerouslySetInnerHTML={{
            __html: `
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '${gtag.GA_TRACKING_ID}', {
              page_path: window.location.pathname,
            });
          `,
          }}
        />
        <Component {...pageProps} key={router.asPath} />
      </FaustProvider>
    </SearchProvider>
  )
}
