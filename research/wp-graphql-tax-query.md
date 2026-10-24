# WPGraphQL Tax Query and WPGraphQL Meta Query

## Sources and install

- `wp-graphql/wp-graphql-tax-query` HEAD (plugin header 0.2.0) and `wp-graphql/wp-graphql-meta-query` HEAD (plugin header 0.1.1), from GitHub tarballs. Neither has a release asset or a wp.org listing, so install from the codeload zip (repo-named root folder):
  - `wp plugin install https://github.com/wp-graphql/wp-graphql-tax-query/archive/refs/heads/develop.zip --activate` (`develop` is the default branch)
  - `wp plugin install https://github.com/wp-graphql/wp-graphql-meta-query/archive/refs/heads/develop.zip --activate`

## Findings

Both plugins only add a `taxQuery` / `metaQuery` input to `WP_Query`-backed connection `where` args (`wp-graphql-tax-query.php:134-140`, `wp-graphql-meta-query.php:139-145`). They map it to `WP_Query` args in `graphql_map_input_fields_to_wp_query` (`wp-graphql-tax-query.php:71`, `247-303`; `wp-graphql-meta-query.php:67`, `307-...`). Neither reads any global (I grepped both). Risky fields: **0**.

## The one indirect thing worth a query

Nested connections (`category { posts(where: { taxQuery }) }`) now run their `WP_Query` while the **parent node's context is active** (`is_category()`, `get_queried_object()`). Today they run with whatever leaked. Third-party `pre_get_posts` callbacks that use conditional tags without checking `$query->is_main_query()` would change behavior here. That is a core-level risk, not one of these plugins'. One query covers it:

```graphql
# Purpose: nested connection under a term. Same post set on main and new, unless a pre_get_posts callback keys off is_category()
query NestedTaxQuery($cat: ID!, $tag: String!) { category(id: $cat, idType: DATABASE_ID) { posts(where: { taxQuery: { taxArray: [ { taxonomy: TAG, field: SLUG, terms: [$tag] } ] } }) { nodes { databaseId } } } }
```

Fixture:

```bash
CAT=$(wp term create category 'TQ Cat' --porcelain); wp term create post_tag 'tq-tag'
for i in 1 2 3; do P=$(wp post create --post_title="TQ $i" --post_status=publish --porcelain); wp post term set $P category $CAT --by=id; [ $i -le 2 ] && wp post term add $P post_tag tq-tag; done
```

Input shape: `TaxQuery.taxArray` (`wp-graphql-tax-query.php:227`), with `taxonomy: TaxonomyEnum` (`:202`), `field: TaxQueryField` (enum at `:152`) and `terms` (`:207`). `TAG` and `SLUG` are the expected enum values; confirm them with introspection.
