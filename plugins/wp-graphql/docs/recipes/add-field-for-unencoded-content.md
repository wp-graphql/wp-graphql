<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Add field for unencoded content"
wordpressUri: "/recipes/add-field-for-unencoded-content/"
wordpressId: "2343"
group: "Custom Fields"
summary: "The following adds a field to the NodeWithContentEditor interface to get the unencoded content for a post: add_action( 'graphql_register_types', function() { register_graphql_field( 'NodeWithContentEditor', 'unencodedCon…"
---

The following adds a field to the `NodeWithContentEditor` interface to get the unencoded content for a post:

```
add_action( 'graphql_register_types', function() {
	register_graphql_field( 'NodeWithContentEditor', 'unencodedContent', [
		'type' => 'String',
		'resolve' => function( $post ) {
			$content = get_post( $post->databaseId )->post_content;
			return ! empty( $content ) ?  apply_filters( 'the_content', $content ) : null;
		}
	]);
});
```

You can query now query for this field:

```
{
  contentNode(id: 952, idType: DATABASE_ID) {
    id
    ... on NodeWithTitle {
      title
    }
    ... on NodeWithContentEditor {
      content
      unencodedContent
    }
  }
}
```

![Unencoded Content query in GraphiQL](https://content.wpgraphql.com/wp-content/uploads/2020/10/UnencodedContent-1024x335.png)

## Related Links

-   Github issue: [https://github.com/wp-graphql/wp-graphql/issues/1035#issuecomment-691232395](https://github.com/wp-graphql/wp-graphql/issues/1035#issuecomment-691232395)
