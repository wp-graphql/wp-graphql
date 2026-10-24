# WPGraphQL Smart Cache (monorepo `plugins/wp-graphql-smart-cache`)

## Sources and install

- Read locally at `plugins/wp-graphql-smart-cache` (header `Version: 2.3.4`). Paths below are relative to that directory.
- `wp plugin install wpgraphql-smart-cache --activate`, or activate the monorepo copy.

## Findings

I grepped `src/` for global post and query reads (`global $post`, `get_post()`, `get_the_ID`, `get_queried_object`, conditional tags, `$wp_query`, `the_content`, `setup_postdata`, `switch_to_blog`, `Deferred`). Nothing reads the queried object to build cache keys or to tag responses. Cache keys come from core's Query Analyzer, which collects them through `graphql_return_response` (`src/Cache/Collection.php:23`), plus the query, variables and user. All of that is independent of `$wp_query` and `$post`.

| Location | Global read | Runs during a GraphQL request? | Category |
| --- | --- | --- | --- |
| `src/Admin/Editor.php:320` `translate_excerpt_text_cb()` (`gettext` filter) | `get_post()` | No. It is added in `admin_init()` (`src/Admin/Editor.php:22-31`). | SAFE |
| `src/Document/Audit.php:518` `flag_unverified_alias_links()` | `get_the_ID()` | No. It is on the `post_column_taxonomy_links` list-table filter (`Audit.php:44`). | SAFE |
| `src/Document/Audit.php:489-491` `filter_list_table_query()` (`posts_where`) | `is_admin()`, `$query->is_main_query()` | Runs for every WP_Query, but returns early unless `is_admin()` is true and the query is the main query, so it is inert for GraphQL connection queries. | SAFE |
| `src/Utils.php:20`, `src/Document/GarbageCollection.php:31` | `new \WP_Query` (local variable named `$wp_query`, not the global) | Persisted-query lookup and cron GC | SAFE |

Risky fields: **0**.

## What to watch anyway (indirect)

- **Cached output bakes in today's leak.** A response cached on main (object cache or network cache) holds whatever the leaked-context HTML was, for example an options wysiwyg with a prepended attachment (see `wp-graphql-acf.md` E1). After upgrading, cached entries keep serving the old output until they are purged. When comparing main and new, purge first with `wp cache flush`, plus a CDN purge if a network cache sits in front. The plugin's only CLI command is `wp graphql smart-cache documents` (`src/Document/Audit.php:51`); there is no CLI purge command.
- **Cache-key tagging is unchanged.** Run any query from the other matrices with Smart Cache active, and diff the `X-GraphQL-Keys` header between main and new. It should be identical, because the keys come from the dataloader and models, not from global state. A difference would mean the new scoping is changing which models are instantiated, which is worth investigating.

```graphql
# Purpose: confirm X-GraphQL-Keys is identical on main and new for a node + root-field mix (send as GET with Smart Cache network cache enabled)
query KeysParity($p1: ID!) { post(id: $p1, idType: DATABASE_ID) { title author { node { name } } } posts(first: 3) { nodes { id } } }
```

Fixture: any published post. `P1=$(wp post create --post_title='SC One' --post_status=publish --porcelain)`. Settings live in the `graphql_cache_section` option (`global_max_age` at `src/Document/MaxAge.php:368`, `cache_toggle` at `src/Admin/Settings.php:29`, `global_ttl` at `src/Cache/Results.php:408`), so for example: `wp option patch insert graphql_cache_section cache_toggle on` and `wp option patch insert graphql_cache_section global_max_age 60`. If the option does not exist yet, use `wp option update graphql_cache_section '{"cache_toggle":"on","global_max_age":"60"}' --format=json`. The `on` value for the toggle is unverified; it may be stored as a checkbox value.
