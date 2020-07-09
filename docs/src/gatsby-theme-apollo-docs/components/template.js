import CodeBlock from 'gatsby-theme-apollo-docs/src/components/code-block'
import MDXRenderer from 'gatsby-plugin-mdx/mdx-renderer'
import PageContent from './page-content'
import PageHeader from './page-header'
import PropTypes from 'prop-types'
import React, { Fragment, createContext, useContext } from 'react'
import SEO from '../../components/Seo/index'
import rehypeReact from 'rehype-react'
import styled from '@emotion/styled'
import { ContentWrapper } from 'gatsby-theme-apollo-core'
import { MDXProvider } from '@mdx-js/react'
import { TypescriptApiBoxContext } from 'gatsby-theme-apollo-docs/src/components/typescript-api-box'
import { graphql, navigate } from 'gatsby'
import '../../components/style.css'

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

  const { hash, pathname } = props.location

  const { file, site } = props.data

  let { frontmatter, headings, fields } =
    file.childMarkdownRemark || file.childMdx


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
      <SEO
        title={frontmatter.title}
        description={frontmatter.description || site.siteMetadata.description}
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

export const pageQuery = graphql`
  query TemplatePageQuery($id: String) {
    site {
      pathPrefix
      siteMetadata {
        title
        description
        twitterHandle
      }
    }
    file(id: { eq: $id }) {
      childMarkdownRemark {
        frontmatter {
          title
          description
        }
        headings {
          value
          depth
        }
        fields {
          image
          graphManagerUrl
        }
        htmlAst
      }
      childMdx {
        frontmatter {
          title
          description
        }
        headings {
          value
          depth
        }
        fields {
          image
          graphManagerUrl
        }
        body
      }
    }
  }
`
