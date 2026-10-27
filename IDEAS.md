# Ideas: building on node context in existing extensions

These are ideas for how some widely used WPGraphQL extensions could build on node context, from reading their source in October 2026. They're exploratory pseudo-code, not prescriptions, and not recommendations from those projects' maintainers, who know their code best. An extension that adopts any of them needs to keep working on WPGraphQL versions before 2.27.0; see "Supporting older WPGraphQL versions" in the [Node Context and Global State](https://github.com/wp-graphql/wp-graphql/blob/main/plugins/wp-graphql/docs/node-context.md) docs.

The node context APIs these use:

- `graphql_model_node_context_globals`: a filter on the globals saved before a node is set up and restored when resolution leaves it.
- `graphql_bind_node_context( $callback )`: wraps a callback so it runs later with the state from when it was wrapped.
- `Model::is_node_scoped()`: return `true` to have a custom model set up once for its part of the query.

## WPGraphQL WooCommerce: restoring `$product`

WooCommerce hooks `wc_setup_product_data()` onto the `the_post` action, in its `includes/wc-template-functions.php`. When WPGraphQL sets up a post, `the_post` fires, and that function sets the global `$product` for a product, or unsets it for a post that isn't one. WPGraphQL restores the post data globals when resolution leaves a node, but it doesn't know about `$product`. So after a nested product resolves (a variable product's `variations`, or its `related` or `upsell` products), `$post` and the main query go back to the outer product, but `$product` stays on the inner one, and code that reads `$product` (a price or stock filter from another plugin, a WooCommerce template function) sees the wrong product for the outer product's later fields. WooGraphQL's own fields don't read `$product`, which is why its responses in [`results/woo/`](results/woo/SUMMARY.md) are unaffected.

Where the fix could go: WPGraphQL WooCommerce 1.0.3 registers its WooCommerce-related hooks in `WooCommerce::init()`, in `includes/class-woocommerce.php` (namespace `WPGraphQL\WooCommerce`). That method currently adds filters such as `woocommerce_session_handler`, `retrieve_password_message` and `wc_order_attribution_origin_label`. A sketch of the addition:

```php
// includes/class-woocommerce.php, in WooCommerce::init(), alongside the other add_filter() calls:
add_filter( 'graphql_model_node_context_globals', [ self::class, 'restore_global_product' ], 10, 2 );
```

```php
// includes/class-woocommerce.php, a new method on the WooCommerce class:

/**
 * Adds the global $product to the globals WPGraphQL restores when resolution leaves a post.
 *
 * @param mixed                  $globals The names of the globals WPGraphQL restores.
 * @param \WPGraphQL\Model\Model $model   The model being set up.
 *
 * @return mixed
 */
public static function restore_global_product( $globals, $model ) {
	// Every post, not only products: wc_setup_product_data() also unsets $product when a post that
	// isn't a product is set up, so that needs undoing too.
	if ( is_array( $globals ) && $model instanceof \WPGraphQL\Model\Post ) {
		$globals[] = 'product';
	}

	return $globals;
}
```

It's harmless on WPGraphQL versions without node context, which never apply the filter, so no version check is needed. With it, any field under a product, including nested objects, can also call WooCommerce template functions that read `$product`.

## WPGraphQL for Rank Math: less duplicated setup

WPGraphQL for Rank Math (0.3.4) gives each node an `seo` field backed by its own models in `src/Model/`. Before those models' fields resolve, each model's `setup()` repeats the matching core model's `setup()` and then adds Rank Math's own state:

- `ContentNodeSeo` repeats the Post model's setup almost line for line: the `parse_query()` call for each post type, the global `$post`, the queried object.
- `TermNodeSeo` repeats the Term model's, and also adds a filter for Rank Math's replacement variables.
- `UserSeo` repeats the User model's, and also sets the global `$author`, which core doesn't.

The `seo` field is resolved from its node, so with node context the node is already set up as the current object when an SEO model's fields resolve. Only Rank Math's additions would still be needed. Taking `TermNodeSeo` as the example, here's its `setup()` and `tear_down()` today, abbreviated:

```php
// src/Model/TermNodeSeo.php in WPGraphQL for Rank Math 0.3.4, abbreviated.
public function setup(): void {
	global $wp_query, $post;

	$this->global_post = $post;

	// Rank Math's own addition: this class's non_cacheable_replacements() method (unchanged below)
	// adds `term_description` to the replacement variables Rank Math shouldn't cache.
	add_filter( 'rank_math/replacements/non_cacheable', [ $this, 'non_cacheable_replacements' ] );

	if ( $this->data instanceof WP_Term ) {
		// The same as core's Term model: reset the global post, and parse the main query for the
		// term's archive (category_name, tag, or the taxonomy) and set the queried object.
		$GLOBALS['post'] = get_post( 0 );
		$wp_query->parse_query( [ 'category_name' => $this->data->slug ] ); // or tag / taxonomy
		$wp_query->queried_object_id = $this->data->term_id;
		$wp_query->queried_object    = $this->data;
	}

	// Seo::setup(): resets Rank Math's Paper object and its head output for this term.
	parent::setup();
}

public function tear_down() {
	remove_filter( 'rank_math/replacements/non_cacheable', [ $this, 'non_cacheable_replacements' ] );
	$GLOBALS['post'] = $this->global_post;
	wp_reset_postdata();
}
```

A sketch of the same methods on WPGraphQL versions with node context, keeping the old path for older versions (`graphql_bind_node_context()` only exists from 2.27.0, so it works as a version check):

```php
public function setup(): void {
	add_filter( 'rank_math/replacements/non_cacheable', [ $this, 'non_cacheable_replacements' ] );

	if ( ! function_exists( 'graphql_bind_node_context' ) ) {
		// Older WPGraphQL: set the term up as the current object here, as today.
		// The `$this->global_post = $post;` line and the `if ( $this->data instanceof WP_Term )`
		// block above, moved into a method of their own.
		$this->setup_term_globals();
	}

	// On WPGraphQL 2.27.0 and later there's nothing to set up for the term itself: `seo` is
	// selected under the term, so WPGraphQL already has it set up as the current object, and
	// restores the globals afterwards.
	parent::setup();
}

public function tear_down() {
	remove_filter( 'rank_math/replacements/non_cacheable', [ $this, 'non_cacheable_replacements' ] );

	if ( ! function_exists( 'graphql_bind_node_context' ) ) {
		$GLOBALS['post'] = $this->global_post;
		wp_reset_postdata();
	}
}
```

The same shape would apply to `ContentNodeSeo` (which has no additions of its own to keep) and `UserSeo` (which would keep setting `$author`, and could add `author` to the restored globals with the `graphql_model_node_context_globals` filter instead of restoring it in `tear_down()`).

A few things that stay as they are:

- **The models are still set up around each of their fields.** They don't opt in to node scope with `is_node_scoped()`, because `Seo::setup()` resets Rank Math's head output before each field, which fields like `fullHead` rely on.
- **This only fits the `seo` field.** Reading an SEO model's fields from anywhere other than under its node (from PHP, say) would still need the old setup, so code paths like that would keep it.

## Faust and WPGraphQL Content Blocks: correct results with no changes

Some extensions already read the current object and get the right one under node context with no code change:

- Faust's `templates` field works out a node's template hierarchy from the main query's conditional tags. In lists, it used to describe the previous node.
- Content Blocks renders blocks with `render_block()`, which reads the global `$post`, and its Post Terms block falls back to `get_the_ID()`.

An extension that resolves values in a `Deferred` callback (or a loader's `then()`) and reads the current object there would want to wrap that callback with `graphql_bind_node_context()`.
