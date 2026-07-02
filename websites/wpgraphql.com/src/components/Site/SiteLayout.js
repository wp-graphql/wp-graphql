import gql from "graphql-tag"
import Header, { NavMenuFragment } from "./SiteHeader"
import Footer from "./SiteFooter"

export default function SiteLayout({ children }) {
  return (
    <>
      <Header />
      {children}
      <Footer />
    </>
  )
}

export const Layout = {
  queries: {
    navMenu: {
      query: gql`
        query Layout_NavMenu {
          ...NavMenu
        }
        ${NavMenuFragment}
      `,
      variables: () => ({}),
    },
  },
}
