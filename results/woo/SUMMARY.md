# WPGraphQL WooCommerce: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

10 queries. Against `main`: **node-context** (#4575 as it stands) has 6 identical, 4 changed only in test probe fields, 0 with changed extension output; **original-pr** (the first version of #4575) has 0 identical, 10 probe only, 0 with changed extension output.

## Findings

**What in the extension reads the current object.** WooGraphQL's own fields don't read `$post` or `$product` (version 1.0.3, with WooCommerce 10.4.3):
- `Product.description` applies `the_content` (`includes/model/class-product.php:244-249`), so shortcodes and blocks in it see the current post.
- `shortDescription` passes an explicit post (`:253-264`).

The product models extend core's Post model (`includes/model/class-wc-post.php:24`, `class-product.php:98`, `class-product-variation.php:67`) and don't override `setup()`, so products and variations are nodes. Setting a post up fires `the_post`, which WooCommerce's `wc_setup_product_data()` uses to set the global `$product`.

**What was tested.** Single, list, nested variations and upsell, a description with `[product_page]` before and after `content`, a `[products]` loop, root fields and `cart` after a product, the `addToCart` mutation, and sibling products. The `probeGlobals` probe reports `$post`, `$product`, the queried object and `is_product()`.

**Before (`main`).** Extension values were right on every build: prices, descriptions, and the post-title block in `content` (`"[ext-matrix] Shortcode Host P1"`). The research predicted `content` after `description` would render P2's title; that didn't happen. The probes showed the leaks. After P1's `description` ran `[product_page]` for P2, the probe in P1 reported `{"post":513,"product":513,"queried":514}` (P2 as post and product). A late root probe reported P2 as the current post.

**After (`node-context`).** Extension output is identical in all 10 queries. Probes changed in 4:
- **In P1** (`woo-q4a`, `woo-q4b`), the probe reports `{"post":514,"product":514,...}`. A field that moves the global post now gets the node set up again.
- **At the root** (`woo-q5`, `woo-q7`), there is no current post, but `$product` is still a product the shortcode set (`"product":513`), because core doesn't save or restore `$product`.
- **Under P3** (`woo-q4c`), on every build, the `[products]` loop restores `$post` but leaves `$product` on P2 (`{"post":515,"product":513}`).

**First version of #4575 (`original-pr`).** No extension values changed. All 10 queries showed probe changes: inside products `is_product()` was false and the queried object 0, and both variations' probes reported P2 (`"post":513`).

**Anything to do.** Nothing for correctness of WooGraphQL's own fields. [IDEAS.md](../../IDEAS.md), "WPGraphQL WooCommerce: restoring `$product`", shows adding `product` to the restored globals with the `graphql_model_node_context_globals` filter. These queries can't show its effect on WooGraphQL output, since WooGraphQL doesn't read `$product`.

## Results by query

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/woo/`](../../queries/woo), and the fixture IDs they used in [`woo.ids`](../woo.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `woo-q1-single-product` | one simple product (P2), prices, `shortDescription`, `description`, probe. | identical | probe only |
| `woo-q2-list-three` | three products in a list, `description` and `content`, probe in each. | identical | probe only |
| `woo-q3-nested-variations-upsell` | a variable product with `variations` and `upsell` nested, probes before and after, then `content`. | identical | probe only |
| `woo-q4a-description-then-content` | P1, `description` (runs `[product_page]` for P2) before `content`, then probe. | probe only | probe only |
| `woo-q4b-content-then-description` | the same with `content` first. | probe only | probe only |
| `woo-q4c-products-loop-then-content` | P3, `description` (runs a `[products]` loop for P2) before `content`, probe, `name`. | identical | probe only |
| `woo-q5-root-after-product` | root `probeGlobals`, `probeGlobalsLate` and `cart` after P1's `description`. | probe only | probe only |
| `woo-q6-mutation-add-to-cart` | `addToCart`, with the payload cart item's product and probe. | identical | probe only |
| `woo-q7-sibling-products-after-shortcode` | P3 resolved after P1's `[product_page]` description, probe in P3 and a late root probe. | probe only | probe only |
| `woo-q8-variations-then-outer-price` | a variable product's `variations`, then probe, `price`, `regularPrice` and `name` on the outer product. | identical | probe only |
