import Image from "next/image"
import Link from "next/link"

export default function HomepageHero() {
  return (
    <div className="bg-white dark:bg-navy">
      <div className="max-w-8xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
        <div className="dark:bg-slate-900 bg-slate-100 rounded-lg shadow-xl overflow-hidden lg:grid lg:grid-cols-2 lg:gap-4">
          <div className="pt-10 pb-12 px-6 sm:pt-16 sm:px-16 lg:py-16 lg:pr-0 xl:py-20 xl:px-20">
            <div className="lg:self-center">
              <h2 className="text-3xl font-extrabold text-navy dark:text-white sm:text-4xl">
                <span className="block">GraphQL API for WordPress</span>
              </h2>
              <p className="mt-4 text-lg leading-6 text-navy dark:text-slate-100">
                WPGraphQL is a free, open-source WordPress plugin that provides
                an extendable GraphQL schema and API for any WordPress site.
              </p>
              <Link href="/docs/introduction">
                <a className="btn-secondary">
                  Get Started
                </a>
              </Link>
              <a
                href="https://wordpress.org/plugins/wp-graphql"
                rel="noreferrer"
                target="_blank"
                className="btn-primary"
              >
                Download the Plugin
              </a>
            </div>
          </div>
          <div className="-mt-6 aspect-w-5 aspect-h-3 md:aspect-w-2 md:aspect-h-1">
            <Image
              className="transform translate-x-6 translate-y-6 rounded-md object-cover object-left-top sm:translate-x-16 lg:translate-y-20"
              src="/images/query-posts.png"
              alt="App screenshot"
              layout="fill"
            />
          </div>
        </div>
      </div>
    </div>
  )
}
