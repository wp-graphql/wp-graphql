<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "List of Key Values"
wordpressUri: "/recipes/list-of-key-values/"
wordpressId: "2359"
group: "Custom Fields"
summary: "This is an example showing how to return a list of keys and values where the keys and values are both strings. add_action( 'graphql_register_types', function() { register_graphql_object_type( 'keyValue', [ 'description'…"
---

This is an example showing how to return a list of keys and values where the keys and values are both strings.

```
add_action( 'graphql_register_types', function() {

	register_graphql_object_type( 'keyValue', [
		'description' => __( 'Keys and their values, both cast as strings', 'your-textdomain' ),
		'fields'      => [
			'key'   => [
				'type' => 'String',
			],
			'value' => [
				'type' => 'String',
			],
		]
	] );

	register_graphql_field( 'RootQuery', 'listOfKeyValues', [
		'type'        => [ 'list_of' => 'KeyValue' ],
		'description' => __( 'Field that resolves as a list of keys and values', 'your-textdomain' ),
		'resolve'     => function() {
			$mock_array = [
				'key1' => 'Value1',
				'key2' => 'Value2',
				'key3' => 'Value3'
			];

			$list = [];
			foreach ( $mock_array as $key => $value ) {
				$list[] = [
					'key' => $key,
					'value' => $value,
				];
			}

			return $list;
		}
	] );

} );
```
