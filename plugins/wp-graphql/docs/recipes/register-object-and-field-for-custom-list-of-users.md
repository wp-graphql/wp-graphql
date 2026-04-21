<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Register object and field for custom list of users"
wordpressUri: "/recipes/register-object-and-field-for-custom-list-of-users/"
wordpressId: "2476"
group: "Custom Fields"
summary: "The following code creates an object type called StuntPerformer and creates a field on the RootQuery called stuntPerformers that returns a custom list of users. In this case, the list of users are the admins of the websi…"
---

The following code creates an object type called `StuntPerformer` and creates a field on the RootQuery called `stuntPerformers` that returns a custom list of users.

In this case, the list of users are the admins of the website, but custom logic could be added to return a curated list of users.

```
add_action( 'graphql_register_types', function() {

	register_graphql_object_type(
		'StuntPerformer',
		[
			'description' => __( 'Stunt Performer', 'bsr' ),
			'fields'      => [
				'firstName' => [
					'type'        => 'String',
					'description' => 'first name'
				],
				'lastName'  => [
					'type'        => 'String',
					'description' => 'last name'
				],
				'uid'       => [
					'type'        => 'String',
					'description' => 'user id'
				]
			],
		]
	);

	register_graphql_field(
		'RootQuery',
		'stuntPerformers',
		[
			'description' => __( 'Return stunt performers', 'bsr' ),
			'type'        => [ 'list_of' => 'stuntPerformer' ],
			'resolve'     => function() {
				$stunt_performers = [];
				$performers       = get_users( array(
					'role__in' => 'administrator'
				) );

				foreach ( $performers as $p ) {
					$performer = [
						'firstName' => $p->first_name,
						'lastName'  => $p->last_name,
						'uid'       => $p->ID
					];

					$stunt_performers[] = $performer;
				}

				return $stunt_performers;
			}
		]
	);

} );
```

You can now query for these stuntPerformers with the following GraphQL:

```
{
  stuntPerformers {
    firstName
    lastName
    uid
  } 
}
```

![Stunt Performers query in GraphiQL](https://content.wpgraphql.com/wp-content/uploads/2020/10/StuntPerformersGraphiql-1024x366.jpg)
