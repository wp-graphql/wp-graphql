<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Showing Post Type labels in public queries"
wordpressUri: "/recipes/showing-post-type-labels-in-public-queries/"
wordpressId: "7762"
group: "Authorization"
summary: "WPGraphQL respects WordPress core access control rights. This means that data that is only available to authenticated users in the WordPress admin is only available to authenticated users making GraphQL requests. Sometim…"
---

WPGraphQL respects WordPress core access control rights. This means that data that is only available to authenticated users in the WordPress admin is only available to authenticated users making GraphQL requests.

Sometimes, you want to expose fields that are restricted by default.

Take the Post Type Label field, for example.

Querying for the label of a Post Type as a public user returns a `null` value by default:

![Screenshot of a query for ContentTypes and their label, showing null value for the label.](https://content.wpgraphql.com/wp-content/uploads/2020/12/Screen-Shot-2020-12-08-at-6.10.35-AM-1024x335.png)

With the following snippet, you can expose the `label` field to public users:

```
add_filter( 'graphql_allowed_fields_on_restricted_type', function( $allowed_restricted_fields, $model_name, $data, $visibility, $owner, $current_user ) {

	if ( 'PostTypeObject' === $model_name ) {
		$allowed_restricted_fields[] = 'label';
	}

	return $allowed_restricted_fields;

}, 10, 6 );
```

And below we can see the same query, showing the value of the labels to public users.

![Screenshot of a query for ContentTypes and their label, showing the label's value for the label.](https://content.wpgraphql.com/wp-content/uploads/2020/12/Screen-Shot-2020-12-08-at-6.10.22-AM-1-1024x333.png)
