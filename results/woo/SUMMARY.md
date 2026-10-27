# WPGraphQL WooCommerce: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

10 queries. Compared with `main`, **#4575** (`node-context`) changed extension output in 0, changed only the probe in 4, and left 6 the same. **The first version of #4575** (`original-pr`) changed extension output in 0, only the probe in 10, and left 0 the same.

## Anything to do?

**No changes needed** for WooGraphQL's own fields, whose output is identical on `main` and with #4575.

Worth considering: WPGraphQL doesn't know about the global `$product`, so it isn't restored when resolution leaves a nested product (variations, related or upsell products), and code from other plugins that reads `$product` can see the inner product for the outer product's later fields. On `main`, the outer product's next model field set it up again, which happened to put `$product` back. [IDEAS.md](../../IDEAS.md), "WPGraphQL WooCommerce: restoring `$product`", shows the fix: a filter and a short method in `includes/class-woocommerce.php`. These queries can't show its effect, since WooGraphQL doesn't read `$product`.

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

## Results by query

Each build's responses were captured twice and matched. Each cell compares that build's response with `main`'s for the same query:

- **Same as `main`**: every value in the response is identical.
- **Extension output**: values from the extension's own fields that differ, as the value on `main` → the value on that build. Long values show the part around the first difference.
- **Probe**: test-only fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), selected at a point in the query, that report what WordPress treats as current there: `post` is the global `$post`, `queried` the main query's queried object, `product` WooCommerce's global `$product`, `authordata` the global `$authordata`, and `singular`, `tax`, `isProduct` and the like are conditional tags. They aren't extension output; they show what an extension reading the current object would see. Inside a node the right answer is that node. At the root, after the nodes, it's whatever was current before the query, which on these requests is nothing (`none`).
- Fixture names such as `P2`, `P1`, `P3` are the ones in [`woo.ids`](../woo.ids), shown with their IDs.

The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), and the queries in [`queries/woo/`](../../queries/woo).

| Query | What it checks | #4575 (`node-context`) | First version of #4575 (`original-pr`) |
| --- | --- | --- | --- |
| `woo-q1-single-product` | one simple product (P2), prices, `shortDescription`, `description`, probe. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `product.probeGlobals`: isProduct true → false, queried P2 (513) → none |
| `woo-q2-list-three` | three products in a list, `description` and `content`, probe in each. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `products.nodes[0].probeGlobals`: isProduct true → false, queried P1 (514) → none<br>**Probe** `products.nodes[1].probeGlobals`: isProduct true → false, queried P2 (513) → none<br>**Probe** `products.nodes[2].probeGlobals`: isProduct true → false, queried P3 (515) → none |
| `woo-q3-nested-variations-upsell` | a variable product with `variations` and `upsell` nested, probes before and after, then `content`. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `product.after`: isProduct true → false, queried V (516) → none<br>**Probe** `product.before`: isProduct true → false, queried V (516) → none<br>**Probe** `product.upsell.nodes[0].probeGlobals`: isProduct true → false, queried P2 (513) → none<br>**Probe** `product.variations.nodes[0].probeGlobals`: post V1 (517) → P2 (513), queried V1 (517) → none<br>**Probe** `product.variations.nodes[1].probeGlobals`: post V2 (518) → P2 (513), queried V2 (518) → none |
| `woo-q4a-description-then-content` | P1, `description` (runs `[product_page]` for P2) before `content`, then probe. | **Extension output:** same as `main`<br>**Probe** `product.probeGlobals`: post P2 (513) → P1 (514), product P2 (513) → P1 (514) | **Extension output:** same as `main`<br>**Probe** `product.probeGlobals`: isProduct true → false, post P2 (513) → P1 (514), queried P1 (514) → none |
| `woo-q4b-content-then-description` | the same with `content` first. | **Extension output:** same as `main`<br>**Probe** `product.probeGlobals`: post P2 (513) → P1 (514), product P2 (513) → P1 (514) | **Extension output:** same as `main`<br>**Probe** `product.probeGlobals`: isProduct true → false, post P2 (513) → P1 (514), queried P1 (514) → none |
| `woo-q4c-products-loop-then-content` | P3, `description` (runs a `[products]` loop for P2) before `content`, probe, `name`. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `product.probeGlobals`: isProduct true → false, queried P3 (515) → none |
| `woo-q5-root-after-product` | root `probeGlobals`, `probeGlobalsLate` and `cart` after P1's `description`. | **Extension output:** same as `main`<br>**Probe** `lateObj`: post P2 (513) → none, queried P1 (514) → none, singular true → false | **Extension output:** same as `main`<br>**Probe** `lateObj`: post P2 (513) → P1 (514), queried P1 (514) → none, singular true → false |
| `woo-q6-mutation-add-to-cart` | `addToCart`, with the payload cart item's product and probe. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `addToCart.cartItem.product.node.probeGlobals`: isProduct true → false, queried P2 (513) → none |
| `woo-q7-sibling-products-after-shortcode` | P3 resolved after P1's `[product_page]` description, probe in P3 and a late root probe. | **Extension output:** same as `main`<br>**Probe** `lateObj`: post P3 (515) → none, product P3 (515) → P2 (513), queried P3 (515) → none, singular true → false | **Extension output:** same as `main`<br>**Probe** `b.s0`: isProduct true → false, queried P3 (515) → none<br>**Probe** `lateObj`: queried P3 (515) → none, singular true → false |
| `woo-q8-variations-then-outer-price` | a variable product's `variations`, then probe, `price`, `regularPrice` and `name` on the outer product. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `product.after`: isProduct true → false, queried V (516) → none |
