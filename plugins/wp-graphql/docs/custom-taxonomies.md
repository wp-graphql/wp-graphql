---
uri: "/docs/custom-taxonomies/"
title: "Custom Taxonomies"
---

## Using Custom Taxonomies with WPGraphQL

In order to use Custom Taxonomies with WPGraphQL, you must configure the Taxonomy to `show_in_graphql` using the following fields:

| Field                              | Type                 | Required | Description                                                                                                                                                                                   |
| ---------------------------------- | -------------------- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `show_in_graphql`                  | boolean              | Yes      | If true, show the taxonomy in the GraphQL Schema                                                                                                                                              |
| `graphql_single_name`              | string               | Yes      | Camel case string with no punctuation or spaces. Needs to start with a letter (not a number)                                                                                                  |
| `graphql_plural_name`              | string               | No       | Camel case string with no punctuation or spaces. Needs to start with a letter (not a number)                                                                                                  |
| `graphql_description`              | string               | No       | Custom description for the type in the GraphQL schema. If not set, will fallback to taxonomy's description, then a default description                                                        |
| `graphql_kind`                     | string               | No       | Allows the type representing the taxonomy to be added to the graph as an object type, interface type or union type. Possible values are 'object', 'interface' or 'union'. Default is 'object' |
| `graphql_resolve_type`             | callable             | No       | The callback used to resolve the type. Only used if "graphql_kind" is set to "union" or "interface"                                                                                           |
| `graphql_interfaces`               | array&lt;string&gt;  | No       | List of Interface names the type should implement. These will be applied in addition to default interfaces such as "Node"                                                                     |
| `graphql_exclude_interfaces`       | array&lt;string&gt;  | No       | List of Interface names the type _should not_ implement. This is applied after default and custom interfaces are added                                                                        |
| `graphql_fields`                   | array&lt;$config&gt; | No       | Array of fields to add to the Type. Applies if "graphql_kind" is "interface" or "object"                                                                                                      |
| `graphql_exclude_fields`           | array&lt;string&gt;  | No       | Array of fields names to exclude from the type. Applies if "graphql_kind" is "interface" or "object"                                                                                          |
| `graphql_connections`              | array&lt;$config&gt; | No       | Array of connection configs to register to the type. Only applies if the "graphql_kind" is "object" or "interface"                                                                            |
| `graphql_exclude_connections`      | array&lt;string&gt;  | No       | Array of connection names to exclude from the type                                                                                                                                            |
| `graphql_union_types`              | array&lt;string&gt;  | No       | Array of possible types the union can resolve to. Only used if "graphql_kind" is set to "union"                                                                                               |
| `graphql_register_root_field`      | boolean              | No       | Whether to register a field to the RootQuery to query a single node of this type. Default true                                                                                                |
| `graphql_register_root_connection` | boolean              | No       | Whether to register a connection to the RootQuery to query multiple nodes of this type. Default true                                                                                          |
| `graphql_exclude_mutations`        | array&lt;string&gt;  | No       | Array of mutations to prevent from being registered. Possible values are "create", "update", "delete"                                                                                         |

### Registering a new Custom Taxonomy

This is an example of registering a new "document_tag" Taxonomy to be connected to the "docs" Custom Post Type and enabling GraphQL support.

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

If you want to expose a Taxonomy that you don't control the registration for, such as a taxonomy registered by a third-party plugin, you can filter the Taxonomy registration like so, adding any of the arguments documented above:

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

## Public vs Private Data

Terms behave differently from post entries here, so it is worth being explicit.

**Terms are served to everyone.** If a taxonomy is in the schema, which it is only because it was registered with `show_in_graphql`, its terms are returned to anonymous callers. `public` and `publicly_queryable` do not change that. A taxonomy registered `public => false` still has all of its terms readable through GraphQL.

The taxonomy itself, meaning the object describing the taxonomy rather than the terms in it, follows the same rule. It is returned to anyone, and individual fields on it that describe how the taxonomy is configured are returned only to users who can edit its terms.

This differs from post types, where `publicly_queryable => false` does keep entries from anonymous callers. See [Public vs Private Data](/docs/custom-post-types/#public-vs-private-data) on the Custom Post Types page for how that works.

### Restricting terms yourself

If terms in a taxonomy should not be readable by everyone, you have two options.

The simplest is not to add the taxonomy to the schema at all. `show_in_graphql` is the decision that exposes it, so leaving it off keeps the terms out entirely.

If you want the taxonomy in the schema but its terms restricted, filter the model. `graphql_data_is_private` runs for every model WPGraphQL builds, and returning true for a term removes it:

```php
add_filter( 'graphql_data_is_private', function ( $is_private, $model_name, $data ) {

    if ( 'TermObject' !== $model_name ) {
        return $is_private;
    }

    if ( isset( $data->taxonomy ) && 'my_taxonomy' === $data->taxonomy ) {
        return ! current_user_can( 'edit_posts' );
    }

    return $is_private;

}, 10, 3 );
```

Terms you mark private are dropped from connections, and looking one up directly returns null.

One thing to know before relying on this: `pageInfo` is calculated from the underlying query, before models are built, so a page can come back with fewer nodes than you asked for, or none at all, while `hasNextPage` still reports true. This is how any model level filtering behaves, private posts included. Clients should page until `hasNextPage` is false rather than stopping at the first page that looks empty.

### `public` describes intent, it does not restrict

`public` is a shorthand WordPress uses to fill in the defaults for `publicly_queryable`, `show_ui`, `show_in_nav_menus` and others. Once those are set, it has no further effect. Setting `public => false` says "this is not a normal, user facing taxonomy," not "keep these terms private."

Both values are readable in the schema, so a client can use them to decide how to render:

```graphql
{
  taxonomy(id: "documentTag", idType: NAME) {
    name
    public             # the broad statement of intent
    publiclyQueryable  # whether terms are reachable on the front end
  }
}
```

`publiclyQueryable` is the useful one for a front end deciding whether to build archive routes for a taxonomy's terms.

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
  documentTag(id: "validIdGoesHere") {
    id
    name
  }
}
```
