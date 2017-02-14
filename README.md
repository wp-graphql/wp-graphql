#WP GraphQL

###Overview
WPGraphQL brings the power of <a href="http://graphql.org/" target="_blank">GraphQL</a> to WordPress.

GraphQL is a Query Language spec open sourced and maintained by Facebook.

As stated on GraphQL.org: "GraphQL provides a complete and understandable description of the data in your API, gives clients the power to ask for exactly what they need and nothing more, makes it easier to evolve APIs over time, and enables powerful developer tools"

In simple terms GraphQL is conceptually similar to REST, in that you ask for data and get a JSON response, however it differs from REST quite substantially, in that there's only a single endpoint, instead of different endpoints for each data type. Additionally, multiple types of data can be retrieved from a single GraphQL API request, and it's back-end agnostic, meaning that the data being returned can come from anywhere. For example, one GraphQL Query could provide a response that consisted of data from a Post, a Page, an Author, a Term and even a remote call to another remote API, such as Google Analytics. . .the sky is the limit.

###Plugin Goals
The goal of this plugin is to provide full GraphQL parity with WordPress Core, meaning that any data that can be managed in a vanilla WordPress install should be manageable through this plugin via a GraphQL API.

Additionally, there should be appropriate entry points for other plugins/themes to extend the GraphQL functionality to make additional data accessible via GraphQL.

### NOT PRODUCTION READY!
This is NOT production ready yet and is currently undergoing a major refactor. Feel free to install and play with it, but things WILL break in the near future

### Installation
Install the plugin like any WP Plugin, then <a href="https://lmgtfy.com/?q=wordpress+flush+permalinks" target="_blank">flush your permalinks</a>.

### Dev Tools
Go get the GraphiQL (ChromiQL) Chrome Extension here: https://chrome.google.com/webstore/detail/chromeiql/fkkiamalmpiidkljmicmjfbieiclmeij

Open the extension and set the URL to your-site.com/graphql

Check the docs in the top right to see what Queries/Mutations are available.