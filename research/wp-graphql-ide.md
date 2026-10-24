# WPGraphQL IDE (monorepo `plugins/wp-graphql-ide`)

## Sources and install

- Read locally at `plugins/wp-graphql-ide` (header `Version: 5.7.0`). Paths below are relative to that directory.
- `wp plugin install wpgraphql-ide --activate`, or activate the monorepo copy. The document fields need Smart Cache active too.

## Schema surface and findings

I grepped `includes/`, `src/`, `plugins/` and `wpgraphql-ide.php` for global post and query reads. There were none outside docblocks (`includes/access-functions.php:72` is a comment).

| Type.field / mutation | Registered / resolver | Global read | Category |
| --- | --- | --- | --- |
| `GraphqlDocument.variables` | `includes/SmartCacheBridge.php:266-277` | `get_post_meta( $post->databaseId, ... )`, explicit ID | SAFE |
| `GraphqlDocument.headers` | `includes/SmartCacheBridge.php:279-290` | explicit ID | SAFE |
| `CreateGraphqlDocumentInput` / `UpdateGraphqlDocumentInput` `variables`/`headers` + `graphql_mutation_response` persistence | `SmartCacheBridge.php:294-297`, `84`, `326-336` | `update_post_meta( (int) $post_object['postObjectId'] )`, explicit ID from the payload | SAFE |
| `updateGraphqlSetting` mutation | `includes/settings.php:499` | options only | SAFE |
| Document privacy: `graphql_data_is_private` → `Access::restrict_post_visibility` | `wpgraphql-ide.php:173` (connection-level author scoping was removed for #4117, see the comment at `wpgraphql-ide.php:160-172`) | current user and the model's data, not query globals | SAFE |

Risky fields: **0**.

Smoke query (`GraphqlDocument.variables` reads `databaseId`, which today triggers setup and leaks the document post). It confirms nothing else in the request depends on that leak:

```graphql
# Purpose: document fields resolve correctly and a root field after them is unaffected
query IdeDocs { graphqlDocuments(first: 3) { nodes { databaseId title variables headers } } viewer { name } }
```

Fixture: `wp post create --post_type=graphql_document --post_status=publish --post_title='Doc 1' --post_content='{ __typename }' --porcelain`, repeated three times. The value for `variables` is set through the mutation or REST.
