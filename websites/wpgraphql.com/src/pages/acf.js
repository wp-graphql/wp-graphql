import {addApolloState, getApolloClient} from "@faustwp/core/dist/mjs/client"
import {NavMenuFragment} from "../components/Site/SiteHeader";
import {gql} from "@apollo/client";
import SiteLayout from "../components/Site/SiteLayout";
import Image from 'next/image'
import {Disclosure} from "@headlessui/react";
import {ChevronUpIcon} from "@heroicons/react/20/solid";
import Link from "next/link";

const GET_NAV_MENU = gql`
query GetNavMenu {
   ...NavMenu
}
${NavMenuFragment}
`

function AcfHero() {
  return (
    <div className="w-full bg-[#00e4bc] flex justify-center pb-[100px]">
      <div className=" w-3/4 pt-5 pb-5 pt-10 md:pt-20 md:pb-20 prose">
        <h1 className="text-5xl drop-shadow text-center text-white">WPGraphQL for Advanced Custom
          Fields</h1>
        <div className="flex flex-wrap w-full">
          <div className="text-center md:text-right p-1 md:p-5 w-full md:w-1/2 mb-4">
            <h2
              className="w-full md:w-3/4 text-4xl float-none md:float-right font-normal drop-shadow text-white mt-0 mb-4">Interact
              with your Advanced Custom Field data using GraphQL Queries</h2>
            <a
              className="bg-orange-500 hover:bg-orange-400 text-white dark:text-white font-bold py-2 px-4 rounded inline-flex items-center"
              href="https://github.com/wp-graphql/wp-graphql-acf"
              target="_blank"
              rel="noreferrer"
            >
              <svg className="fill-current w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg"
                   viewBox="0 0 20 20">
                <path d="M13 8V2H7v6H2l8 8 8-8h-5zM0 18h20v2H0v-2z"/>
              </svg>
              <span>Download the Plugin</span>
            </a>
          </div>
          <div className="p-5 w-full md:w-1/2 relative pb-[50%] md:pb-[20%]">
            <iframe
              src="https://www.youtube.com/embed/rIg4MHc8elg"
              title="WPGraphQL for Advanced Custom Fields"
              allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
              frameBorder="0"
              webkitallowfullscreen="true"
              mozallowfullscreen="true"
              allowFullScreen
              className="absolute top-0 left-0 h-[100%] w-[100%] md:w-[80%]"
            />
          </div>
        </div>
      </div>
    </div>
  )
}

function HowItWorks() {
  return (
    <div className="text-center lg:max-w-[80%] mx-auto">
      <h2 className="text-3xl pb-5 uppercase">How it Works</h2>
      <p className="text-3xl font-light">WPGraphQL for Advanced Custom Fields automatically exposes
        your ACF fields to the WPGraphQL Schema</p>
      <div className="flex flex-wrap w-full">
        <div className="lg:w-1/2 p-5">
          <Image
            src='/images/acf-fields.jpeg'
            alt="Screenshot of Advanced Custom Fields field group user interface"
            width="710"
            height="628"
          />
        </div>
        <div className="lg:w-1/2 lg:text-left">
          <h3 id="create-your-acf-fields" className="text-2xl mt-0 lg:mt-[2.4em]">Create your ACF Fields</h3>
          <p className="text-xl">Create your ACF Field Groups and Fields, the same way you normally
            would, using the ACF User Interface, registering your fields with PHP or using ACF
            local-json. Each field group and the fields within it can be configured to &quot;Show in
            GraphQL&quot;.</p>
        </div>
      </div>
      <div className="flex flex-wrap border-t-1 lg:border-t-0 w-full pt-10 lg:pt-0 border-t">
        <div className="lg:w-1/2 lg:text-right">
          <h3 id="query-with-graphql" className="text-2xl mt-0 lg:mt-[2.4em]">Query with GraphQL</h3>
          <p className="text-xl">Once your field groups and fields have been configured to &quot;Show in
            GraphQL&quot;, they will be available in the GraphQL Schema and ready for querying!</p>
        </div>
        <div className="lg:w-1/2 p-5 lg:pt-[50px] pt-0">
          <Image
            src='/images/acf-query-fields.png'
            alt="Screenshot of Advanced Custom Fields field group user interface"
            width="1738"
            height="832"
          />
        </div>
      </div>
    </div>
  )
}

function SupportedFields() {
  const fields = [
    "Text",
    "Text Area",
    "Number",
    "Range",
    "Email",
    "URL",
    "Password",
    "Image",
    "File",
    "WYSIWYG",
    "oEmbed",
    "Select",
    "Checkbox",
    "Radio Button",
    "Button Group",
    "True False",
    "Link",
    "Post Object",
    "Page Link",
    "Relationship",
    "Taxonomy",
    "User",
    "Google Map",
    "Date Picker",
    "Date/Time Picker",
    "Time Picker",
    "Color Picker",
    "Group",
    "Repeater",
    "Flex Field",
    "Gallery",
  ]

  return (
    <div className="mb-70 lg:px-20 text-center">
      <h2 id="supported-fields" className="text-3xl pb-5 uppercase">Supported Fields</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
        {fields.map((field, i) => (
          <div key={i}>{field}</div>
        ))}
      </div>
      <p className="text-sm my-10">WPGraphQL for Advanced Custom Fields supports nearly all of the
        ACF (free & pro) fields. Some of the fields, such as Accordion and Tab, which are not data
        fields are not supported. The Clone field needs some more assessment to determine if it can
        properly be supported. Fields from 3rd party extensions are not supported out of the
        box.</p>
    </div>
  )

}
function Why() {
  const features = [
    {
      title: "Time",
      content: "WPGraphQL is highly extendable, but it can be time consuming to expose fields to the Schema. This plugin can save you heaps of time.",
    },
    {
      title: "Performance",
      content: "WPGraphQL is one of the fastest ways to query data in WordPress, and now we bring that performance to ACF data too."
    },
    {
      title: "Support",
      content: "Receive the same great community support as the core WPGraphQL plugin through channels such as Github and Discord.",
    }
  ]

  return (
    <div className="mb-70 px-0 lg:px-20 text-center">
      <h2 id="why-wpgraphql-for-acf" className="text-3xl pb-5 uppercase">Why WPGraphQL for ACF?</h2>
      <div className="grid grid-cols-1 md:grid-cols-3">
        {features.map((feature, i) => (
          <div key={i}
               className="box-shadow bg-slate-200 dark:bg-slate-800 m-5 rounded-lg drop-shadow-lg p-5">
            <h3 className="uppercase">{feature.title}</h3>
            <p>{feature.content}</p>
          </div>
        ))}
      </div>

    </div>
  )
}

function WorksWithJS() {

  const frameworks = [
    {
      name: "React",
      logo: "/logos/logo-react.png"
    },
    {
      name: "Vue",
      logo: "/logos/logo-vue.png"
    },
    {
      name: "NextJS",
      logo: "/logos/logo-nextjs.png"
    },
    {
      name: "Gatsby",
      logo: "/logos/logo-gatsby.png"
    },
    {
      name: "Ember",
      logo: "/logos/logo-ember.png"
    },
    {
      name: "Angular",
      logo: "/logos/logo-angular.png"
    }
  ];

  return (
    <div className="mb-70 lg:px-20 text-center">
      <h2 id="frameworks" className="text-3xl pb-5 uppercase">
        Works with Popular JavaScript Frameworks
      </h2>
      <div className="grid grid-cols-3 md:grid-cols-3">
        {frameworks.map((framework, i) => (
          <div key={i}
               className="bg-white dark:bg-gray-900 m-5 rounded-lg drop-shadow-lg p-2 lg:p-5">
            <Image src={framework.logo} alt={framework.name + ' Logo'} width="150" height="150"/>
          </div>
        ))}
      </div>
    </div>
  )
}
function Pricing() {
  return (
    <div className="mb-70 lg:px-20 text-center">
      <h2 id="pricing-support" className="text-3xl pb-5 uppercase">
        Pricing & Support
      </h2>
      <p className="text-xl">
        WPGraphQL for Advanced Custom Fields is a <strong>FREE</strong> open-source WordPress
        plugin. The code is available on <a href="https://github.com/wp-graphql/wp-graphql-acf"
                                            rel="noreferrer" target="_blank">Github</a>. Support and
        feature requests are
        handled through <a href="https://github.com/wp-graphql/wp-graphql-acf/issues" rel="noreferrer"
                           target="_blank">issues</a>. For general questions about the plugin,
        visit the <Link
        href="/discord">WPGraphQL
        Discord</Link>.
      </p>
    </div>
  )
}

function Faq() {
  const questions = [
    {
      question: "What is included in support?",
      answer: `
      Support is limited to usage of the WPGraphQL for Advanced Custom Fields. If you need support for things such as learning best practices of
      implementing GraphQL at your organization, expert advice/consulting
      on a specific project(s), learning how to use WPGraphQL with caching
      clients, such as Apollo, or other needs not directly related to this
      plugin, contact us and we can pair you with an expert.`
    },
    {
      question: "Where can I get support?",
      answer: `
      Support and feature requests are handled through Github issues. For
          general questions about the plugin, visit the WPGraphQL Discord.
      `
    },
    {
      question: "What are the supported ACF Field Locations?",
      answer: `
      WPGraphQL for ACF attempts to automatically map ACF Field Groups assigned to Post Types, Taxonomies, Users, Comments and Menu Items to the Schema.

      More specific rules, such as a Field Group assigned to one specific Post cannot be automatically mapped to the Schema, but there's settings at the ACF Field Group level to configure which Type(s) in the GraphQL Schema the field group should be associated with.
      `
    },
    {
      question: "Are GraphQL Mutations Supported",
      answer: "GraphQL Mutations for ACF Fields are not currently supported."
    },
    {
      question: "Are there any dependencies?",
      answer: "WPGraphQL for Advanced Custom Fields requires the latest versions of WPGraphQL and Advanced Custom Fields. It likely works with older versions, but we're only officially supporting compatibility with the latest."
    }
  ];

  return (
    <div className="mb-70 lg:px-10 text-center">
      <h2 id="faq" className="text-3xl pb-5 uppercase">
        FAQ
      </h2>
      <div className="mx-auto w-full md:max-w-[70%] rounded-2xl ">
        {questions.map((question, i) => (
          <Disclosure key={i}>
            {({open}) => (
              <>
                <Disclosure.Button
                  className="flex w-full justify-between rounded-lg bg-gray-100 dark:bg-slate-800 px-4 py-2 my-2 text-left text-sm font-medium dark:text-slate-100 hover:bg-gray-300 dark:hover:bg-slate-900 focus:outline-none focus-visible:ring focus-visible:ring-orange-500 focus-visible:ring-opacity-75 text-xl">
                  <span>{question.question}</span>
                  <ChevronUpIcon
                    className={`${
                      open ? 'rotate-180 transform' : ''
                    } h-5 w-5 text-gray-500`}
                  />
                </Disclosure.Button>
                <Disclosure.Panel
                  className="px-4 pt-4 pb-2 text-sm text-slate-700 text-lg dark:text-white">
                  {question.answer}
                </Disclosure.Panel>
              </>
            )}
          </Disclosure>
        ))}

      </div>
    </div>
  )
}

function PageContainer({children}) {
  return (
    <div
      className="bg-white dark:bg-slate-700 min-h-[50px] p-10 md:p-20 w-[80%] drop-shadow mx-auto -mt-[100px] mb-[50px] prose dark:prose-dark">
      {children}
    </div>
  )
}

function Acf() {
  return (
    <SiteLayout>
      <main className="content">
        <AcfHero/>
        <PageContainer>
          <HowItWorks/>
          <SupportedFields/>
          <Why/>
          <WorksWithJS/>
          <Pricing/>
          <Faq/>
        </PageContainer>
      </main>
    </SiteLayout>
  )
}

export default Acf;

export async function getStaticProps() {
  const client = getApolloClient()
  await client.query({query: GET_NAV_MENU})
  return addApolloState(client, {
    props: {
      revalidate: 30
    }
  })
}
