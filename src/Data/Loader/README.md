# Data Loaders

This directory contains classes related to data loading.

The concept comes from the formal DataLoader library. 

WordPress already does some batching and caching, so implementing DataLoader straight
up actually leads to _increased_ queries in WPGraphQL, so this approach
makes use of some custom batch load functions and Deferred resolvers, provided
by GraphQL-PHP to reduce the number of queries needed in many cases.  
