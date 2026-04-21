<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Filter to add restricted field on Model"
wordpressUri: "/recipes/filter-to-add-restricted-field-on-model/"
wordpressId: "2423"
group: "Queries"
summary: "Labels on Post Types are not publicly exposed by WordPress. They are attributes for use in the Admin, and are treated with respect to proper access to the admin. To see the labels, the user requesting them must be authen…"
---

Labels on Post Types are not publicly exposed by WordPress. They are attributes for use in the Admin, and are treated with respect to proper access to the admin.

To see the labels, the user requesting them must be authenticated.

When a user requesting a `PostType`, these are the following fields that are by default allowed to be viewed by a public request: [https://github.com/wp-graphql/wp-graphql/blob/develop/src/Model/PostType.php#L59](https://github.com/wp-graphql/wp-graphql/blob/develop/src/Model/PostType.php#L59)

You can use the `graphql_allowed_fields_on_restricted_type` filter to expose more fields publicly if you chose to do so: [https://github.com/wp-graphql/wp-graphql/blob/develop/src/Model/Model.php#L292](https://github.com/wp-graphql/wp-graphql/blob/develop/src/Model/Model.php#L287)

```
add_filter( 'graphql_allowed_fields_on_restricted_type', function( $fields, $model_name, $data, $visibility, $owner, $current_user ) {
	if ( 'PostTypeObject' === $model_name ) {
		$fields[] = 'label';
	}
	return $fields;
}, 10, 6 );
```

Before adding the filter:

![Restricted field before adding filter](https://content.wpgraphql.com/wp-content/uploads/2020/10/RestrictedFieldsBefore.png)

After adding the filter:

![Restricted field after adding filter](https://content.wpgraphql.com/wp-content/uploads/2020/10/RestrictedFieldsAfter.png)

Github Issue: [https://github.com/wp-graphql/wp-graphql/issues/1304#issuecomment-626836656](https://github.com/wp-graphql/wp-graphql/issues/1304#issuecomment-626836656)
