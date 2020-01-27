exports.createSchemaCustomization = ({actions}) => {
    actions.createTypes(`
        type File implements Node @infer {
            childMarkdownRemark: MarkdownRemark
        }

        type MarkdownRemark implements Node @infer {
            frontmatter: MarkdownRemarkFrontmatter
            fields: MarkdownRemarkFields
        }

        type MarkdownRemarkFields {
            image: String
            version: String
            slug: String
            graphManagerUrl: String
        }

        type MarkdownRemarkFrontmatter {
            title: String
            subtitle: String
            description: String
        }
    `)
}

require('es6-promise').polyfill();
require('isomorphic-fetch');
const path = require(`path`);
const slash = require(`slash`);
let activeEnv = process.env.ACTIVE_ENV || process.env.NODE_ENV || 'development';
require('dotenv').config({
    path: `.env.${activeEnv}`,
});

const themeOptions = require('./apolloThemeOptions');
const extensions = require(`./extensions`);

exports.sourceNodes = async ({boundActionCreators, createNodeId, createContentDigest}) => {
    await Promise.all(
        extensions && extensions.map(async extension => {

            if (extension.owner && extension.repo) {

                const {createNode} = boundActionCreators;

                const GET_REPO_QUERY = `
                query MyQuery($owner:String! $name:String!) {
                    repository(name: $name, owner: $owner) {
                      id
                      createdAt
                      updatedAt
                      name
                      descriptionHTML
                      url
                      fundingLinks {
                        platform
                      }
                      owner {
                        login
                      }
                      collaborators {
                        totalCount
                      }
                      stargazers {
                        totalCount
                      }
                      issues(states: [OPEN]) {
                        totalCount
                      }
                      openPullRequests: pullRequests(states: [OPEN]) {
                        totalCount
                      }
                      object(expression: "master:README.md") {
                        ...on Blob {
                          text
                        }
                      }
                    }
                }
                `;

                await fetch(`https://api.github.com/graphql`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${process.env.GITHUB_TOKEN}`,
                    },
                    body: JSON.stringify({
                        query: GET_REPO_QUERY,
                        variables: {
                            owner: extension.owner,
                            name: extension.repo,
                        }
                    })
                }).then(res => {
                    return res.json();
                }).then(res => {

                    const {data: {repository}} = res;

                    if (repository && repository.id) {

                        const parentId = createNodeId(`plugin ${repository.id}`);
                        const readmeNode = {
                            id: createNodeId(`readme ${repository.id}`),
                            parent: parentId,
                            slug: `/extensions/en/${repository.name}`,
                            children: [],
                            internal: {
                                type: `WPGraphQLExtensionReadme`,
                                mediaType: `text/markdown`,
                                content: repository.object && repository.object.text ? repository.object.text : ``,
                            },
                        };

                        readmeNode.internal.contentDigest = createContentDigest(readmeNode);

                        const node = {
                            ...repository,
                            deprecated: false,
                            created: new Date(repository.createdAt),
                            modified: new Date(repository.updatedAt),
                            id: parentId,
                            parent: null,
                            children: [],
                            slug: `/extensions/${repository.name}`,
                            readme___NODE: readmeNode.id,
                            title: `${repository.name}`,
                            internal: {
                                type: `WPGraphQLExtension`,
                                content: repository.object && repository.object.text ? repository.object.text : ``,
                            }
                        };

                        node.internal.contentDigest = createContentDigest(node);
                        createNode(readmeNode);
                        createNode(node);
                    }

                });

            }

        })
    );

};

function getPageFromEdge({node}) {
    return node.childMarkdownRemark || node.childMdx;
}

function getSidebarContents(sidebarCategories, edges, version, contentDir) {
    return Object.keys(sidebarCategories).map(key => ({
        title: key === 'null' ? null : key,
        pages: sidebarCategories[key]
            .map(linkPath => {
                const match = linkPath.match(/^\[(.+)\]\((https?:\/\/.+)\)$/);
                if (match) {
                    return {
                        anchor: true,
                        title: match[1],
                        path: match[2]
                    };
                }

                const edge = edges.find(edge => {

                    const {relativePath} = edge.node;

                    return (
                        relativePath
                            .slice(0, relativePath.lastIndexOf('.'))
                            .replace(new RegExp(`^${contentDir}/`), '') === linkPath
                    );
                });

                if (!edge) {
                    return null;
                }

                const {frontmatter, fields} = getPageFromEdge(edge);
                return {
                    title: frontmatter.title,
                    path: fields.slug
                };
            })
            .filter(Boolean)
    }));
}

const pageFragment = `
  internal {
    type
  }
  frontmatter {
    title
  }
  fields {
    slug
    version
  }
`;

exports.createPages = async ({graphql, actions}) => {
    const {createPage} = actions;
    const {
        contentDir = 'docs/source',
        sidebarCategories,
        localVersion,
        defaultVersion,
    } = themeOptions;
    const {data} = await graphql(`
    {
      allWpGraphQlExtension(sort: {fields: [stargazers___totalCount], order: DESC}) {
        edges {
            next {
              id
            }
            previous {
              id
            }
            node {
              id
              name
              slug
              readme {
                  childMarkdownRemark {
                    ${pageFragment}
                  }
                  childMdx {
                    ${pageFragment}
                  }
              }
            }
        }
      }
      allFile(filter: {extension: {in: ["md", "mdx"]}}) {
        edges {
          node {
            id
            relativePath
            childMarkdownRemark {
              ${pageFragment}
            }
            childMdx {
              ${pageFragment}
            }
          }
        }
      }
    }
    `);

    const {allWpGraphQlExtension, allFile} = data;
    const {edges} = allFile;
    const mainVersion = localVersion || defaultVersion;
    const sidebarContents = {
        [mainVersion]: getSidebarContents(
            sidebarCategories,
            edges,
            mainVersion,
            contentDir
        )
    };

    allWpGraphQlExtension && allWpGraphQlExtension.edges && allWpGraphQlExtension.edges.map(edge => {
        const {node, previous, next} = edge;
        const extensionTemplate = path.resolve(`src/templates/extension-single.js`);
        if (node && node.slug) {
            createPage({
                path: `${node.slug}`,
                component: slash(extensionTemplate),
                context: {
                    slug: node.slug,
                    id: node.id,
                    name: node.name,
                    sidebarContents: sidebarContents[mainVersion],
                    nextId: next && next.id ? next.id : null,
                    prevId: previous && previous.id ? previous.id : null,
                }
            });
        }

    });

};
