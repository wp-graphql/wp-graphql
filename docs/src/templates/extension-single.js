import React, {Fragment, useRef, useState, createContext, useContext} from 'react'
import PropTypes from 'prop-types'
import { graphql, navigate, Link } from 'gatsby'
import SEO from 'gatsby-theme-apollo-docs/src/components/seo'
import CodeBlock from 'gatsby-theme-apollo-docs/src/components/code-block'
import useMount from 'react-use/lib/useMount';
import styled from '@emotion/styled'
import { ContentWrapper } from 'gatsby-theme-apollo-core'
import { Helmet } from 'react-helmet'
import SectionNav from 'gatsby-theme-apollo-docs/src/components/section-nav';
import rehypeReact from 'rehype-react'
import themeOptions from '../../apolloThemeOptions';
import { PageNav } from 'gatsby-theme-apollo-core';
import {IconGithub} from '@apollo/space-kit/icons/IconGithub';

import {
    breakpoints,
    colors,
    headerHeight,
    smallCaps
} from 'gatsby-theme-apollo-core';

const StyledContentWrapper = styled(ContentWrapper)({
    paddingBottom: 0,
    img: {
        maxWidth: '100%',
    }
});

const Container = styled.div({
    display: 'flex',
    alignItems: 'flex-start',
    maxWidth: 1200
});

const MainContent = styled.main({
    flexGrow: 1,
    width: 0,
    maxWidth: '100ch'
});

const tableBorder = `1px solid ${colors.divider}`;
const table = {
    marginBottom: '1.45rem',
    border: tableBorder,
    borderSpacing: 0,
    borderRadius: 4,
    [['th', 'td']]: {
        padding: 16,
        borderBottom: tableBorder
    },
    'tbody tr:last-child td': {
        border: 0
    },
    th: {
        ...smallCaps,
        fontSize: 13,
        fontWeight: 'normal',
        color: colors.text2,
        textAlign: 'inherit'
    },
    td: {
        verticalAlign: 'top',
        code: {
            whiteSpace: 'normal'
        }
    }
};

const BodyContent = styled.div({
    // style all anchors with an href and no prior classes
    // this helps avoid anchors with names and styled buttons
    'a[href]:not([class])': {
        color: colors.primary,
        textDecoration: 'none',
        ':hover': {
            textDecoration: 'underline'
        },
        code: {
            color: 'inherit'
        }
    },
    [['h1', 'h2', 'h3', 'h4', 'h5', 'h6']]: {
        '&[id]::before': {
            // inspired by https://css-tricks.com/hash-tag-links-padding/
            content: "''",
            display: 'block',
            marginTop: -headerHeight,
            height: headerHeight,
            visibility: 'hidden',
            pointerEvents: 'none'
        },
        ':not(:hover) a svg': {
            visibility: 'hidden'
        },
        'a.anchor': {
            ':hover': {
                opacity: colors.hoverOpacity
            },
            svg: {
                fill: colors.primary
            }
        }
    },
    [['h2', 'h3', 'h4']]: {
        ':not(:first-child)': {
            marginTop: 56
        }
    },
    img: {
        display: 'block',
        maxWidth: '100%',
        margin: '0 auto'
    },
    table
});

const Aside = styled.aside({
    display: 'flex',
    flexDirection: 'column',
    flexShrink: 0,
    width: 260,
    maxHeight: `calc(100vh - ${headerHeight}px)`,
    marginTop: -36,
    marginLeft: 'auto',
    padding: '40px 56px',
    paddingRight: 0,
    position: 'sticky',
    top: headerHeight,
    [breakpoints.lg]: {
        display: 'none'
    },
    [breakpoints.md]: {
        display: 'block'
    },
    [breakpoints.sm]: {
        display: 'none'
    }
});

const AsideHeading = styled.h4({
    fontWeight: 600
});

const CustomLinkContext = createContext()

function CustomLink(props) {
    const { pathPrefix, baseUrl } = useContext(CustomLinkContext);

    const linkProps = { ...props };
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
};

const components = {
    pre: CodeBlock,
    a: CustomLink,
};

const renderAst = new rehypeReact({
    createElement: React.createElement,
    components,
}).Compiler

const AsideLinkWrapper = styled.h5({
    display: 'flex',
    marginBottom: 0,
    ':not(:last-child)': {
        marginBottom: 16
    }
});

const AsideLinkInner = styled.a({
    display: 'flex',
    alignItems: 'center',
    color: colors.text2,
    textDecoration: 'none',
    ':hover': {
        color: colors.text3
    },
    svg: {
        width: 20,
        height: 20,
        marginRight: 6,
        fill: 'currentColor'
    }
});

function AsideLink(props) {
    return (
        <AsideLinkWrapper>
            <AsideLinkInner target="_blank" rel="noopener noreferrer" {...props} />
        </AsideLinkWrapper>
    );
}

const ExtensionSingle = props => {
    const contentRef = useRef(null);
    const [imagesToLoad, setImagesToLoad] = useState(0);
    const [imagesLoaded, setImagesLoaded] = useState(0);
    const { data, pageContext } = props;
    const { site, wpGraphQlExtension, nextExtension, prevExtension } = data;
    useMount(() => {
        if (props.hash) {
            // turn numbers at the beginning of the hash to unicode
            // see https://stackoverflow.com/a/20306237/8190832
            const hash = props.hash.toLowerCase().replace(/^#(\d)/, '#\\3$1 ');
            try {
                const hashElement = contentRef.current.querySelector(hash);
                if (hashElement) {
                    hashElement.scrollIntoView();
                }
            } catch (error) {
                // let errors pass
            }
        }

        let toLoad = 0;
        const images = contentRef.current.querySelectorAll('img');
        images.forEach(image => {
            if (!image.complete) {
                image.addEventListener('load', handleImageLoad);
                toLoad++;
            }
        });

        setImagesToLoad(toLoad);
    });

    function handleImageLoad() {
        setImagesLoaded(prevImagesLoaded => prevImagesLoaded + 1);
    }

    const editLink = (
        <AsideLink href={wpGraphQlExtension.url}>
            <IconGithub /> View on GitHub
        </AsideLink>
    );

    return(
        <Fragment>
            <Helmet>
                <title>{wpGraphQlExtension.name}</title>
            </Helmet>
            <SEO
                title={wpGraphQlExtension.name}
                description={wpGraphQlExtension.descriptionHTML}
                siteName={site.siteMetadata.title}
                twitterHandle={site.siteMetadata.twitterHandle}
            />
            <StyledContentWrapper>
                <div className="header-wrapper">
                    <h1>{wpGraphQlExtension.name}</h1>
                    <h3 dangerouslySetInnerHTML={{__html:wpGraphQlExtension.descriptionHTML}} />
                    <hr />
                </div>
                <Container>
                    <MainContent>
                        <BodyContent ref={contentRef} className="content-wrapper">
                            <CustomLinkContext.Provider
                                value={{
                                    pathPrefix: site.pathPrefix,
                                    baseUrl: themeOptions.baseUrl,
                                }}
                            >
                                <div>
                                    {renderAst(wpGraphQlExtension.readme.childMarkdownRemark.htmlAst)}
                                </div>
                            </CustomLinkContext.Provider>
                        </BodyContent>
                        <PageNav
                            prevPage={prevExtension}
                            nextPage={nextExtension}
                        />
                    </MainContent>
                    <Aside>
                        <Link to={`/extensions/all`}>{`< Back to All Extensions`}</Link>
                        <hr/>
                        <AsideHeading>{pageContext.name}</AsideHeading>
                        {wpGraphQlExtension.readme.childMarkdownRemark.headings.length > 0 && (
                            <SectionNav
                                headings={wpGraphQlExtension.readme.childMarkdownRemark.headings}
                                contentRef={contentRef}
                                imagesLoaded={imagesLoaded === imagesToLoad}
                            />
                        )}
                        {editLink}

                    </Aside>
                </Container>
            </StyledContentWrapper>
        </Fragment>

    )
};

export default ExtensionSingle

export const query = graphql`
query GET_EXTENSION($id:String, $nextId: String, $prevId: String) {
  site {
    pathPrefix
    siteMetadata {
      title
      description
      twitterHandle
    }
  }
  wpGraphQlExtension(id: {eq: $id}) {
    id
    name
    descriptionHTML
    url
    owner {
      login
    }
    readme {
      id
      childMarkdownRemark {
        id
        timeToRead
        htmlAst
        headings {
          value
          depth
        }
      }
    }
  }
  nextExtension: wpGraphQlExtension(id: {eq: $nextId}) {
    id
    title: name
    path: slug
  }
  prevExtension: wpGraphQlExtension(id: {eq: $prevId}) {
    id
    title: name
    path: slug
  }
}
`;
