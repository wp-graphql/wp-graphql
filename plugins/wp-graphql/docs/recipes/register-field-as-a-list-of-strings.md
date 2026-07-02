<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Register field as a list of strings"
wordpressUri: "/recipes/register-field-as-a-list-of-strings/"
wordpressId: "2485"
group: "Custom Fields"
summary: "The below code registers a field called listOfStrings that returns a list of strings as the result: add_action( 'graphql_register_types', function() { register_graphql_field( 'RootQuery', 'listOfStrings', [ 'type' => [ '…"
---

The below code registers a field called `listOfStrings` that returns a list of strings as the result:

```
add_action( 'graphql_register_types', function() {

	register_graphql_field( 'RootQuery', 'listOfStrings', [
		'type' => [ 'list_of' => 'String' ],
		'resolve' => function() {
			return [
				'String One',
				'String Two'
			];
		}
	] );

} );
```

This field can now be queried:

```
{
  listOfStrings
}
```

![ListOfStrings field results displayed in GraphiQL](https://content.wpgraphql.com/wp-content/uploads/2020/10/ListOfStringsField-1024x278.jpg)
