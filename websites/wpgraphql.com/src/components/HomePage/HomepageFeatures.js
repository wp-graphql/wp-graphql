import Image from "next/image"

export default function HomepageFeatures() {
  return (
    <>
      <div className="relative bg-white dark:bg-navy pt-16 overflow-hidden sm:pt-24 lg:pt-32">
        <div className="mx-auto max-w-md px-4 text-center sm:px-6 sm:max-w-3xl lg:px-8 lg:max-w-7xl">
          <div>
            <h2 className="subtitle font-sans">
              Efficient Data Fetching
            </h2>
            <p className="mt-2 text-3xl font-extrabold text-navy dark:text-white tracking-tight sm:text-4xl font-lora">
              Query what you need. Get exactly that.
            </p>
            <p className="mt-5 max-w-prose mx-auto text-xl text-navy dark:text-gray-100">
              With GraphQL, the client makes declarative queries, asking for the
              exact data needed, and exactly what was asked for is given in
              response, nothing more. This allows the client to have control over
              their application, and allows the GraphQL server to perform more
              efficiently by only fetching the resources requested.
            </p>
          </div>
          <div className="mt-12 -mb-10 sm:-mb-24 lg:-mb-10">
            <Image
              className="rounded-lg shadow-xl ring-1 ring-black ring-opacity-5"
              src="/images/graphiql-query-posts.png"
              alt=""
              width={1024}
              height={402}
            />
          </div>
        </div>
      </div>
      <div className="relative bg-blue-100 dark:bg-slate-900 pt-16 overflow-hidden sm:pt-24 lg:pt-32">
        <div className="mx-auto max-w-md px-4 text-center sm:px-6 sm:max-w-3xl lg:px-8 lg:max-w-7xl">
          <div>
            <h2 className="subtitle font-sans">
              Nested Resources
            </h2>
            <p className="mt-2 text-3xl font-extrabold text-navy dark:text-white tracking-tight sm:text-4xl font-lora">
              Fetch many resources in a single request
            </p>
            <p className="mt-5 max-w-prose mx-auto text-xl text-navy dark:text-gray-100">
              GraphQL queries allow access to multiple root resources, and also
              smoothly follow references between connected resources. While a
              typical REST API would require round-trip requests to many
              endpoints, GraphQL APIs can get all the data your app needs in a
              single request. Apps using GraphQL can be quick even on slow
              mobile network connections.
            </p>
          </div>
          <div className="mt-12 -mb-10 sm:-mb-24 lg:-mb-10">
            <Image
              className="rounded-lg shadow-xl ring-1 ring-black ring-opacity-5"
              src="/images/query-multiple-root-resources.png"
              alt=""
              width={1017}
              height={438}
            />
          </div>
        </div>
      </div>
    </>
  )
}
