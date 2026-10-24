# Extension matrix: node-scoped context (WPGraphQL core)

> **Read this as background, not results.** These notes come from reading each extension's source before any query was run, against an earlier prototype of the change (`d54073f9a`). Several predictions didn't hold, and the final build differs from that prototype (it re-applies a node whose globals a field changed, keeps per-field setup for models with their own `setup()`, and binds connection callbacks). What was actually observed is in the top-level [README](../README.md).

This matrix tests the core change that moves model `setup()` from "around each model-field read, and leaked afterwards" to "scoped to the node's part of the query". The core code checked is the working tree on `fix/model-restore-global-query` (commits `166eff865`..`d54073f9a`): `plugins/wp-graphql/src/Model/NodeContext.php`, called from `src/Utils/InstrumentSchema.php:109` (`enter()` before every field), `src/Model/Model.php:457` (`run_as()` for model-field reads outside the node), and `src/Request.php:697` (`end_request()`).

Everything here is static source reading. **No query has been executed**, so every "expected" value is a prediction to confirm on wp-env.

## How the implementation differs from the original brief

These points matter for every file in this folder.

1. **A node is any `Model` subclass that overrides `setup()`** (`NodeContext.php:268-277`, by reflection), not only Post, Term and User.
   - In core that is Post, Term and User. WooGraphQL's `Product`, `ProductVariation` and `Coupon` inherit `Post::setup()`, so they are nodes too.
   - **Rank Math's four SEO models override `setup()`**, so each `seo` object becomes its own stacked node.
2. **`setup()` runs once when resolution enters the node, not before each field.**
   - `enter()` keeps a stack entry while key and model match, and never re-checks the actual globals.
   - So anything a field does to the globals **inside** a node persists for that node's later fields. On main, the next model-field read re-ran `setup()` and "healed" it.
   - This is the main new class of regression. See the top three findings below.
3. **What gets restored on leaving a node:** `$wp_query`'s public properties, plus `post`, `authordata`, `id`, `currentday`, `currentmonth`, `page`, `pages`, `multipage`, `more` and `numpages` (`NodeContext.php:36`). Plugin globals such as `$product` are not restored.
4. **Deferred callbacks run without `enter()`.** They see whatever context was entered last. None of the extensions checked read globals inside a Deferred, so this is theoretical for now.
5. **In a `/graphql` request `$wp_query->post` is empty** (the main query never runs). So any `wp_reset_postdata()` a resolver or core block calls is a no-op: it does not snap back to the node.

## Summary

| Extension | File | Risky (non-SAFE) | Highest-risk finding | Confidence |
| --- | --- | --- | --- | --- |
| WPGraphQL WooCommerce 1.0.3 | [wp-graphql-woocommerce.md](wp-graphql-woocommerce.md) | 4 | `Product.description` (`includes/model/class-product.php:244-249`) runs `the_content`. A `[product_page]` shortcode in it repoints `$post`, the post data globals and `$product`, and its `wp_reset_postdata()` is a no-op. Under the new behavior **every later field in that product node** (including core `Product.content`) sees the wrong product, so field order changes results (Q4a/Q4b). Also: `$product` is not in the restore list, so it leaks after nested `variations` / `related` / `upsell`. | med-high |
| WPGraphQL Content Blocks 4.8.9 | [wp-graphql-content-blocks.md](wp-graphql-content-blocks.md) | 7 rows, 5 fields | Rendering a `core/query` / `core/post-template` block (`renderedHtml`, `includes/Utilities/WPGraphQLHelpers.php:84`; every attribute field, `includes/Blocks/Block.php:290`) loops with `the_post()` (`wp-includes/blocks/post-template.php:105`). Its `wp_reset_postdata()` (:142) is a no-op, so later sibling blocks in the same post render the **loop's last post**: post-title, post-terms (`CorePostTerms.terms` → `get_the_ID()` at `includes/Blocks/CorePostTerms.php:79`). Root fields after the node are fixed by the new behavior (query E: Loop B's ID becomes null); the in-node leak is not. There is also a **new regression**: on main, the next model-field read (`title`) re-ran `Post::setup()` and repaired the leak. Now `run_as()` skips that, so query D's `after` alias goes from `Host Cat` to `Loop Cat`. `renderedHtml` is statically cached per clientId, so a wrong render sticks. | high (paths), med (values) |
| WPGraphQL for Rank Math 0.3.4 | [wp-graphql-rank-math.md](wp-graphql-rank-math.md) | 8 | **New regression.** `seo { jsonLd { raw } fullHead }` makes `fullHead` print **two** JSON-LD blocks. `jsonLd` hooks the JsonLD printer on `rank_math/head` (`src/Model/Seo.php:137-139`, `:332`). On main, `fullHead`'s per-field `setup()` ran `remove_all_actions('rank_math/head')` and wiped it; under the new behavior `setup()` runs once per seo node. The reverse order runs the `rank_math/json_ld` callbacks twice (impact unverified). | high (mechanism) |
| WPGraphQL for Yoast SEO 5.1.0 | [wp-graphql-yoast-seo.md](wp-graphql-yoast-seo.md) | 9 | Root `seo` content-type archive output (`includes/helpers/functions.php:356-386` via `root-query.php:42`). On main, the last leaked node bleeds into every archive's `fullHead` / `schema.raw` / `archive.title` (previous post's featured image as `primaryImageOfPage`, front-page breadcrumbs, `/graphql/` canonical after a MediaItem, `%%pt_plural%%` from the leaked post type). Yoast caches per object for the request, so the first state wins. The new behavior fixes this, but **output changes** for sites that render archive heads from it. One field gets worse: `archive.social.title` becomes a generic "Archive". | high (paths), med (values) |
| Faust (faustwp 1.9.0) | [faustwp.md](faustwp.md) | 2 | `templates` / deprecated `conditionalTags` (`includes/graphql/callbacks.php:172-226`) never read `$root`: they call bare `$wp_query->parse_query()` (`:188`) plus `is_*`. On main, concrete-type lists give node N the templates of node N-1. The new behavior fixes Post/Term/User, but ContentType and Comment (not nodes) now get the original state (`front-page, home, index`, all-false conditionals). Side effect: it removes `prepend_attachment` from `the_content` for the rest of the request (`:202-204`). Low practical impact: the current Faust client works out templates itself. | high (mechanism) |
| WPGraphQL for ACF 3.0.0 (monorepo) | [wp-graphql-acf.md](wp-graphql-acf.md) | 3 (+1 unverified) | Options-page wysiwyg (root field, deferred loader, `Registry.php:428-436`; `the_content` at `FieldConfig.php:651-654`). On main, `[gallery]` without ids renders the leaked post's images, and a leaked attachment makes `prepend_attachment` **prepend attachment markup** into the options HTML. Under the new behavior it gets unattached media and no prefix (a fix, but the output changes). Term wysiwyg changes the same way (category 2). Post-level ACF is a canary that should stay identical. | high |
| WPGraphQL Polylang 0.7.1 | [wp-graphql-polylang.md](wp-graphql-polylang.md) | 0 | `defaultLanguage { homeUrl }` reads `PLL()->curlang`, which the extension keeps false for the whole request (forced admin context, `src/Loader.php:32-43`). The result is the same on both. | high |
| WPGraphQL Headless Login 0.4.4 | [wp-graphql-headless-login.md](wp-graphql-headless-login.md) | 0 | Only current-user state. Login payload `user { auth }` is a smoke test. | high |
| WPGraphQL JWT Authentication 0.7.x | [wp-graphql-jwt-authentication.md](wp-graphql-jwt-authentication.md) | 0 | Only current-user state. | high |
| WPGraphQL Tax Query 0.2.0 / Meta Query 0.1.1 | [wp-graphql-tax-query.md](wp-graphql-tax-query.md) | 0 | Indirect only: nested connections now run their `WP_Query` under the parent node's conditionals, which matters to third-party `pre_get_posts` callbacks that skip `is_main_query()`. | high |
| WPGraphQL Smart Cache 2.3.4 (monorepo) | [wp-graphql-smart-cache.md](wp-graphql-smart-cache.md) | 0 | Global reads are admin-only. Purge caches between main and new runs, because cached responses bake in the old leak. `X-GraphQL-Keys` should be identical. | high |
| WPGraphQL IDE 5.7.0 (monorepo) | [wp-graphql-ide.md](wp-graphql-ide.md) | 0 | Explicit-ID meta reads only. | high |

### Cross-cutting conclusions

- **The biggest new risk is in-node mutation, not missing context** (implementation point 2). Three independent extensions hit it:
  - the Content Blocks query loop;
  - Woo `[product_page]` / `[products]` inside `description`;
  - Rank Math's `rank_math/head` hook.
- Two core-side mitigations the agents converged on (neither tested):
  - (a) Have `Post::setup()` also set `$wp_query->post` to the node, so `wp_reset_postdata()` inside a node lands back on the node. This also re-fires `the_post`, which resets Woo's `$product`.
  - (b) Re-apply a node's context when the actual globals (at least `$GLOBALS['post']`) no longer match what was set up. Watch the performance: Content Blocks can resolve hundreds of block fields per post.
  - Optionally, (c) make the restored-globals list filterable so WooGraphQL can add `product`.
- **Most category 1 and 2 changes are fixes** (Yoast archives, ACF options/term wysiwyg, Faust templates), but they change output. They deserve the manual upgrade notice.
- **Rank Math is the only third-party model subclass with its own `setup()`.** Its `ContentNodeSeo` has no `tear_down`; core's `pop()` restore covers it.
- **No extension reads globals inside a Deferred.** Category 3 is clean across the board. No extension's mutation reads leaked state. Category 4 is clean.

## A shared probe (test site only)

Most category 1 shapes are easiest to see with a root field that reports the current globals. The Content Blocks file defines `debugGlobalPostId`. A fuller version, as an mu-plugin for the **test site only** (never ship it):

```php
<?php // wp-content/mu-plugins/ext-matrix-probe.php (test site only)
add_action( 'graphql_register_types', function () {
	register_graphql_field( 'RootQuery', 'debugGlobals', [
		'type'    => 'String',
		'resolve' => function () {
			global $wp_query, $authordata;
			return wp_json_encode( [
				'post'      => get_the_ID() ?: null,
				'queried'   => get_queried_object_id() ?: null,
				'singular'  => is_singular(),
				'tax'       => is_tax() || is_category() || is_tag(),
				'author'    => is_author(),
				'authordata'=> $authordata->ID ?? null,
				'product'   => isset( $GLOBALS['product'] ) && is_object( $GLOBALS['product'] ) ? $GLOBALS['product']->get_id() : null,
			] );
		},
	] );
} );
```

Append `debugGlobals` at the root after any node in any query to see the #4572 shape. On main it reports the last leaked object; under the new behavior it reports all null/false, except `product`, which still leaks.

## Install order and groupings

Base for all groups:

```bash
wp plugin install wp-graphql --activate          # or the branch build under test
```

Then pick one group per test run. Some extensions conflict, and isolating each one keeps a diff attributable.

| Group | Plugins (in order) | Notes |
| --- | --- | --- |
| A. Content | `secure-custom-fields` → `wpgraphql-acf` → `wp-graphql-content-blocks` → `faustwp` → `wpgraphql-smart-cache` → `wpgraphql-ide` | All compatible. Run the Faust queries alone first; its `templates` resolver calls a bare `parse_query()` that shares `$wp_query` with everything else. Turn Smart Cache's object cache off, or purge between builds. |
| B. Yoast | `wordpress-seo` → `add-wpgraphql-seo` (or the GitHub 5.1.0 zip), optionally `polylang` + wp-graphql-polylang | Do not combine with Rank Math. |
| C. Rank Math | `seo-by-rank-math` → wp-graphql-rank-math 0.3.4 zip | Do not combine with Yoast: both register `seo` on ContentNode, User, the term types and RootQuery. **Keep Rank Math's Redirections module off**: `get_head()` fires `do_action('wp')` with a faked `REQUEST_URI`, and the Redirector can `exit` mid-request. |
| D. Commerce | `woocommerce` (pin **10.4.3**, WooGraphQL's tested-up-to) → wp-graphql-woocommerce 1.0.3 zip → wp-graphql-headless-login 0.4.4 zip | WooGraphQL needs **PHP 8.1+ and WPGraphQL 2.0+**, so skip the PHP 7.4/8.0 cells. Yoast/Rank Math `seo` on `Product` can be layered on in a second pass. |
| E. Auth alternative | wp-graphql-jwt-authentication | **Conflicts with Headless Login** (both register the `login` mutation). Never with group D's Headless Login. |
| F. i18n | `polylang` → wp-graphql-polylang | Forces Polylang into admin context for all GraphQL requests. Any Yoast/Rank Math Polylang integration sees no current language on both builds. |

Install URLs and commands are in each file. Several extensions have no wp.org listing, so their files give GitHub release zips: WooGraphQL, Rank Math, Headless Login, JWT, Polylang (branch zip or composer), and Tax/Meta Query (`develop` branch zip).

## Run procedure (suggested)

1. On the **main** build, activate one group, run its fixture block from the file, then run each query and save the responses.
2. Switch the core plugin to the branch build. Run `wp cache flush`, and purge Smart Cache and the Yoast/Rank Math transients if relevant. Re-run the same queries.
3. Diff the two sets of responses. Each query in the files says what should differ (a fix) and what must be identical (a canary).

## Not covered

- **WPGraphQL for Gravity Forms** (AxeWP/wp-graphql-gravity-forms): the extension is free, but Gravity Forms is paid. Not downloaded or analyzed. Likely low risk (form and entry data by explicit ID), but unverified.
- **WPGraphQL for SearchWP, the Stripe integration**: paid base plugins. WooGraphQL has compatibility hooks for SearchWP and Stripe (`includes/class-compatibility.php`), which were not analyzed.
- **ACF PRO block render templates**: ACF Blocks' `render_template` / `render_callback` run through Content Blocks' `renderedHtml` and read the global post. Under the new behavior they get the node's post. ACF PRO source was not checked.
- **wp-graphql-schema-monitor** (monorepo, experimental): not in scope.
- None of the queries have been executed on wp-env (per the research-only constraint).
