<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Register a basic Mutation"
wordpressUri: "/recipes/register-a-basic-mutation/"
wordpressId: "7812"
group: "Custom Fields"
summary: "This snippet shows how to register a basic GraphQL Mutation with a single input field, a single output field, and the input is simply returned as the value for the output. add_action( 'graphql_register_types', function()…"
---

This snippet shows how to register a basic GraphQL Mutation with a single input field, a single output field, and the input is simply returned as the value for the output.

```
add_action( 'graphql_register_types', function() {

	register_graphql_mutation( 'testMutation', [
		'inputFields' => [
			'phoneNumber' => [
				'type' => [ 'non_null' => 'String' ],
			],
		],
		'outputFields' => [
			'phoneNumber' => [
				'type' => 'String',
			],
		],
		'mutateAndGetPayload' => function( $input ) {

			return [
				'phoneNumber' => $input['phoneNumber'] ?? null,
			];

		}
	]);

} );
```
