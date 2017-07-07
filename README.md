![Logo](https://www.wpgraphql.com/wp-content/uploads/2017/06/wpgraphql-logo-250x.png)

# WPGraphQL 

<a href="https://www.wpgraphql.com" target="_blank">Website</a> • <a href="https://www.gitbook.com/book/wp-graphql/wp-graphql/" target="_blank">Docs</a> • <a href="https://wp-graphql.github.io/wp-graphql-api-docs/" target="_blank">ApiGen Code Docs</a>

GraphQL API for WordPress.

[![Build Status](https://travis-ci.org/wp-graphql/wp-graphql.svg?branch=master)](https://travis-ci.org/wp-graphql/wp-graphql) [![Coverage Status](https://coveralls.io/repos/github/wp-graphql/wp-graphql/badge.svg?branch=master)](https://coveralls.io/github/wp-graphql/wp-graphql?branch=master)
[![WPGraphQL on Slack](https://slackin-wpgraphql.herokuapp.com/badge.svg)](https://slackin-wpgraphql.herokuapp.com/)

------

## Installing
Install and activate WPGraphQL like any WP Plugin, then <a href="https://lmgtfy.com/?q=wordpress+flush+permalinks" target="_blank">flush your permalinks</a>.

## Overview
This plugin brings the power of GraphQL to WordPress.

<a href="https://graphql.org" target="_blank">GraphQL</a> is a query language spec that was open sourced by Facebook® in 
2015, and has been used in production by Facebook® since 2012.

GraphQL has some similarities to REST in that it exposes an HTTP endpoint where requests can be sent and a JSON response 
is returned. However, where REST has a different endpoint per resource, GraphQL has just a single endpoint and the
data returned isn't implicit, but rather explicit and matches the shape of the request. 

A REST API is implicit, meaning that the data coming back from an endpoint is implied. An endpoint such as `/posts/` 
implies that the data I will retrieve is data related to Post objects, but beyond that it's hard to know exactly what 
will be returned. It might be more data than I need or might not be the data I need at all. 

GraphQL is explicit, meaning that you ask for the data you want and you get the data back in the same shape that it was 
asked for.

Additionally, where REST requires multiple HTTP requests for related data, GraphQL allows related data to be queried and 
retrieved in a single request, and again, in the same shape of the request without any worry of over or under-fetching 
data.

GraphQL also provides rich introspection, allowing for queries to be run to find out details about the Schema, which is
how powerful dev tools, such as _GraphiQl_ have been able to be created.

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
    
    <img src="https://github.com/wp-graphql/wp-graphql/blob/master/img/graphql-docs.gif?raw=true" alt="GraphiQL API Explorer">

## Example Queries
Here's some example queries to get you going with experimenting. You can simply copy and paste these into the GraphiQL 
query inspector and you'll receive a response of data in the same shape as the request. There's also a video walkthrough 
of GraphQL queries using WPGraphQL here: 
<a href="https://www.wpgraphql.com/2017/02/17/intro-to-wpgraphql-queries/">https://www.wpgraphql.com/2017/02/17/intro-to-wpgraphql-queries/</a>

#### Get a list of posts:
```
{
    posts{
        edges{
            node{
                id
                title
                link
                slug
                date
            }
        }
    }
}
```

#### Get a list of category terms:
```
{
  categories{
    edges{
      node{
        id
        name
        link
        slug
      }
    }
  }
}
```

#### Get a list of posts, with the categories it's attached to:


```
{
  posts {
    edges {
      node {
        id
        title
        link
        slug
        date
        categories {
          edges {
            node {
              id
              link
              slug
            }
          }
        }
      }
    }
  }
}

```

## POSSIBLE BREAKING CHANGES
Please note that as the plugin continues to take shape, there might be breaking changes at any point. Once the plugin reaches a stable 1.0.0 release, breaking changes should be minimized and communicated appropriately if they are required.

## Extensions and/or plugins with WPGraphQL support
There are a few extensions available as well:
- https://github.com/wp-graphql/wp-graphql-meta-query 
Adds support for meta_query
- https://github.com/wp-graphql/wp-graphql-tax-query
Adds support for tax_query
- https://github.com/roborourke/wp-graphql-meta
Adds support for automatically exposing fields registered using the `register_meta` API to GraphQL calls
- https://github.com/dfmedia/wp-term-timestamps
Simple plugin that stores created and modified timestamps along with the user ID as term_meta when terms are created and updated. Adds `created` and `modified` fields to TermObject's if this plugin is active alongside WPGraphQL.


## Unit Testing
To run unit tests during development, you'll first need a testing database that you'd like to use. 

Open the command line and navigate to the plugin's directory. From within the plugin directory, run the following 
commands to install the test suite, filling in the parameters appropriately to link to an existing test database or to
create a new test database:

`composer install`

`bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]`

NOTE: You'll want the test database to be a true test database, not a database with valuable, existing information, as 
the tests will create new data and clear out data, and you don't want to cause issues with a database you're actually 
using for projects.

## Shout Outs
This plugin brings the power of GraphQL (http://graphql.org/) to WordPress.

This plugin is based on the hard work of Jason Bahl and Ryan Kanner of Digital First Media (https://github.com/dfmedia),
and Edwin Cromley of BE-Webdesign (https://github.com/BE-Webdesign).

The plugin is built on top of the graphql-php library by Webonyx (https://github.com/webonyx/graphql-php) and makes use 
of the graphql-relay-php library by Ivome (https://github.com/ivome/graphql-relay-php/)

Special thanks to Digital First Media (http://digitalfirstmedia.com) for allocating development resources to push the 
project forward.

Some of the concepts and code are based on the WordPress Rest API. Much love to the folks (https://github.com/orgs/WP-API/people) 
that put their blood, sweat and tears into the WP-API project, as it's been huge in moving WordPress forward as a 
platform and helped inspire and direct the development of WPGraphQL.

Much love to Facebook® for open sourcing the GraphQL spec (https://facebook.github.io/graphql/), the amazing GraphiQL 
dev tools (https://github.com/graphql/graphiql), and maintaining the JavaScript GraphQL reference 
implementation (https://github.com/graphql/graphql-js)

Much love to Apollo (Meteor Development Group) for their work on driving GraphQL forward and providing a lot of insight 
into how to design GraphQL schemas, etc. Check them out: http://www.apollodata.com/
