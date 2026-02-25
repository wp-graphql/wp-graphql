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

export { NavMenuFragment }
