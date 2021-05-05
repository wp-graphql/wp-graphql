---
uri: "/docs/custom-taxonomies/"
title: "Custom Taxonomies"
---

## Using Custom Taxonomies with WPGraphQL

In order to use Custom Taxonomies with WPGraphQL, you must configure the Taxonomy to `show_in_graphql` using the following fields:

- **show_in_graphql** (boolean): true or false
- **graphql_single_name** (string): camel case string with no punctuation or spaces. Needs to start with a letter (not a number). Important to be different than the plural name.
- **graphql_plural_name** (string): camel case string with no punctuation or spaces. Needs to start with a letter (not a number). Important to be different than the single name.

### Registering a new Custom Taxonomy

This is an example of registering a new “document_tag” Taxonomy to be connected to the "docs" Custom Post Type and enabling GraphQL support.

```php
add_action('init', function() {
  register_taxonomy( 'doc_tag', 'docs', [
    'labels'  => [
      'menu_name' => __( 'Document Tags', 'your-textdomain' ), //@see https://developer.wordpress.org/themes/functionality/internationalization/
    ],
    'show_in_graphql' => true,
    'graphql_single_name' => 'documentTag',
    'graphql_plural_name' => 'documentTags',
  ]);
});
```

### Filtering an Existing Custom Taxonomy

If you want to expose a Taxonomy that you don’t control the registration for, such as a taxonomy registered by a third-party plugin, you can filter the Taxonomy registration like so:

```php
add_filter( 'register_taxonomy_args', function( $args, $taxonomy ) {

  if ( 'doc_tag' === $taxonomy ) {
    $args['show_in_graphql'] = true;
    $args['graphql_single_name'] = 'documentTag';
    $args['graphql_plural_name'] = 'documentTags';
  }

  return $args;

}, 10, 2 );
```

## Querying Custom Taxonomies

Querying terms of Custom Taxonomies is nearly identical to querying Categories and Tags. The difference being the name assigned by `graphql_single_name` and `graphql_plural_name`.

Assuming the taxonomy was registered as shown above, with `graphql_plural_name` set to `documentTags`, you would be able to query like so:

```graphql
{
  documentTags {
    nodes {
      id
      name
    }
  }
}
```

And because the taxonomy was registered in relation to the `docs` Post Type, you'd be able to query the connected nodes like so:

```graphql
{
  documentTags {
    nodes {
      id
      name
      docs {
        nodes {
          id
          title
        }
      }
    }
  }
}
```

And you'd be able to query a single `documentTag` like so:

```graphql
{
  documentTag( id: \"validIdGoesHere\" ) {
    id
    name
  }
}
```

## Mutating Custom Taxonomy Terms

Mutating Custom Taxonomy terms is nearly identical to mutation [Categories and Tags](/docs/categories-and-tags/).
