# Ideas: building on node context in existing extensions

These are ideas for how some widely used WPGraphQL extensions could build on node context, from reading their source in October 2026. They're exploratory pseudo-code, not prescriptions, and not recommendations from those projects' maintainers, who know their code best. An extension that adopts any of them needs to keep working on WPGraphQL versions before 2.27.0; see "Supporting older WPGraphQL versions" in the [Node Context and Global State](https://github.com/wp-graphql/wp-graphql/blob/main/plugins/wp-graphql/docs/node-context.md) docs.

The node context APIs these use:

- `graphql_model_node_context_globals`: a filter on the globals saved before a node is set up and restored when resolution leaves it.
- `graphql_bind_node_context( $callback )`: wraps a callback so it runs later with the state from when it was wrapped.
- `Model::is_node_scoped()`: return `true` to have a custom model set up once for its part of the query.

## WPGraphQL WooCommerce: restoring `$product`

WooCommerce sets the global `$product` on the `the_post` action (`wc_setup_product_data()`), so WPGraphQL sets it whenever it sets up a product. Nothing resets it when resolution leaves the product, though, and WPGraphQL doesn't know about it. After a nested product resolves (a variable product's `variations`, `related` or `upsell` products), `$post` and the main query go back to the outer product, but `$product` stays on the inner one. WooGraphQL's own fields don't read `$product`, so its responses are unaffected, but code that does (a price or stock filter from another plugin, say) can see the wrong product for the outer product's later fields.

Adding `product` to the restored globals fixes that:

```php
add_filter(
	'graphql_model_node_context_globals',
	static function ( $globals, $model ) {
		if ( is_array( $globals ) && $model instanceof \WPGraphQL\Model\Post ) {
			$globals[] = 'product';
		}

		return $globals;
	},
	10,
	2
);
```

It's harmless on WPGraphQL versions without node context, which never apply the filter. With it, any field under a product, including nested objects, can also call WooCommerce template functions that read `$product`.

## WPGraphQL for Rank Math: less duplicated setup

The extension's SEO models repeat the matching core model's `setup()` before adding their own state:

- `ContentNodeSeo` repeats the Post model's setup almost line for line: the `parse_query()` call for each post type, the global `$post`, the queried object.
- `TermNodeSeo` repeats the Term model's, and also adds a filter for Rank Math's replacement variables.
- `UserSeo` repeats the User model's, and also sets the global `$author`, which core doesn't.

The `seo` field is resolved from its node, so the node is already set up when an SEO model's fields resolve, and only the additions are still needed. A sketch:

```php
// TermNodeSeo, sketched for WPGraphQL versions with node context.
public function setup(): void {
	// Rank Math's own addition, still needed.
	add_filter( 'rank_math/replacements/non_cacheable', [ $this, 'non_cacheable_replacements' ] );

	// No parse_query(), global $post or queried object here: the term this SEO object
	// belongs to is already set up as the current object, because `seo` is selected under it.
	parent::setup();
}
```

`UserSeo` would keep setting `$author`, and could add `author` to the restored globals with the filter instead of restoring it in `tear_down()`. The models would still be set up around each of their fields (they don't need `is_node_scoped()`), because the parent `setup()` resets Rank Math's head output before each field. Reading an SEO model's fields from anywhere other than under its node would need the old setup, so this only fits code paths that always resolve the model through the `seo` field.

## Faust and WPGraphQL Content Blocks: correct results with no changes

Some extensions already read the current object and get the right one under node context with no code change:

- Faust's `templates` field works out a node's template hierarchy from the main query's conditional tags. In lists, it used to describe the previous node.
- Content Blocks renders blocks with `render_block()`, which reads the global `$post`, and its Post Terms block falls back to `get_the_ID()`.

An extension that resolves values in a `Deferred` callback (or a loader's `then()`) and reads the current object there would want to wrap that callback with `graphql_bind_node_context()`.
