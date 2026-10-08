import HomepageCta from "components/HomePage/HomepageCta"
import HomepageExtensions from "components/HomePage/HomepageExtensions"
import HomepageFeatures from "components/HomePage/HomepageFeatures"
import HomepageFrameworks from "components/HomePage/HomepageFrameworks"
import HomepageHero from "components/HomePage/HomepageHero"
import HomePageTrust from "components/HomePage/HomepageTrust"
import SiteLayout from "components/Site/SiteLayout"
import Seo from "components/Seo/Seo"

const META = {
  title: "WPGraphQL - The GraphQL API for WordPress",
  description:
    "WPGraphQL is a free, open-source WordPress plugin that provides an extendable GraphQL schema and API for any WordPress site.",
}

export default function FrontPage({ uri }) {
  return (
    <SiteLayout>
      <Seo title={META.title} description={META.description} uri={uri || "/"} />
      <main className="content">
        <HomepageHero />
        <HomepageFrameworks />
        <HomepageFeatures />
        <HomepageExtensions />
        <HomePageTrust />
        <HomepageCta />
      </main>
    </SiteLayout>
  )
}

FrontPage.queries = {}
