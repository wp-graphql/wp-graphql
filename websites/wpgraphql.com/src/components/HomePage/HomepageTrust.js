import Image from "next/image"

/* This example requires Tailwind CSS v2.0+ */
export default function HomePageTrust() {
  return (
    <div className="bg-lightGray dark:bg-slate-800">
      <div className="max-w-8xl mx-auto py-12 px-4 sm:px-6 lg:py-16 lg:px-8">
        <h2 className="text-center text-3xl tracking-tight font-extrabold text-navy dark:text-white sm:text-4xl">
          {`Who's Using WPGraphQL?`}
        </h2>
        <p className="text-center mt-3 max-w-2xl mx-auto text-xl text-navy dark:text-gray-50 sm:mt-4">
          Digital agencies, product teams and freelancers around the world trust
          WPGraphQL in production to bridge modern front-end stacks with content
          managed in WordPress.
        </p>
        <div className="mt-6 grid grid-cols-2 gap-0.5 md:grid-cols-3 lg:mt-8">
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white">
            <Image
              className="max-h-12"
              src="/logos/logo-apollo.png"
              alt="Apollo GraphQL"
              width={80}
              height={80}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white ">
            <Image
              className="max-h-12"
              src="/logos/logo-credit-karma.png"
              alt="Credit Karma"
              width={80}
              height={80}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white ">
            <Image
              className="max-h-12"
              src="/logos/logo-denverpost.svg"
              alt="The Denver Post"
              width={220}
              height={80}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white">
            <Image
              className="max-h-12"
              src="/logos/logo-dfuzr.png"
              alt="Dfuzr"
              width={260}
              height={80}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white ">
            <Image
              className="max-h-12"
              src="/logos/logo-funkhaus.png"
              alt="Funkhaus"
              width={280}
              height={80}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white ">
            <Image
              className="max-h-12"
              src="/logos/logo-harness.png"
              alt="Harness Software"
              width={250}
              height={75}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white ">
            <Image
              className="max-h-12"
              src="/logos/logo-webdev-studios.png"
              alt="Web Dev Studios"
              width={200}
              height={75}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white ">
            <Image
              className="max-h-12"
              src="/logos/logo-quartz.jpg"
              alt="Quartz"
              width={80}
              height={80}
            />
          </div>
          <div className="col-span-1 flex justify-center py-8 px-8 bg-white ">
            <Image
              className="max-h-12"
              src="/logos/logo-hope-lab.png"
              alt="Hope Lab"
              width={120}
              height={80}
            />
          </div>
        </div>
      </div>
    </div>
  )
}
