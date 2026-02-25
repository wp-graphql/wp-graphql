import Image from "next/image"

/* This example requires Tailwind CSS v2.0+ */
export default function HomepageFrameworks() {
  return (
    <div className="bg-blue-100 dark:bg-blue pt-12 sm:pt-16">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="max-w-4xl mx-auto text-center">
          <h2 className="text-3xl font-extrabold text-navy dark:text-white sm:text-4xl">
            Build rich JavaScript applications <br /> with WordPress and GraphQL
          </h2>
          <p className="mt-3 text-xl text-navy dark:text-gray-200 sm:mt-4">
            WPGraphQL allows you to separate your CMS from your presentation
            layer. Content creators can use the CMS they know, while developers
            can use the frameworks and tools they love.
          </p>
        </div>
      </div>
      <div className="mt-10 pb-12 bg-white dark:bg-navy sm:pb-16">
        <div className="relative">
          <div className="absolute inset-0 h-1/2 bg-blue-100 dark:bg-blue" />
          <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="max-w-4xl mx-auto">
              <dl className="rounded-lg bg-white dark:bg-slate-900 shadow-lg sm:grid sm:grid-cols-4">
                <div className="flex flex-col border-t border-b border-gray-100 dark:border-0 p-6 text-center sm:border-0 sm:border-l sm:border-r">
                  <dt className="order-2 mt-2 text-lg leading-6 font-medium text-gray-500 dark:text-gray-200">
                    Gatsby
                  </dt>
                  <dd className="order-1 text-5xl text-center font-extrabold text-indigo-600">
                    <Image
                      className="max-h-12"
                      src="/logos/logo-gatsby.png"
                      alt="Gatsby"
                      height={75}
                      width={75}
                    />
                  </dd>
                </div>
                <div className="flex flex-col border-t border-b border-gray-100 dark:border-slate-600 p-6 text-center sm:border-0 sm:border-l sm:border-r">
                  <dt className="order-2 mt-2 text-lg leading-6 font-medium text-gray-500 dark:text-gray-200">
                    NextJS
                  </dt>
                  <dd className="order-1 text-5xl font-extrabold text-indigo-600">
                    <Image
                      className="max-h-12"
                      src="/logos/logo-nextjs.png"
                      alt="NextJS"
                      height={75}
                      width={75}
                    />
                  </dd>
                </div>
                <div className="flex flex-col border-t border-b border-gray-100 dark:border-slate-600 p-6 text-center sm:border-0 sm:border-l sm:border-r">
                  <dt className="order-2 mt-2 text-lg leading-6 font-medium text-gray-500 dark:text-gray-200">
                    Vue
                  </dt>
                  <dd className="order-1 text-5xl font-extrabold text-indigo-600">
                    <Image
                      className="max-h-12"
                      src="/logos/logo-vue.png"
                      alt="Vue"
                      height={75}
                      width={75}
                    />
                  </dd>
                </div>
                <div className="flex flex-col border-t border-b border-gray-100 dark:border-0 p-6 text-center sm:border-0 sm:border-l sm:border-r">
                  <dt className="order-2 mt-2 text-lg leading-6 font-medium text-gray-500 dark:text-gray-200">
                    Svelte
                  </dt>
                  <dd className="order-1 text-5xl font-extrabold text-indigo-600">
                    <Image
                      className="max-h-12"
                      src="/logos/logo-svelte.png"
                      alt="Svelte"
                      height={75}
                      width={75}
                    />
                  </dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
