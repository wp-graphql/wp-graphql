---
uri: "/docs/custom-post-types/"
title: "Custom Post Types"
---

## Using Custom Post Types with WPGraphQL

In order to use Custom Post Types with WPGraphQL, you must configure the Post Type to `show_in_graphql` using the following fields:

- **show\_in\_graphql** | *boolean* | (required): true or false. If true, show the post type in the GraphQL Schema.
- **graphql\_single\_name** | *string* | (required): camel case string with no punctuation or spaces. Needs to start with a letter (not a number).
- **graphql\_plural\_name** | *string* | (optional): camel case string with no punctuation or spaces. Needs to start with a letter (not a number).
- **graphql\_kind** | *string* | (optional): Allows the type representing the post type to be added to the graph as an object type, interface type or union type. Possible values are 'object', 'interface' or 'union'. Default is 'object'.
- **graphql\_resolve\_type** | *callable* | (optional): The callback used to resolve the type. Only used if "graphql\_kind" is set to "union" or "interface".
- **graphql\_interfaces** | *array\<string\>* | (optional): List of Interface names the type should implement. These will be applied in addition to default interfaces such as "Node".
- **graphql\_exclude\_interfaces** | *array\<string\>* | (optional): List of Interface names the type *should not* implement. This is applied after default and custom interfaces are added, so this can remove default interfaces. Note: Interfaces applied by other interfaces will not be excluded unless that interface is also excluded. For example a post type that supports "thumbnail" will have NodeWithFeaturedImage interface applied, which also applies the Node interface. Excluding "Node" interface will not work unless "NodeWithFeaturedImage" was also excluded.
- **graphql\_exclude\_mutations** | *array\<string\>* | (optional): Array of mutations to prevent from being registered. Possible values are "create", "update", "delete".
- **graphql\_fields** | *array\<$config\>* | (optional): Array of fields to add to the Type. Applied if "graphql\_kind" is "interface" or "object".
- **graphql\_exclude\_fields** | *array\<string\>* | (optional): Array of fields names to exclude from the type. Applies if "graphql\_kind" is "interface" or "object". Note: any fields added by an interface will not be excluded with this option.
- **graphql\_connections** | *array\<$config\>* | (optional): Array of connection configs to register to the type. Only applies if the "graphql\_kind" is "object" or "interface".
- **graphql\_exclude\_connections** | *array\<string\>* | (optional): Array of connection names to exclude from the type. Only connections defined on the type will be excluded. Connections inherited from interfaces implemented on this type will remain even if "excluded" in this list.
- **graphql\_union\_types** | *array\<string\>* | (optional): Array of possible types the union can resolve to. Only used if "graphql\_kind" is set to "union".
- **graphql\_register\_root\_field** | *boolean* | (optional): Whether to register a field to the RootQuery to query a single node of this type. Default true.
- **graphql\_register\_root\_connection** | *boolean* | (optional): Whether to register a connection to the RootQuery to query multiple nodes of this type. Default true.
- **graphql\_exclude\_mutations** | *array\<string\>* | (optional): Array of mutations to prevent from being registered. Possible values are "create", "update", "delete".

## Registering a new Custom Post Type

This is an example of registering a new "docs" post\_type and enabling GraphQL Support.

```php
add_action( 'init', function() {
   register_post_type( 'docs', [
      'show_ui' => true, # whether you want the post_type to show in the WP Admin UI. Doesn't affect WPGraphQL Schema.
      'labels'  => [
        //@see https://developer.wordpress.org/themes/functionality/internationalization/
        'menu_name' => __( 'Docs', 'your-textdomain' ), # The label for the WP Admin. Doesn't affect the WPGraphQL Schema.
      ],
      'hierarchical' => true, # set to false if you don't want parent/child relationships for the entries
      'show_in_graphql' => true, # Set to false if you want to exclude this type from the GraphQL Schema
      'graphql_single_name' => 'document', 
      'graphql_plural_name' => 'documents', # If set to the same name as graphql_single_name, the field name will default to `all${graphql_single_name}`, i.e. `allDocument`.
      'public' => true, # set to false if entries of the post_type should not have public URIs per entry
      'publicly_queryable' => true, # Set to false if entries should only be queryable in WPGraphQL by authenticated requests
   ] );
} );
```

## Filtering an Existing Post Type

If you want to expose a Post Type that you don’t control the registration for, such as a post type registered in a third-party plugin, you can filter the Post Type registration like so, applying any of the arguments
listed above:

```php
add_filter( 'register_post_type_args', function( $args, $post_type ) {

  // Change this to the post type you are adding support for
  if ( 'docs' === $post_type ) {
    $args['show_in_graphql'] = true;
    $args['graphql_single_name'] = 'document';
    $args['graphql_plural_name'] = 'documents'; # Don't set, and it will default to `all${graphql_single_name}`, i.e. `allDocument`.
  }

  return $args;

}, 10, 2 );
```

## Using Custom Post Type UI

Custom Post Type UI is a popular WordPress plugin that enables users to register Custom Post Types and Custom Taxonomies from the WordPress dashboard via a user interface. The [WPGraphQL for Custom Post Type UI](/extenstion-plugins/wpgraphql-for-custom-post-type-ui/) plugin provides fields to Custom Post Type UI that allow you to set whether the Post Type or Taxonomy should show in GraphQL, and set the GraphQL Single Name and GraphQL Plural Name.

## Public vs Private Data

WPGraphQL respects WordPress access control policies. If a Post Type is registered as `publicly_queryable => true` then WPGraphQL will expose posts of that post type to public queries. If the post type is registered as `publicly_queryable => false` the posts of that post type will be exposed only to authenticated users who have the capability required to access it.

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

WPGraphQL exposes fields that a post type has registered support for using the [post\_type\_supports](https://developer.wordpress.org/reference/functions/post_type_supports/), and leaves out fields that a post type does not support.

Supported fields are applied to the GraphQL Type using [Interfaces](/docs/interfaces/).

An example, would be the title field.

If your Custom Post Type supports the `title` field, the GraphQL Type representing your post type will have the `NodeWithTitle` Interface applied to it.

## Mutating Custom Post Types

Mutating Custom Post types is pretty similar to mutating [Posts & Pages](/docs/posts-and-pages/).
