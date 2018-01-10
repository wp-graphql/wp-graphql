'use strict';

const path = require("path");
const { syncToAlgolia } = require('./node/algoliasync');

let nav = {};

exports.createPages = ({ boundActionCreators, graphql }) => {
  const { createPage } = boundActionCreators;

  const docTemplate = path.resolve(`src/templates/documentation.js`);

  return graphql(`
    query getAllMarkdown {
      allMarkdownRemark(limit: 1000, sort: {order: ASC, fields: [fileAbsolutePath]}, filter: {fileAbsolutePath: {regex: "//docs//"}}) {
        edges {
          previous {
            id
            frontmatter {
              title
              description
              path
            }
          }
          node {
            fileAbsolutePath
            id
            shortExcerpt:excerpt(pruneLength:100)
            excerpt(pruneLength:1000)
            html
            timeToRead
            frontmatter {
              title
              description
              path
            }
          }
          next {
            id
            frontmatter {
              title
              description
              path
            }
          }
        }
      }
    }
  `).then(result => {
    if (result.errors) {
      return Promise.reject(result.errors);
    }

    /**
     * Get all the pages from the GraphQL Request
     */
    const allPages = result.data.allMarkdownRemark.edges;

    /**
     * Creates the navigation
     */
    allPages.map(addNavItem);

    console.log( 'Syncing to Algolia...' );
    syncToAlgolia(result.data);


    /**
     * Creates the pages
     */
    allPages.forEach(({ node, next, previous } ) => {

      let path = node.frontmatter && node.frontmatter.path ? node.frontmatter.path : '/';

      if ( node.frontmatter && node.frontmatter.path ) {

        /**
         * Create the page, passing context that can be used
         * by GraphQL Variables in page level GraphQL Queries
         */
        createPage({
          path: path,
          component: docTemplate,
          context: {
            path: path,
            nav: nav,
            node: node,
            next: next,
            previous: previous
          },
        });

      }

    });
  });
};

/**
 * Add nav item to the navigation
 * @param obj
 * @param i
 */
function addNavItem( obj, i ){

  if ( obj.node.frontmatter.path ) {

    let splitpath = obj.node.frontmatter.path ? obj.node.frontmatter.path.split('/') : [];

    let newNav = nav;
    for (i=0;i<splitpath.length;i++) {
      let node = {
        title: capitalize(splitpath[i]),
        name: splitpath[i],
        type: 'directory',
        path: obj.node.frontmatter.path
      };
      if (i == splitpath.length - 1) {
        node.title = capitalize(obj.node.frontmatter.title);
        node.path = obj.node.frontmatter.path ? obj.node.frontmatter.path : '/';
        node.description = obj.node.frontmatter.description;
        node.type = 'page';
      }
      newNav[splitpath[i]] = newNav[splitpath[i]] || node;
      newNav[splitpath[i]].children = newNav[splitpath[i]].children || {};
      newNav = newNav[splitpath[i]].children;
    }

  }

}

function capitalize(string) {
  return string && string[0].toUpperCase() + string.slice(1);
}