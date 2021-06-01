---
uri: "/docs/custom-post-types/"
title: "Custom Post Types"
---

## Using Custom Post Types with WPGraphQL

In order to use Custom Post Types with WPGraphQL, you must configure the Post Type to `show_in_graphql` using the following fields:

- **show_in_graphql** (boolean): true or false
- **graphql_single_name** (string): camel case string with no punctuation or spaces. Needs to start with a letter (not a number). Important to be different than the plural name.
- **graphql_plural_name** (string): camel case string with no punctuation or spaces. Needs to start with a letter (not a number). Important to be different than the single name.

## Registering a new Custom Post Type

This is an example of registering a new "docs" post_type and enabling GraphQL Support.

```php
add_action( 'init', function() {
   register_post_type( 'docs', [
      'show_ui' => true,
      'labels'  => [
        //@see https://developer.wordpress.org/themes/functionality/internationalization/
        'menu_name' => __( 'Docs', 'your-textdomain' ),
      ],
      'show_in_graphql' => true,
      'hierarchical' => true,
      'graphql_single_name' => 'document',
      'graphql_plural_name' => 'documents',
   ] );
} );
```

## Filtering an Existing Post Type

If you want to expose a Post Type that you don’t control the registration for, such as a post type registered in a third-party plugin, you can filter the Post Type registration like so:

```php
add_filter( 'register_post_type_args', function( $args, $post_type ) {

  // Change this to the post type you are adding support for
  if ( 'docs' === $post_type ) {
    $args['show_in_graphql'] = true;
    $args['graphql_single_name'] = 'document';
    $args['graphql_plural_name'] = 'documents';
  }

  return $args;

}, 10, 2 );
```

## Using Custom Post Type UI

Custom Post Type UI is a popular WordPress plugin that enables users to register Custom Post Types and Custom Taxonomies from the WordPress dashboard via a user interface. The [WPGraphQL for Custom Post Type UI](/extenstion-plugins/wpgraphql-for-custom-post-type-ui/) plugin provides fields to Custom Post Type UI that allow you to set whether the Post Type or Taxonomy should show in GraphQL, and set the GraphQL Single Name and GraphQL Plural Name.

## Public vs Private Data

WPGraphQL respects WordPress access control policies. If a Post Type is registered as `public => true` then WPGraphQL will expose posts of that post type to public queries. If the post type is registered as `public=>false` the posts of that post type will be exposed only to authenticated users.

## Querying Custom Post Types

Querying content in a Custom Post type is similar to querying [Posts & Pages](/docs/posts-and-pages/). The difference being that the `graphql_single_name` and `graphql_plural_name` will be shown in the Schema.

So, assuming you registered the post type as shown above, with the `graphql_plural_name` of `docs`, you would be able to query like so:

```graphql
{
  docs {
    nodes {
      id
      title
    }
  }
}
```

And if your `graphql_single_name` were `Doc`, you would be able to query a single Doc like so:

```graphql
{
  doc( id: "validIdGoesHere" ) {
    id
    title
  }
}
```

## Post Type Supports & Interfaces

All post types have the `ContentNode` Interface applied to their GraphQL Type.

WPGraphQL exposes fields that a post type has registered support for using the [post_type_supports](https://developer.wordpress.org/reference/functions/post_type_supports/), and leaves out fields that a post type does not support.

Supported fields are applied to the GraphQL Type using [Interfaces](/docs/interfaces/).

An example, would be the title field.

If your Custom Post Type supports the `title` field, the GraphQL Type representing your post type will have the `NodeWithTitle` Interface applied to it.

## Mutating Custom Post Types

Mutating Custom Post types is pretty similar to mutating [Posts & Pages](/docs/posts-and-pages/).
