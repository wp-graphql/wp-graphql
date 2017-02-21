WPGraphQL 
[![Build Status](https://travis-ci.org/wp-graphql/wp-graphql.svg?branch=master)](https://travis-ci.org/wp-graphql/wp-graphql) [![Coverage Status](https://coveralls.io/repos/github/wp-graphql/wp-graphql/badge.svg?branch=master)](https://coveralls.io/github/wp-graphql/wp-graphql?branch=master)
=============

GraphQL API for WordPress.

##Installing
Install the plugin like any WP Plugin, then <a href="https://lmgtfy.com/?q=wordpress+flush+permalinks" target="_blank">flush your permalinks</a>.

##Overview
This plugin brings the power of GraphQL to WordPress.

<a href="https://graphql.org" target="_blank">GraphQL</a> is a query language spec that was open sourced by Facebook® in 
2015, and has been used in production by Facebook® since 2012.

GraphQL has some similarities to REST in that it exposes an HTTP endpoint where requests can be sent and a JSON response is
returned. However, where REST has a different endpoint per resource, GraphQL has just a single endpoint and the
data returned isn't implicit, but rather explicit and matches the shape of the request. 

A REST API is implicit, meaning that the data coming back from an endpoint is implied. An endpoint such as `/Posts/` implies 
that the data I will retrieve is data related to Post objects, but beyond that it's hard to know exactly what will be
returned. It might be more data than I need or might not be the data I need at all. 

GraphQL is explicit, meaning that you ask for the data you want and you get the data back in the same shape that it was asked for.

Additionally, where REST requires multiple HTTP requests for related data, GraphQL allows related data to be queried and retrieved
in a single request, and again, in the same shape of the request without any worry of over or under-fetching data.

## GraphiQL API Explorer
_GraphiQL_ is a fantastic GraphQL API Explorer / IDE. There are various versions of _GraphiQL_
that you can find, including a <a href="https://chrome.google.com/webstore/detail/chromeiql/fkkiamalmpiidkljmicmjfbieiclmeij?hl=en">Chrome Extension</a> but
my recommendation is the _GraphiQL_ desktop app below:

- <a href="https://github.com/skevy/graphiql-app">Download the GraphiQL Desktop App</a>
    - Once the app is downloaded and installed, open the App.
    - Set the `GraphQL Endpoint` to `http://yoursite.com/graphql`
    - You should now be able to browse the GraphQL Schema via the "Docs" explorer
    at the top right. 
    - On the left side, you can execute GraphQL Queries
    
    <img src="https://github.com/wp-graphql/wp-graphql/blob/master/docs/img/graphql-docs.gif?raw=true" alt="GraphiQL API Explorer">