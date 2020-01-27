const themeOptions = require('./apolloThemeOptions');

module.exports = {
  siteMetadata: {
    siteName: `WPGraphQL`,
    title: `WPGraphQL Docs`,
    description: `WPGraphQL (GraphQL for WordPress) documentation.`,
    twitterHandle: `wpgraphql`,
    author: `WPGraphQL`,
  },
  plugins: [
    `gatsby-plugin-sharp`,
    {
      resolve: `gatsby-source-filesystem`,
      options: {
        name: `images`,
        path: `${__dirname}/source/images`,
      },
    },
    `gatsby-transformer-sharp`,
    `gatsby-plugin-sharp`,
    {
      resolve: `gatsby-plugin-manifest`,
      options: {
        name: `wpgraphql-docs`,
        short_name: `wpgraphql`,
        start_url: `/`,
        background_color: `#0E2339`,
        theme_color: `#0E2339`,
        display: `minimal-ui`,
        icon: `source/images/icon.png`,
      },
    },
    {
      resolve: 'gatsby-plugin-antd',
      options: {
        style: false,
      },
    },
    {
      resolve: 'gatsby-theme-apollo-docs',
      options: themeOptions,
    },
    `gatsby-plugin-netlify`,
    `gatsby-plugin-netlify-cache`,
  ],
}
