import { gql } from "@apollo/client"

import HomepageCta from "components/HomePage/HomepageCta"
import HomepageFeatures from "components/HomePage/HomepageFeatures"
import HomepageFrameworks from "components/HomePage/HomepageFrameworks"
import HomepageHero from "components/HomePage/HomepageHero"
import HomePageTrust from "components/HomePage/HomepageTrust"
import SiteLayout, { NavMenuFragment } from "components/Site/SiteLayout"

export default function FrontPage({ data }) {
  return (
    <SiteLayout>
      <main className="content">
        <HomepageHero />
        <HomepageFrameworks />
        <HomepageFeatures />
        <HomePageTrust />
        <HomepageCta />
      </main>
    </SiteLayout>
  )
}

FrontPage.layoutProps = {
  meta: {
    title: "WPGraphQL - The GraphQL API for WordPress",
  },
}

FrontPage.query = gql`
  query frontPageQuery {
    ...NavMenu
  }
  ${NavMenuFragment}
`
