<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Using GraphQL Fragments in PHP"
wordpressUri: "/recipes/using-graphql-fragments-in-php/"
wordpressId: "2469"
group: "Queries"
summary: "You can execute GraphQL queries in PHP. In this case, we even show using a GraphQL Fragment. add_action( 'init', function() { $results = graphql([ 'query' => ' { posts { nodes { ...PostFields } } } fragment PostFields on…"
---

You can execute GraphQL queries in PHP. In this case, we even show using a GraphQL Fragment.

```
add_action( 'init', function() {

	$results = graphql([
		'query' => '
		{
		  posts {
		    nodes {
		      ...PostFields
		    }
		  }
		}
		fragment PostFields on Post {
		  id
		  title
		}
		',
	]);

	var_dump( $results );
	die();

} );
```

Executing this code leads to the following output:

![PHP output of executing Graphql](https://content.wpgraphql.com/wp-content/uploads/2020/10/GraphqlFragmentPHPoutput.png)

Additionally, if you were to define your fragment in another file, such as the file that is rendering the data, you can define fragments as variables and concatenate them like so:

```
$fragment = '
  fragment PostFields on Post {
    id
    title
}
';

$results = graphql([
  'query' => '
    {
      posts {
        nodes {
	  ...PostFields
	}
      }
    }
  ' . $fragment ,
]);
```
