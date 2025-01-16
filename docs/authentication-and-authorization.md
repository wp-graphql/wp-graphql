---
uri: "/docs/authentication-and-authorization/"
title: "Authentication and Authorization"
---

## Understanding GraphQL Operations

Before diving into authentication and authorization, it's important to understand how GraphQL operations work in WPGraphQL.

GraphQL has two main operation types:
- **Queries**: Used for fetching data
- **Mutations**: Used for modifying data 

While mutations use the `mutation` keyword and must be processed synchronously (one after another), both queries and mutations are fundamentally similar - they map input to resolver functions that interact with WordPress.


## Authentication & Authorization Concepts

- **Authentication**: The process of verifying a user's identity (logging in, validating credentials)
- **Authorization**: The process of verifying what a user can access or modify (permissions)

In WPGraphQL, these processes build on WordPress's existing user and capability systems.

## Authentication Methods

WPGraphQL supports multiple authentication approaches depending on your use case:

### 1. Remote HTTP Requests
For applications making requests to the `/graphql` endpoint, you can use:

- **Application Passwords** (Recommended for WordPress 5.6+)
  - Built into WordPress core
  - Secure token-based authentication
  - [Integration Guide](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)

- **JWT Authentication** 
  - Uses JSON Web Tokens
  - Ideal for headless WordPress applications
  - [WPGraphQL JWT Authentication Plugin](https://github.com/wp-graphql/wp-graphql-jwt-authentication)

- **Basic Authentication**
  - Simple username/password authentication
  - [Basic Auth Plugin](https://github.com/WP-API/Basic-Auth)
  - Note: Only use with SSL/HTTPS connections

- **OAuth1**
  - More complex but very secure
  - [OAuth1 Plugin](https://github.com/WP-API/OAuth1)

### 2. WordPress Admin Requests
When making requests from within the WordPress admin (like WPGraphiQL):
- Uses WordPress's nonce-based authentication
- Automatically authenticated via the user's session
- Example implementation in [WPGraphiQL](https://github.com/wp-graphql/wp-graphiql/blob/82518eafa5f383c5929111431e4a641caace3b57/assets/app/src/App.js#L58-L75)

### 3. Direct PHP Function Calls
When using WPGraphQL programmatically within WordPress:
- Uses the current user's session
- Call `graphql([ 'query' => $query ])` directly
- Inherits WordPress authentication context

## Authorization in WPGraphQL

WPGraphQL implements a granular authorization system:

### Field-Level Authorization
- Each field can have its own authorization rules
- Fields can return null if user lacks permission
- Other fields in the same query still resolve if authorized
- Examples: 
  - `email` in `generalSettings` requires authentication
  - `{ posts( where: { status: DRAFT } ) { nodes { id, title } } }` requires authentication (draft posts are not public)

### Mutation Authorization
- Checks WordPress capabilities before executing
- Respects WordPress roles and permissions
- Examples: 
  - Creating a post checks `publish_posts` capability
