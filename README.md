# Extension compatibility tests: node-scoped context

Test kit and results for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575), which fixes [#4572](https://github.com/wp-graphql/wp-graphql/issues/4572). This branch isn't meant to be merged. It holds the queries, fixtures and responses we used to check the change against popular WPGraphQL extensions, so extension maintainers can see exactly what was tested for their plugin and re-run it.

## The change, briefly

While a post, term or user resolves, WPGraphQL sets it up as the current object (the main query, the global `$post`, `$authordata`), so WordPress functions and filters that read "the current" object see it. Before #4575 that state was set up before each of the node's own model fields and never put back, so the last object resolved stayed current for the rest of the request.

With #4575, a node is the current object for every field selected under it, including fields other plugins register and nested values such as blocks, and the state from before is restored once resolution leaves it. A node is set up again if a field changes the global post, author or queried object, and connections run their deferred callbacks with their own node's state. See the [Node Context and Global State](https://github.com/wp-graphql/wp-graphql/blob/fix/model-restore-global-query/plugins/wp-graphql/docs/node-context.md) docs.

## Builds compared

| Label | Commit | Behavior |
| --- | --- | --- |
| `main` | `c0dc6a95a` | Setup before each model field, never restored (#4572). |
| `original-pr` | `71b90651a` | The first version of #4575: restored after each model field. |
| `node-context` | `d2553540c` | Node-scoped context, the version under review. |

Later commits on the PR add docs and tests, and remove a redundant `Post::tear_down()` whose only effect was when the main query already had a post (a `graphql()` call inside a theme's loop), which these HTTP queries never are.

## Results

85 queries in 8 groups, each run twice per build (both runs matched once the tokens listed under Notes were normalized).

| Extension | Version | Queries | The extension's own fields, `node-context` vs `main` |
| --- | --- | --- | --- |
| WPGraphQL for Yoast SEO | 5.1.0 (Yoast SEO 28.7) | 21 | 2 fixed, the rest identical. `fullHead`, schema and breadcrumbs unchanged. |
| Faust | 1.9.0 | 11 | 7 fixed. |
| WPGraphQL for ACF (WYSIWYG fields) | 3.0.0 (ACF PRO 6.8.4) | 13 | 10 fixed. |
| WPGraphQL for Rank Math | 0.3.4 (Rank Math 1.0.280) | 10 | Identical. |
| WPGraphQL WooCommerce | 1.0.3 (WooCommerce 10.4.3) | 10 | Identical. |
| WPGraphQL Content Blocks | 4.8.9 | 11 | Identical. |
| WPGraphQL Polylang | 0.7.1 (Polylang 3.9.1) | 6 | Identical. |
| WPGraphQL Headless Login | 0.4.4 | 3 | Identical. |

No regressions: every difference from `main` is a value that now comes from the right node. The queries also include a test-only probe field (`probe/`) that reports the current object at various points; its values changed only where they now describe the right node. For comparison, `original-pr` had 25 regressions across the same queries (for example, Yoast schema losing the primary image and the Blog breadcrumb, Faust `templates` returning the front page's for every node, and Content Blocks rendering the wrong post's blocks in lists).

**Fixed, with no report we could find:**

- Faust's `templates` was off by one in lists (each post got the previous post's templates).
- Galleries without explicit IDs in WPGraphQL for ACF WYSIWYG fields on options pages, terms and users showed images from whichever post resolved before them.
- A WPGraphQL for Yoast SEO taxonomy term's canonical URL read `/graphql/` when a media item resolved before it.

**Possibly related, not confirmed:** [wpengine/faustjs#477](https://github.com/wpengine/faustjs/issues/477) (doesn't reproduce on current `main` in the shapes we tried), and [ashhitch/wp-graphql-yoast-seo#49](https://github.com/ashhitch/wp-graphql-yoast-seo/issues/49) and [#176](https://github.com/ashhitch/wp-graphql-yoast-seo/issues/176) (not reproduced).

**Not tested:** WPGraphQL for Gravity Forms (paid base plugin), authenticated requests (previews, restricted fields), multisite, and other PHP and WordPress versions.

## Environment

wp-env site, WordPress 7.0, PHP 8.3, Twenty Twenty-Five, `/%postname%/` permalinks, guest POST requests, with ACF PRO 6.8.4, WPGraphQL for ACF 3.0.0, WPGraphQL Smart Cache 2.3.4 (object cache off) and WPGraphQL IDE 5.7.0 always active. One group of extensions active at a time:

| Group | Plugins | Notes |
| --- | --- | --- |
| Content | WPGraphQL Content Blocks, Faust (with the ACF canaries) | |
| Yoast | Yoast SEO, WPGraphQL for Yoast SEO | Not together with Rank Math: both register `seo`. |
| Rank Math | Rank Math, WPGraphQL for Rank Math | Set the `rank_math_registration_skip` option to `1` (without it every `seo` field errors on every build), and keep the Redirections module off. |
| Commerce | WooCommerce 10.4.3, WPGraphQL WooCommerce 1.0.3 | WooGraphQL needs PHP 8.1+. |
| Polylang | Polylang, WPGraphQL Polylang | Smoke test with a reduced fixture. |
| Headless Login | WPGraphQL Headless Login | Smoke test. |

## What's here

```
queries/<group>/   one query per .graphql file (+ .vars.json); "#### next-operation" splits a batch request
fixtures/          <group>.sh creates the content a group's queries use and writes <group>.ids
probe/             test-only mu-plugin: the probe fields, plus the test types and ACF settings some queries use (never on a real site)
results/<group>/   saved responses per build: main/, original-pr/, node-context/
research/          the source-reading notes written before any query ran (background, not results)
tools/             matrix2.py runs a query directory, matrix.py diffs two runs, normalize.py removes known noise
```

Fixture and content IDs are specific to our test site, so compare runs on your own site across builds rather than against these responses directly.

## Re-running a group

1. On a test site, activate WPGraphQL (the build to test) and the group's plugins, and copy `probe/zz-ext-matrix-probe.php` into `wp-content/mu-plugins/`.
2. Create the fixtures: `WP="wp --path=/path/to/site" zsh fixtures/content.sh` (writes `fixtures/content.ids`). The content and Yoast fixtures import a placeholder image from `/tmp/extmx.png` on the site's machine, so put any small PNG there first.
3. Run the queries: `python3 tools/matrix2.py run --queries=queries/faust --out=out/faust --label=main --ids=fixtures/content.ids --endpoint=https://example.test/graphql`
4. Switch WPGraphQL to the other build and repeat with another `--label`.
5. Normalize once both runs exist: `python3 tools/normalize.py out/faust`
6. Compare: `python3 tools/matrix.py diff --queries=queries/faust --out=out/faust --a=main --b=node-context`

The content, Faust and ACF query groups share `fixtures/content.ids`. The other groups each have their own.

## Notes

- **Normalized noise:** WooCommerce quantity input IDs, WooCommerce's shuffled "Related products" (replaced by the sorted list of product IDs) and Headless Login tokens. An earlier version of `normalize.py` emptied that product ID list when run twice, which some saved `main` and `original-pr` WooCommerce responses went through, so their related product lists show `[]`. The script is now safe to run more than once.
- **Rank Math `seoScore.badgeHtml`** (`queries/rankmath/rm-b-list-three.graphql`) returns an error for some list items on every build, including `main`: the resolver passes `rank_math_get_seo_score()`'s return value to `str_replace()`, which fails on PHP 8 when that value is an integer. It's unrelated to this change.
- **Content Blocks and query loops:** the research notes predicted that a Query Loop block could leave the global post pointing at the loop's last post for later blocks. On WordPress 7.0 that doesn't happen on any build, because `WP_Block::render()` restores `$post` around each dynamic block.
