import CodeBlock from 'gatsby-theme-apollo-docs/src/components/code-block'
import MDXRenderer from 'gatsby-plugin-mdx/mdx-renderer'
import PageContent from 'gatsby-theme-apollo-docs/src/components/page-content'
import PageHeader from 'gatsby-theme-apollo-docs/src/components/page-header'
import PropTypes from 'prop-types'
import React, { Fragment, createContext, useContext } from 'react'
import SEO from 'gatsby-theme-apollo-docs/src/components/seo'
import rehypeReact from 'rehype-react'
import styled from '@emotion/styled'
import { ContentWrapper } from 'gatsby-theme-apollo-core'
import { Helmet } from 'react-helmet'
import { MDXProvider } from '@mdx-js/react'
import { TypescriptApiBoxContext } from 'gatsby-theme-apollo-docs/src/components/typescript-api-box'
import { graphql, navigate, useStaticQuery } from 'gatsby'

const StyledContentWrapper = styled(ContentWrapper)({
  paddingBottom: 0,
})

const CustomLinkContext = createContext()

function CustomLink(props) {
  const { pathPrefix, baseUrl } = useContext(CustomLinkContext)

  const linkProps = { ...props }
  if (props.href) {
    if (props.href.startsWith('/')) {
      linkProps.onClick = function handleClick(event) {
        const href = event.target.getAttribute('href')
        if (href.startsWith('/')) {
          event.preventDefault()
          navigate(href.replace(pathPrefix, ''))
        }
      }
    } else if (!props.href.startsWith('#') && !props.href.startsWith(baseUrl)) {
      linkProps.target = '_blank'
      linkProps.rel = 'noopener noreferrer'
    }
  }

  return <a {...linkProps}>{linkProps.children}</a>
}

CustomLink.propTypes = {
  href: PropTypes.string,
}

const components = {
  pre: CodeBlock,
  a: CustomLink,
}

const renderAst = new rehypeReact({
  createElement: React.createElement,
  components,
}).Compiler

export default function Template(props) {
  // since we can't shadow the page query below
  // we need to get all headings of all pages
  // then find the list of headings relevant to this page.
  // This can be removed when this PR is merged and released:
  // https://github.com/gatsbyjs/gatsby/pull/17681
  const { tempAllFile } = useStaticQuery(graphql`
    query TEMPORARY_HEADINGS_QUERY {
      tempAllFile: allFile {
        nodes {
          childMdx {
            frontmatter {
              title
            }
            headings {
              value
              depth
            }
            fields {
              graphManagerUrl
            }
          }
          # childMarkdownRemark {
          #   frontmatter {
          #     title
          #   }
          #   headings {
          #     value
          #     depth
          #   }
          #   fields {
          #     graphManagerUrl
          #   }
          # }
        }
      }
    }
  `)

  const { hash, pathname } = props.location

  const { file, site } = props.data

  let { frontmatter, headings, fields } =
    file.childMarkdownRemark || file.childMdx

  // remove this when
  // https://github.com/gatsbyjs/gatsby/pull/17681 is merged
  // see note above for more info
  const tempHeadingsQueryNode = tempAllFile.nodes.find(node => {
    if (!node.childMarkdownRemark && !node.childMdx) {
      return false
    }

    const availableData = node.childMdx || node.childMarkdownRemark

    return availableData.frontmatter.title === frontmatter.title
  })

  // remove this when
  // https://github.com/gatsbyjs/gatsby/pull/17681 is merged
  // see note above for more info
  const availableTempHeadingsQueryData =
    tempHeadingsQueryNode.childMdx || tempHeadingsQueryNode.childMarkdownRemark

  // remove this when
  // https://github.com/gatsbyjs/gatsby/pull/17681 is merged
  // see note above for more info
  headings = availableTempHeadingsQueryData.headings

  const { title, description, twitterHandle } = site.siteMetadata

  const {
    sidebarContents,
    githubUrl,
    spectrumUrl,
    typescriptApiBox,
    baseUrl,
  } = props.pageContext

  const pages = sidebarContents
    .reduce((acc, { pages }) => acc.concat(pages), [])
    .filter(page => !page.anchor)

  return (
    <Fragment>
      <Helmet>
        <title>{frontmatter.title}</title>
      </Helmet>
      <SEO
        title={frontmatter.title}
        description={frontmatter.description || description}
        siteName={title}
        twitterHandle={twitterHandle}
        baseUrl={baseUrl}
        image={fields.image}
      />
      <StyledContentWrapper>
        <PageHeader {...frontmatter} />
        <hr />
        <PageContent
          title={frontmatter.title}
          graphManagerUrl={fields.graphManagerUrl}
          pathname={pathname}
          pages={pages}
          headings={headings}
          hash={hash}
          githubUrl={githubUrl}
          spectrumUrl={spectrumUrl}
        >
          <CustomLinkContext.Provider
            value={{
              pathPrefix: site.pathPrefix,
              baseUrl,
            }}
          >
            {file.childMdx ? (
              <TypescriptApiBoxContext.Provider value={typescriptApiBox}>
                <MDXProvider components={components}>
                  <MDXRenderer>{file.childMdx.body}</MDXRenderer>
                </MDXProvider>
              </TypescriptApiBoxContext.Provider>
            ) : (
              renderAst(file.childMarkdownRemark.htmlAst)
            )}
          </CustomLinkContext.Provider>
        </PageContent>
      </StyledContentWrapper>
    </Fragment>
  )
}

Template.propTypes = {
  data: PropTypes.object.isRequired,
  pageContext: PropTypes.object.isRequired,
  location: PropTypes.object.isRequired,
}

// export const pageQuery = graphql`
//   query TemplatePageQuery($id: String) {
//     site {
//       pathPrefix
//       siteMetadata {
//         title
//         description
//         twitterHandle
//       }
//     }
//     file(id: { eq: $id }) {
//       childMarkdownRemark {
//         frontmatter {
//           title
//           description
//         }
//         headings {
//           value
//           depth
//         }
//         fields {
//           image
//           graphManagerUrl
//         }
//         htmlAst
//       }
//       childMdx {
//         frontmatter {
//           title
//           description
//         }
//         headings {
//           value
//           depth
//         }
//         fields {
//           image
//           graphManagerUrl
//         }
//         body
//       }
//     }
//   }
// `
