# WPGraphQL WooCommerce (WooGraphQL): node-scoped context risk matrix

## 1. Versions, sources, install

| Item | Value |
| --- | --- |
| Extension | WooGraphQL **v1.0.3** (`wp-graphql-woocommerce.php:6`), github `wp-graphql/wp-graphql-woocommerce`. Source read from `dl/wp-graphql_wp-graphql-woocommerce/wp-graphql-woocommerce-HEAD` (no `.git`, header version is the reference). Header: `Requires PHP: 8.1`, `Requires Plugins: woocommerce`, `WC requires at least: 9.0.0`, `WC tested up to: 10.4.3`, `WPGraphQL requires at least: 2.0.0` (`wp-graphql-woocommerce.php` header). **It cannot run in the PHP 7.4/8.0 cells of the matrix.** |
| Base plugin | WooCommerce **11.2.1** (`woocommerce.latest-stable.zip`, `woocommerce.php:6`) downloaded to `dl/wporg_woocommerce/woocommerce`. The functions cited below (`wc_setup_product_data`, shortcodes, `CartCheckoutUtils`) are long-standing, but pin 10.4.3 (WooGraphQL's tested-up-to) for the run. |
| WP core reference | `class-wp-query.php` from wordpress-develop 6.8.3 (`dl/wp_core_wpquery/`): `setup_postdata()` fires `do_action_ref_array( 'the_post', ... )` at :4856; `reset_postdata()` :5082-5087 only acts when `$wp_query->post` is non-empty |
| Core WPGraphQL "main" | `origin/main` `src/Model/Model.php:425-427` (setup/tear_down around the FIRST read of each model field; values are memoized by `__get`), `src/Model/Post.php:165` setup, Post has no tear_down (base no-op `Model.php:175`), `src/Router.php:78` (request handled on `parse_request`, so the main `WP_Query` never runs and `$wp_query->post` stays empty; `did_action('wp')` is 0) |
| Core WPGraphQL "new" | working tree, branch `fix/model-restore-global-query` (`166eff865`, `d54073f9a`): `src/Model/NodeContext.php` (`GLOBALS` list :37, `enter()` :111-155 called at the start of every field from `src/Utils/InstrumentSchema.php:109`, `run_as()` :171-189 used for model field reads at `src/Model/Model.php:457`, `sets_up_context()` :268-277 = any `Model` subclass whose `setup()` is not `Model::setup`, `restore_global_state()` :309-325). A node is set up once when resolution enters its subtree. Globals mutated by a field inside the node persist for later fields in that node. `Deferred` callbacks get no `enter()`, so they see whatever context was entered last. |

```bash
wp plugin install wp-graphql --activate
wp plugin install woocommerce --version=10.4.3 --activate
wp plugin install https://github.com/wp-graphql/wp-graphql-woocommerce/releases/download/v1.0.3/wp-graphql-woocommerce.zip --activate
```

## 2. How the extension registers fields, and how its models relate to core

**Model hierarchy (category 6).** `WC_Post extends WPGraphQL\Model\Post` (`includes/model/class-wc-post.php:24`, constructor calls `parent::__construct( get_post( $id ) )` :39-58). `Product` (`includes/model/class-product.php:98`), `Product_Variation` (`includes/model/class-product-variation.php:67`) and `Coupon` (`includes/model/class-coupon.php:48`) extend `WC_Post`. None of the model classes override `setup()` or `tear_down()` (`grep "function setup\|function tear_down" includes/model` finds nothing). So they inherit `Post::setup()`, `NodeContext::sets_up_context()` (declaring class is `WPGraphQL\Model\Post`, not `Model`) returns true, and **products, variations and coupons ARE recognized as nodes** by the new logic. Their `setup()` is Post's: `parse_query( [ 'product' => slug, 'post_type' => 'product', 'name' => slug ] )` etc., `setup_postdata()`, `$GLOBALS['post']`, queried object.
`Order` (`includes/model/class-order.php:81`), `Customer` (`class-customer.php:43`), `Order_Item` (`class-order-item.php:67`), `Shipping_Method` (`class-shipping-method.php:29`), `Shipping_Zone` (`class-shipping-zone.php:31`), `Tax_Rate` (`class-tax-rate.php:55`) extend `WPGraphQL\Model\Model` directly with no `setup()`: transparent to NodeContext (like core's Comment). Same as today (they never set anything up).

**Side effect every product node has.** `WP_Query::setup_postdata()` fires `the_post` (`class-wp-query.php:4856`), and WooCommerce always hooks `wc_setup_product_data` to it (`includes/wc-template-functions.php:160-175`; the file is always loaded via `after_setup_theme`, `includes/class-woocommerce.php:338` / :982-984). So `Post::setup()` on a product or variation sets `$GLOBALS['product'] = wc_get_product( $post )`, and on any non-product Post node it `unset( $GLOBALS['product'] )`. **`'product'` is not in `NodeContext::GLOBALS` (`NodeContext.php:37`)**, so it is never captured or restored.

| Registration | File:line |
| --- | --- |
| `product` / `product_variation` / `shop_coupon` post types into the schema (`model` => WooGraphQL model, `graphql_kind` interface, `graphql_resolve_type`) | `includes/class-post-types.php:142-183` (product :143-153, variation :155-175, coupon :177-183) |
| Product / ProductUnion resolveType (`Post_Types::resolve_product_type`) reads `$value->post_type`, `$value->ID` | `includes/class-post-types.php:351-356`; variation `resolve_product_variation_type` :396-411 |
| `RootQuery.product(id, idType)` | `includes/type/interface/class-product.php:30-101` (local `new \WP_Query` :62-70 for SLUG, returns `Factory::resolve_crud_object` deferred :98) |
| `Product.description(format)` -> model `description` (applies `the_content`) | interface `includes/type/interface/class-product.php:135-155`; model `includes/model/class-product.php:244-249` |
| `Product.shortDescription(format)` -> model `shortDescription` (applies `get_the_excerpt` + `the_excerpt`, NOT `woocommerce_short_description`, no `do_shortcode`) | interface `class-product.php:156-177`; model `class-product.php:253-265` |
| `Product.image` (`load_deferred( $source->image_id )`) | `includes/type/interface/class-product.php:232-243` |
| `ProductWithPricing.price/regularPrice/salePrice(format)` -> model fields using `wc_get_price_to_display()` / `get_variation_prices()` | `includes/type/interface/class-product-with-pricing.php:54-122`; model `class-product.php:360-390` (variable :477-502); variation model `class-product-variation.php:121-135` |
| `ProductWithVariations.variations` (`post_parent = $source->ID`) | `includes/type/interface/class-product-with-variations.php:70-80` |
| `RootQuery.products` | `includes/connection/class-products.php:28-37` |
| `Product.related` (`wc_get_related_products( $source->ID )`) | `includes/connection/class-products.php:120-155` (call :148) |
| `Product.upsell` (`$source->upsell_ids`) | `class-products.php:156-170` |
| `GroupProduct.products` (`$source->grouped_ids`) | `class-products.php:172-180` |
| `SimpleProduct.crossSell`, `VariableProduct.crossSell` (`$source->cross_sell_ids`) | `class-products.php:187-205` |
| `Product.parent` (`$source->parent_id`) | `class-products.php:206-224` |
| `RootQuery.cart` (`Factory::resolve_cart()` = `WC()->cart`) | `includes/type/object/class-root-query.php:32-89`; `includes/data/class-factory.php:138-140` |
| `Cart.total` etc. (read precomputed `$source->get_totals()`) | `includes/type/object/class-cart-type.php:328-352` |
| `CartItem.product` / `CartItem.variation` (one-to-one connections) | `includes/type/interface/class-cart-item.php:264-296`, :297-330 |
| `RootQuery.collectionStats` (Store API `ProductQueryFilters`, explicit request) | `includes/type/object/class-root-query.php:703-800` (:743) |
| `Customer.sessionToken`, `User.wooSessionToken` (return `new Deferred`) | `includes/type/object/class-customer-type.php:348-374`, :380-410 |
| `addToCart` mutation | `includes/mutation/class-cart-add-item.php:28-29` (payload `cartItem` :85-90, `cart` :91) |

**Global reads inside the extension.** `grep` for `global $post|$product|$wp_query`, `$GLOBALS[`, `get_post()` no arg, `get_the_ID`, `get_queried_object`, `is_*()` conditionals, `setup_postdata`, `wp_reset_postdata`, `wp_reset_query`, `query_posts`, `switch_to_blog`, `wc_setup_product_data`, `do_shortcode`, `render_block`, `do_blocks` over `includes/`, `access-functions.php`, `wp-graphql-woocommerce.php` finds only `class-product.php:247` (`the_content`) and :257/:264 (`get_the_excerpt`, `the_excerpt`). `global $wp` in `class-payment-method-set-default.php:87` and `class-payment-method-delete.php:79` is declared and never used. Every `wc_get_product()` call passes an ID (no reliance on the global-`$post` fallback in `class-wc-product-factory.php:133-138`). `WC()->query->get_catalog_ordering_args()` is always called with an explicit orderby (`class-product-connection-resolver.php:163,167,412`), so its `get_query_var()`/`is_search()` branch (`class-wc-query.php:703-721`) never runs. `is_cart()`/`is_checkout()` are always false in a `/graphql` HTTP request (`CartCheckoutUtils::is_page_type()` returns null when `! did_action( 'wp' )`, `src/Blocks/Utils/CartCheckoutUtils.php:35-38`).

## 3. Risky fields

| `Type.field` | Resolver file:line | Global(s) read (downstream file:line) | Cat. | Why, under the NEW behavior |
| --- | --- | --- | --- | --- |
| `Product.description` (and core `Product.content`, same `post_content` through `the_content`, `src/Model/Post.php` `contentRendered`) | `includes/model/class-product.php:244-249` via `includes/type/interface/class-product.php:148-154` | `the_content` -> `do_shortcode` -> WC `[product_page]` (`includes/class-wc-shortcodes.php:622-649`: `$single_product->the_post()`, then `wp_reset_postdata()` which is a no-op because `$wp_query->post` is empty) and `[products]`/`[product]` (`includes/shortcodes/class-wc-shortcode-products.php:640-687`: restores `$GLOBALS['post']` but leaves `$id`, `$pages`, ... and `$GLOBALS['product']` on the last looped product) | **5 + 2** | The shortcode overwrites the node's `$post`/`$product`/postdata and nothing puts them back inside the node. Today every model field's first read re-runs `Post::setup()` (`origin/main Model.php:425`), so `content`, or any later model field, heals. With the new behavior the product node is set up once and `run_as()` skips setup while the product is the active model (`NodeContext.php:172-174`), so every field resolved AFTER `description` in the same product node (core `content` blocks using the default `postId` context, WooGraphQL fields, third-party fields) sees the shortcode's product. **Regression, order-dependent.** It is restored correctly once resolution leaves the node. Content without such shortcodes is SAFE: dynamic blocks/shortcodes now always see the node product. |
| `$GLOBALS['product']` across nested product nodes: fields of the OUTER `Product` selected after `variations`, `related`, `upsell`, `crossSell`, `parent`, `GroupProduct.products` | connections at `class-product-with-variations.php:70-80`, `includes/connection/class-products.php:120-224` | `$GLOBALS['product']`, set by `wc_setup_product_data` on `the_post` (`includes/wc-template-functions.php:160-175`) during each nested node's `Post::setup()`; not in `NodeContext::GLOBALS` (`NodeContext.php:37`) | **5 + 2** | After the nested variation/product node pops, `$post`, postdata and `$wp_query` are restored to the outer product, but `$product` stays the LAST nested product. Today each outer field's first read re-runs setup and `the_post` resets `$product`. The new behavior does not re-run setup, so `$post` = outer and `$product` = inner, which is inconsistent. WooGraphQL itself never reads `$product`; readers are WC blocks that fall back to it (e.g. `src/Blocks/BlockTypes/ProductStockIndicator.php:79-92`, only when the block has no `postId` context) and third-party price, currency or dynamic-pricing filters on `woocommerce_product_get_price` and similar hooks that read `global $product` (unverified, ecosystem). |
| `$GLOBALS['product']` after leaving a product node: root fields, sibling nodes, mutations in a batch (e.g. `RootQuery.cart`, `RootQuery.customer`, `RootQuery.collectionStats` selected after `product`) | `class-root-query.php:32-89` etc. | same as above; plus `$post`, `is_product()` (`is_singular('product')`), `get_queried_object()` | **1** | Today, after a product node, root fields see leaked `$post` = product, `is_single`/`is_product()` true, and queried object = product. The new behavior gives `$post` null, `is_product()` false, queried object 0, **but `$product` is still the last product**. WooGraphQL's root resolvers and the WC core code they call (`WC()->cart` totals, Store API filters, session) read none of these (see section 2 grep), so no WooGraphQL-visible change is expected. Third-party cart/price filters that branch on `is_product()` or `global $product` would change (unverified). |
| `Product`/`ProductUnion`/`ProductVariation` type resolution (abstract type, not a field) | `includes/class-post-types.php:351-356` (`$value->post_type`, `$value->ID`), :405-411 (`$value->databaseId` fallback) | model field reads -> `Post::setup()` -> `the_post` -> `$GLOBALS['product']` | **5** (low) | resolveType runs before the product's first field calls `enter()`, so these first reads go through `run_as()`. That means a full setup + tear_down + restore around each read, inside whatever node context is active at that moment (possibly a different branch). `$post` and `$wp_query` are restored, but the `the_post` side effect leaves `$product` = this product. This only feeds the `$product` staleness above. Today the same reads also run setup (and leak everything). Perf: 1-2 extra setup/restore cycles per product resolved. |

### SAFE (checked)

| `Type.field` | File:line | Reason |
| --- | --- | --- |
| `Product.shortDescription` | `class-product.php:253-265` | Passes an explicit `get_post( id )` to `get_the_excerpt`. `the_excerpt` has no `do_shortcode` by default. Runs inside its node in both behaviors. |
| `ProductWithPricing.price/regularPrice/salePrice` (any `format`), variation prices, `onSale`, `purchasable`, `addToCartText` | `class-product-with-pricing.php:54-122`; `class-product.php:167-204, 293-298, 360-390, 477-502, 528-534`; `class-product-variation.php:121-135` | `wc_get_price_to_display()` uses the product object, options and `WC()->customer` tax location. No post/query globals in WC core. Inside the node in both behaviors. |
| `Product.related/upsell/crossSell/parent`, `GroupProduct.products`, `ProductWithVariations.variations`, `RootQuery.products` | `class-products.php:28-224`; `class-product-with-variations.php:70-80` | Read model fields of their own source (inside node). Queries are secondary `WP_Query`s with explicit args. WC's `pre_get_posts` only touches the main query. |
| `Product.image` | `class-product.php:232-243` | Reads `image_id` synchronously in node, then `load_deferred`. Cat 3 with no global read. |
| `Customer.sessionToken`, `User.wooSessionToken` | `class-customer-type.php:358-372`, :390-404 | These are the only `new Deferred` in the extension (`grep "new Deferred" includes`). The callbacks run with no `enter()`, under whatever node was entered last (category 3 is real in general), but they read only `WC()->session`, so they are unaffected. All other deferral is `load_deferred()` via `WC_CPT_Loader` (`includes/data/loader/class-wc-cpt-loader.php:76-190`). Its batch callback runs a secondary `WP_Query` and constructs models, and a model constructor reads no globals. Model field reads happen later inside the node's own fields. |
| `RootQuery.product(idType: SLUG)` | `class-product.php:62-70` | Local `WP_Query` with `fields => ids`. Does not touch the main query or `$post`. |
| `RootQuery.collectionStats` | `class-root-query.php:703-800` | Store API `ProductQueryFilters` builds its own SQL (`src/StoreApi/Utilities/ProductQueryFilters.php` only uses `$wpdb`). |
| `addToCart`, cart/checkout mutations (cat 4) | `includes/mutation/class-cart-*.php`, `includes/data/mutation/class-cart-mutation.php:54,80`, `class-checkout-mutation.php` | Explicit product IDs. `is_checkout()`/`is_cart()` are always false in a GraphQL HTTP request (`CartCheckoutUtils.php:35-38`). Payload `product` nodes are scoped like any node. |
| Models (cat 6) | section 2 | Products, variations and coupons are recognized as Post nodes via inherited `Post::setup()`. Order, Customer, etc. are transparent. No overridden setup/tear_down to conflict with. |

**Suggested core-side mitigations (for the PR author, not verified):** (1) `Post::setup()` could also set `$wp_query->post` (and `posts`/`post_count`) to the node, so a `wp_reset_postdata()` inside a node restores to the node, not to nothing. That fixes row 1 and also re-fires `the_post`, which resets `$product`. (2) Make the captured-globals list filterable (e.g. a `graphql_*` filter over `NodeContext::GLOBALS`) so WooGraphQL can add `product`, or re-run `setup_postdata()` for the outer node on `pop()`.

## 4. Fixtures (wp-cli)

```bash
# Probe fields (test-only mu-plugin, NOT part of WooGraphQL): report the globals a resolver sees.
MU=$(wp eval 'echo WPMU_PLUGIN_DIR;'); mkdir -p "$MU"
cat > "$MU/wcg-probe.php" <<'PHP'
<?php
add_action( 'graphql_register_types', function () {
	$probe = function () {
		global $post;
		$p = ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ) ? $GLOBALS['product']->get_id() : null;
		return wp_json_encode( [
			'post'      => $post instanceof WP_Post ? $post->ID : null,
			'product'   => $p,
			'queried'   => get_queried_object_id(),
			'isProduct' => function_exists( 'is_product' ) ? is_product() : null,
		] );
	};
	foreach ( [ 'RootQuery', 'Product', 'ProductVariation' ] as $type ) {
		register_graphql_field( $type, 'probeGlobals', [ 'type' => 'String', 'resolve' => $probe ] );
	}
} );
PHP

# P2: plain target product (its title is the "wrong" value we look for).
P2=$(wp wc product create --user=1 --name="Probe Target P2" --type=simple --regular_price=20 --status=publish --porcelain)
# P1: description = dynamic block reading global $post + WC shortcode that runs its own loop.
P1=$(wp wc product create --user=1 --name="Shortcode Host P1" --type=simple --regular_price=10 --status=publish \
  --description="<!-- wp:post-title {\"level\":2} /-->
[product_page id=\"$P2\"]" --porcelain)
# P3: [products] loop (restores $post, leaves $product/postdata on the looped product).
P3=$(wp wc product create --user=1 --name="Loop Host P3" --type=simple --regular_price=30 --status=publish \
  --description="[products ids=\"$P2\"]
<!-- wp:post-title {\"level\":2} /-->" --porcelain)

# Variable product V with two variations (nested ProductVariation nodes).
ATTR=$(wp wc product_attribute create --user=1 --name=Color --slug=color --porcelain)
wp wc product_attribute_term create "$ATTR" --user=1 --name=Red
wp wc product_attribute_term create "$ATTR" --user=1 --name=Blue
V=$(wp wc product create --user=1 --name="Variable V" --type=variable --status=publish \
  --description='<!-- wp:post-title {"level":2} /-->' \
  --attributes="[{\"id\":$ATTR,\"variation\":true,\"visible\":true,\"options\":[\"Red\",\"Blue\"]}]" --porcelain)
V1=$(wp wc product_variation create "$V" --user=1 --regular_price=11 --attributes="[{\"id\":$ATTR,\"option\":\"Red\"}]" --porcelain)
V2=$(wp wc product_variation create "$V" --user=1 --regular_price=12 --attributes="[{\"id\":$ATTR,\"option\":\"Blue\"}]" --porcelain)
# Upsell so P2 nests under V via Product.upsell.
wp wc product update "$V" --user=1 --upsell_ids="[$P2]"

echo "{\"p1\":$P1,\"p2\":$P2,\"p3\":$P3,\"v\":$V,\"v1\":$V1,\"v2\":$V2}"
```

Settings: none required. The WooGraphQL session handler is on by default (`woographql_setting( 'disable_ql_session_handler', 'off' )`, `includes/class-woocommerce.php:93`), and a guest cart works within one request. If `[product_page]` emits PHP notices in the `/graphql` context (it renders the full single-product template), the probe-based queries (Q3, Q5) still show the `$product` effect.

## 5. Queries

Variables for all queries (substitute the echoed IDs):

```json
{ "p1": 101, "p2": 102, "p3": 103, "v": 104, "include": [101, 102, 103] }
```

Q1 (a) single node. Compare the probe and prices. Expected on both: `probeGlobals` = `{"post":P2,"product":P2,"queried":P2,"isProduct":true}`, same prices. The new behavior should show no difference here.

```graphql
query Q1($p2: ID!) {
  product(id: $p2, idType: DATABASE_ID) {
    databaseId
    probeGlobals
    ... on SimpleProduct { price regularPrice(format: RAW) salePrice }
    shortDescription
    description
  }
}
```

Q2 (b) list of 3 differing products (checks for wrong-product bleed). For each node, `probeGlobals.post` and `.product` should equal `databaseId` on both. For the P1 node, `content`'s `<h2 class="wp-block-post-title">` text: main = "Shortcode Host P1"; new = "Probe Target P2" if `description` resolved earlier in that node (it does here). The P3 node's title stays P3 on both, because `[products]` restores `$post`.

```graphql
query Q2($include: [Int]) {
  products(first: 3, where: { include: $include }) {
    nodes { databaseId probeGlobals description content }
  }
}
```

Q3 (c) nested nodes (variations + upsell under V). Compare `after` with `before`. Main: `after.post` = V2's ID or P2's ID (leaked from the last nested node). New: `after.post` = V (fixed), **`after.product` = last nested product (P2), not V** (the `$product` staleness). Inside variations, `probeGlobals.post` and `.product` should equal that variation on both.

```graphql
query Q3($v: ID!) {
  product(id: $v, idType: DATABASE_ID) {
    databaseId
    before: probeGlobals
    ... on VariableProduct {
      price
      variations { nodes { databaseId probeGlobals price } }
    }
    upsell { nodes { databaseId probeGlobals } }
    after: probeGlobals
    content
  }
}
```

Q4a / Q4b (d) the same field before vs after the model field that runs the shortcode. Compare the post-title block inside `content`. Main: "Shortcode Host P1" in both. New: Q4a renders "Probe Target P2" (regression), Q4b renders "Shortcode Host P1".

```graphql
query Q4a($p1: ID!) {
  product(id: $p1, idType: DATABASE_ID) { description content probeGlobals }
}
```

```graphql
query Q4b($p1: ID!) {
  product(id: $p1, idType: DATABASE_ID) { content description probeGlobals }
}
```

Q5 (e) the "#4572 shape": root fields after a product node. Main: root `probeGlobals` = `{"post":P2,...,"queried":P1,"isProduct":true}` (leaked; `$post` is P2 because of the shortcode). New: `{"post":null,"product":P2,"queried":0,"isProduct":false}`, so the restored state still has a stale `product`. `cart.total` and `isEmpty` are identical on both.

```graphql
query Q5($p1: ID!) {
  product(id: $p1, idType: DATABASE_ID) { databaseId description }
  probeGlobals
  cart { total isEmpty }
}
```

Q6 (f) mutation (cat 4 sanity check). Expect identical `cart.total`. The nested `probeGlobals` should equal P2 on both. The mutation resolver itself reads no globals.

```graphql
mutation Q6($p2: Int!) {
  addToCart(input: { productId: $p2, quantity: 1 }) {
    cart { total }
    cartItem { key quantity product { node { databaseId probeGlobals } } }
  }
}
```

(`Q6` variables: `{ "p2": 102 }`, since `productId` is `Int`, `includes/mutation/class-cart-add-item.php:45`.)
