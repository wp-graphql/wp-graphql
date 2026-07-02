<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Deprecating a field in the Schema"
wordpressUri: "/recipes/deprecating-a-field-in-the-schema/"
wordpressId: "828602"
group: "Custom Fields"
summary: "Sometimes it can be helpful to deprecate a field in the Schema without removing it altogether. This snippet shows how to deprecate the `Post.excerpt` field. You can use this technique to deprecate other fields. // Filter…"
---

Sometimes it can be helpful to deprecate a field in the Schema without removing it altogether.

This snippet shows how to deprecate the \`Post.excerpt\` field.

You can use this technique to deprecate other fields.

```
// Filter the Object Fields
add_filter( 'graphql_object_fields', function( $fields, $type_name, $wp_object_type, $type_registry ) {

        // If the Object Type is not the "Post" Type
        // return the fields unaltered
	if ( 'Post' !== $type_name ) {
		return $fields;
	}

        // If the excerpt field doesn't exist 
        // (removed by another plugin, for example)
        // return the fields unaltered
	if ( ! isset( $fields['excerpt'] ) ) {
		return  $fields;
	}

        // Add a deprecation reason to the excerpt field
	$fields['excerpt']['deprecationReason'] = __( 'Just showing how to deprecate an existing field', 'your-textdomain' );
	
        // return the modified
        return $fields;

}, 10, 4 );
```

After using this snippet, we can verify in the WPGraphQL Schema Docs that the field is indeed deprecated:

![](https://content.wpgraphql.com/wp-content/uploads/2022/01/Screen-Shot-2022-01-19-at-11.02.58-AM.png)

Screenshot of the excerpt field showing deprecated in the GraphiQL IDE Schema Docs
