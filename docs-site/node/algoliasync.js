const algoliasearch = require('algoliasearch')
//
// query getAllMarkdown {
//   allMarkdownRemark(limit: 1000, sort: {order: ASC, fields: [fileAbsolutePath]}, filter: {fileAbsolutePath: {regex: "//docs//"}}) {
//     edges {
//       previous {
//         id
//         frontmatter {
//           title
//           description
//           path
//         }
//       }
//       node {
//         fileAbsolutePath
//         id
//         html
//         timeToRead
//         frontmatter {
//           title
//           description
//           path
//         }
//       }
//       next {
//         id
//         frontmatter {
//           title
//           description
//           path
//         }
//       }
//     }
//   }
// }
//
// request('http://localhost:8000/___graphql', query)
//   .then(data => {
//   })

module.exports = {
  syncToAlgolia: function syncToAlgolia(data) {
    const client = algoliasearch('0OQW7P3CWR', '8e69991be2fdbcfc9461bfb376644eb2')
    const index = client.initIndex('wpgraphqldocs')

    const objects = data.allMarkdownRemark.edges
      .map(edge => edge.node)
      .map(node => ({
        path: node.frontmatter.path,
        shortExcerpt: node.shortExcerpt,
        title: node.frontmatter.title,
        objectID: node.frontmatter.path,
        body: node.excerpt
      }))

    index.clearIndex((clearErr, clearContent) => {
      index.saveObjects(objects, (err, content) => {
        if (!err) {
          console.log(`Successfully synced ${objects.length} items to Algolia`)
        } else {
          console.error(`Error while syncing to Algolia`, err)
        }
      })
    })
  }
}