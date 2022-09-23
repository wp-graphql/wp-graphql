---
uri: "/docs/custom-taxonomies/"
title: "Custom Taxonomies"
---

## Using Custom Taxonomies with WPGraphQL

In order to use Custom Taxonomies with WPGraphQL, you must configure the Taxonomy to `show_in_graphql` using the following fields:

- **show_in_graphql** | _boolean_ | (required): true or false
- **graphql_single_name** | _string_ | (required): camel case string with no punctuation or spaces. Needs to start with a letter (not a number). 
- **graphql_plural_name** | _string_ | (optional): camel case string with no punctuation or spaces. Needs to start with a letter (not a number). 
- **graphql_kind** | _string_ | (optional): Allows the type representing the taxonomy to be added to the graph as an object type, interface type or union type. Possible values are 'object', 'interface' or 'union'. Default is 'object'.
- **graphql_resolve_type** | _callable_ | (optional): The callback used to resolve the type. Only used if "graphql_kind" is set to "union" or "interface".
- **graphql_interfaces** | _array<string>_ | (optional): List of Interface names the type should implement. These will be applied in addition to default interfaces such as "Node".
- **graphql_exclude_interfaces** | _array<string>_ | (optional): List of Interface names the type _should not_ implement. This is applied after default and custom interfaces are added, so this can remove default interfaces. Note: Interfaces applied by other interfaces will not be excluded unless that interface is also excluded.
- **graphql_connections** | _array<array>_ | (optional): Array of connection configs to register to the type. Only applied if the "graphql_kind" is "object" or "interface".
- **graphql_exclude_connections** | _array<string>_ | (optional): Array of connection names to exclude from the type. Only connections defined on the type will be excluded. Connections inherited from interfaces implemented on this type will remain even if "excluded" in this list.
- **graphql_union_types** | _array<string>_ | (optional): Array of possible types the union can resolve to. Only used if "graphql_kind" is set to "union".
- **graphql_register_root_field** | _boolean_ | (optional): Whether to register a field to the RootQuery to query a single node of this type. Default true.
- **graphql_register_root_connection** | _boolean_ | (optional): Whether to register a connection to the RootQuery to query multiple nodes of this type. Default true.
- **graphql_exclude_mutations** | _array<string>_ | (optional): Array of mutations to prevent from being registered. Possible values are "create", "update", "delete". 

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

If you want to expose a Taxonomy that you don’t control the registration for, such as a taxonomy registered by a third-party plugin, you can filter the Taxonomy registration like so, adding any of the arguments documented above:

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
