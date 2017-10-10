![Logo](https://www.wpgraphql.com/wp-content/uploads/2017/06/wpgraphql-logo-e1502819081849.png)

# WPGraphQL 

<a href="https://www.wpgraphql.com" target="_blank">Website</a> • <a href="https://wp-graphql.gitbooks.io/wp-graphql/content/" target="_blank">Docs</a> • <a href="https://wp-graphql.github.io/wp-graphql-api-docs/" target="_blank">ApiGen Code Docs</a>

GraphQL API for WordPress.

[![Build Status](https://travis-ci.org/wp-graphql/wp-graphql.svg?branch=master)](https://travis-ci.org/wp-graphql/wp-graphql)
[![Coverage Status](https://coveralls.io/repos/github/wp-graphql/wp-graphql/badge.svg?branch=master)](https://coveralls.io/github/wp-graphql/wp-graphql?branch=master)
[![WPGraphQL on Slack](https://slackin-dsxwfpqeja.now.sh/badge.svg)](https://slackin-dsxwfpqeja.now.sh/)

------

## Quick Install
Download and install like any WordPress plugin.

## Documentation

Documentation can be found [on the Wiki](https://github.com/wp-graphql/wp-graphql/wiki) on this repository.

- Requires PHP 5.5+
- Requires WordPress 4.7+

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

## POSSIBLE BREAKING CHANGES
Please note that as the plugin continues to take shape, there might be breaking changes at any point. Once the plugin reaches a stable 1.0.0 release, breaking changes should be minimized and communicated appropriately if they are required.

## Unit Testing and Code Coverage 

Before anything is merged into the WPGraphQL code base it must pass all tests and have 100% code coverage. Travis-CI and Coveralls will check this when you create a pull request to the WPGraphQL repo. However, before that happens, you should ensure all of these requirements are met locally. The following will help you set up both testing and code coverage in your local environment.

### Prerequisites
To run unit tests and code coverage during development you'll need the following:

* [Composer](https://getcomposer.org/doc/00-intro.md)
    * [phpunit](https://phpunit.de/) 
        * `composer global require phpunit/phpunit`
    * [php-coveralls](https://github.com/php-coveralls/php-coveralls)
        * `composer global require php-coveralls/php-coveralls`
* [Xdebug](https://xdebug.org/docs/install)

### Test Database
Open the command line, then from the root of your WordPress install, navigate to the `wp-content/plugins/wp-graphql` directory. From within the WPGraphQL plugin's directory run the following commands to install the test suite, filling in the parameters appropriately to link to an existing test database or to
create a new test database:

`bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]`

For example: 

`bin/install-wp-tests.sh wp-tests-docs root password localhost latest`

NOTE: You'll want the test database to be a true test database, not a database with valuable, existing information, as 
the tests will create new data and clear out data, and you don't want to cause issues with a database you're actually 
using for projects.

DEBUGGING: If you have run this command before in another branch you may already have a local copy of WordPress downloaded in your `/private/tmp` directory. If this is the case, please remove it and then run the install script again. Without removing this you may receive an error when running phpunit.

### Running the Tests and Checking Coverage
Now that we have the prerequisites installed we can run the tests and check our code coverage. The results of our tests will appear on the command line, however our code coverage information is stored in the coverage directory in the form of HTML. This HTML is generated by phpunit when it runs the tests by passing the `--coverage-html` flag.

`phpunit --coverage-html ./coverage`

The easiest way to check your code coverage is to drag, or right click and open, the `index.html` file from the coverage directory (`wp-graphql/coverage/index.html`) in your browser. This should render a dashboard that will allow you to view the code coverage of all files in the WPGraphQL repo.

### Running Individual Files 
As you'll note, running all of the tests in the entire test suite can be time consuming. If you would like to run only one test file instead of all of them, simply pass the test file you're trying to test, like so:

`phpunit --coverage-html ./coverage ./tests/test-media-item-mutations.php`

## Shout Outs
This plugin brings the power of GraphQL (http://graphql.org/) to WordPress.

This plugin is based on the hard work of Jason Bahl, Ryan Kanner, Hughie Devore and Peter Pak of Digital First Media (https://github.com/dfmedia),
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
