---
uri: "/docs/graphql-resolvers/"
title: "GraphQL Resolvers"
---

On this page you will find information about what GraphQL resolvers are and how they work. This page will be most useful for developers that are [already familiar with GraphQL](/docs/intro-to-graphql/).

> Resolvers exist in *any* GraphQL implementation, not just WPGraphQL, but how they are implemented and the API for working with them varies from language to language, which is why you find this page under the WPGraphQL Concepts section.

A GraphQL Schema consists of Types and Fields, which declares what is possible to be asked for. Resolvers are the functions that execute when a field is asked for.

When registering a field to theWPGraphQL Schema defining a resolver is optional.

**Registering a field *without* a resolver:**

Below is an example of registering a field to the Schema *without* a resolve function defined.

```php
add_action( 'graphql_register_types', function() {
  register_graphql_field( 'RootQuery', 'hello', [
    'type' => 'String',
  ]);
} );
```

This will add a `hello` field to the RootQuery in the Schema and will allow this query to be executed:

```graphql
{
  hello
}
```

However, if we were to execute this, the results would be a `null` value for the hello field, like so:

```json
{
  "data": {
    "hello": null
  }
}
```

This is because we registered the field to the Schema, but *did not* define a resolver.

**Registering a field *with* a resolver:**

Below is the same example as above, but with a resolve function included.

```php
add_action( 'graphql_register_types', function() {
  register_graphql_field( 'RootQuery', 'hello', [
    'type' => 'String',
    'resolve' => function() {
      return 'world';
    },
  ]);
} );
```

Here we defined a `resolve` function and have it return the string `world`. So now, if the above query were executed again, the results would be:

```json
{
  "data": {
    "hello": "world"
  }
}
```

## Resolver Arguments

In most cases, fields in a GraphQL schema will not simply resolve with a hard-coded string, like the above example. Resolvers often need more information to properly resolve.

In GraphQL, all resolve functions get passed 4 function arguments which the resolve function can use to during execution:

- **\\$root (mixed):** This first argument is whatever previous object or array was being resolved. In this case, our field is at the Root, so it's passed a null value. If our field was on a `Post` type, for example, it would be passed an instance of a Post (`\\WPGraphQL\\Post\\Model`)
- **\\$args (array):** The 2nd argument is the args on the field. The field we registered doesn't have any args, but if it did, the input values of the args would be passed here as an array.
- **\\$context (AppContext):** The 3rd argument is an instance of the AppContext class. This class is passed to every resolver and is used for things like DataLoading so resolvers can pull from centrally cached data instead of fetching fresh data, etc.
- **\\$info (ResolveInfo):** The 4th argument is an instance of ResolveInfo, which can be used to determine things about where in the resolveTree the field is, what the Parent Type is, what fields have been selected on this Type, and more.

To better understand how these function arguments work, let's add an argument to the `hello` field to accept a name as input:

```php
add_action( 'graphql_register_types', function() {
  register_graphql_field( 'RootQuery', 'hello', [
    'type' => 'String',
    'args' => [
       'name' => [
         'type' => 'String',
         'description' => __( 'Enter your name so GraphQL can say hello to you', 'your-textdomain' ),
       ]
    ],
    'resolve' => function( $root, $args, $context, $info ) {
      
      // if the name argument was input in the query, return it
      return ( isset( $args['name'] ) ? $args['name'] : 'world';

    },
  ]);
} );
```

Here we added an argument named `name` that accepts a string.

In the resolve function, we added the 4 function arguments to the function call, and we check to see if the `name` argument was passed through on the field, and if so, we return the value for the resolver, and default to `world` if no value was input.

Now we could query like so:

```graphql
{
  hello( name: "Pam" )
}
```

And we would get the following result:

```json
{
  "data": {
    "hello": "Pam"
  }
}
```

## Overriding Existing Resolvers

You may find yourself in a situation where you need to override an existing resolver. There are many ways to accomplish this. Let's look at some examples:

### graphql_pre_resolve_field filter

During GraphQL execution, the `graphql_pre_resolve_field` filter executes prior to the default field resolution. If this filter returns a value, it will return the value and skip executing the default resolver.

This can be used like so:

 ```php
add_filter( 'graphql_pre_resolve_field', function( $default, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver ) {

  if ( 'rootquery' === strtolower( $type_name ) && 'hello' === $field_key ) {
    return 'custom value';
  }

  return $default;

}, 10, 9 );
```

This first checks if the filter is being applied to the `hello` field on the `RootQuery` type, and if it is it returns a string of "custom value".

The same query for the `hello` field would now return the following:

```json
{
  "data": {
    "hello": "custom value"
  }
}
```

> **NOTE:** We used `strtolower()` to convert the type name to lowercase because behind the scenes WPGraphQL converts type names to lowercase strings, so it's always safest to check type names using all lowercase characters.

[Learn more about the graphql\_pre\_resolve\_field filter](/filters/graphql_pre_resolve_field/).

### graphql_resolve_field filter

This filter is similar to above, but the difference is that this filter runs *after* default execution of the resolve field has already run.

Let's say we wanted to prefix the results of the `hello` field with something custom.

We could do that like so:

```php
add_filter( 'graphql_resolve_field', function( $result, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver ) {

  if ( 'rootquery' === strtolower( $type_name ) && 'hello' === $field_key ) {
    return 'some prefix ' . $result;
  }

  return $result;

}, 10, 9 );
```

Now executing the following query:

```graphql
{
  hello( name: "Pam" )
}
```

Would return the following, with our prefix before the existing results of the query:

```json
{
  "data": {
    "hello": "some prefix Pam"
  }
}
```

[Learn more about the graphql\_resolve\_field filter](/filters/graphql_resolve_field/).

### Completely Replacing the Field Resolve Function

Below is an example of replacing the field's resolve function altogether.

Let's say we wanted the `hello` field to *always* return the string "goodbye", no matter what.

We could replace the resolve function for the field like so:

```php
add_filter( 'graphql_RootQuery_fields', function( $fields ) {

    // First, make sure there's actually a "hello" field registered to the RootQuery Type
    if ( isset( $fields['hello'] ) ) {

      // Override the resolve function completely
      $fields['hello']['resolve'] = function() {
        return 'goodbye';
      }
    }

    return $fields;

}, 10, 1 );
```

Now the same query for the hello field (with or without an input argument supplied to the query) would return the following:

```json
{
  "data": {
    "hello": "goodbye"
  }
}
```
