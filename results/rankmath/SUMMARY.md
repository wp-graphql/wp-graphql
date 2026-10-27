# WPGraphQL for Rank Math: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

10 queries. Compared with `main`, **#4575** (`node-context`) changed extension output in 0, changed only the probe in 2, and left 8 the same. **The first version of #4575** (`original-pr`) changed extension output in 0, only the probe in 3, and left 7 the same.

## Anything to do?

**No changes needed.** Extension output is identical on `main` and with #4575.

Optional: [IDEAS.md](../../IDEAS.md), "WPGraphQL for Rank Math: less duplicated setup", sketches how the SEO models could stop repeating core's setup on WPGraphQL versions with node context. It shows the file, its current code, and a sketch that keeps working on older WPGraphQL versions.

## Findings

**What in the extension reads the current object.** Every `seo` object is one of the extension's own models (WPGraphQL for Rank Math 0.3.4). They extend `WPGraphQL\Model\Model` through `Seo` (`src/Model/Seo.php:22`), and their `setup()` makes the object current for Rank Math:
- `ContentNodeSeo::setup()` (`src/Model/ContentNodeSeo.php:68`) parses the main query for the post (`:114-133`) and calls `setup_postdata()` (`:143`). `TermNodeSeo`, `UserSeo` and `ContentTypeSeo` do the same for their objects (`setup()` at `:62`, `:67`, `:63`).
- `Seo::setup()` (`src/Model/Seo.php:101`) rebuilds Rank Math's `Paper` from the current query. Its `setup_post_head()` (`:326`) sets `REQUEST_URI`, runs `remove_all_actions()` on `wp`, `rank_math/head` and `rank_math/json_ld` (`:330-336`), and can set `is_home` (`:339`).
- `fullHead` fires `wp` and `rank_math/head` (`get_head()`, `:256-263`).
- `seoScore.badgeHtml` calls `rank_math_get_seo_score()` (`ContentNodeSeo.php:169-171`), which reads the global post.

**What was tested.** Single, list, nested (author, categories, a page's parent), field order between `jsonLd` and `fullHead`, `seo` before and after core fields, and root `contentType`/`category` `seo` and `rankMathSettings` after a post. Probes were used at the root and inside nodes.

**Before (`main`).** All values were right. The probes showed the leak outside `seo`: in `rm-f`, a probe at the top of the first post reported the other post (`"postTitle":"[ext-matrix] RM Beta"`), and a late root probe reported the last post.

**After (`node-context`).** Extension output is identical in all 10 queries, which is expected. Your models don't opt in to node scope (`is_node_scoped()` defaults to false), so core still runs their `setup()` around each field read and restores the main query and post globals afterwards. That's also why the research's predicted regression, two JSON-LD blocks in `fullHead` when `jsonLd` is selected first, didn't occur: `fullHead` has one `application/ld+json` block on every build (`rm-d1`, `rm-d4`). Only probes changed (`rm-e`, `rm-f`), and they now report each post inside its own node and no current post at the root.

**First version of #4575 (`original-pr`).** No extension values changed. Probes inside nodes showed `"queried":null` and `"singular":false`.

`seoScore.badgeHtml` errors on every build, including `main` (`str_replace(): Argument #3 ($subject) must be of type array|string, int given`). `ContentNodeSeo.php:171` passes `rank_math_get_seo_score()`'s integer return to `str_replace()` on PHP 8. That is unrelated to this change.

## Results by query

Each build's responses were captured twice and matched. Each cell compares that build's response with `main`'s for the same query:

- **Same as `main`**: every value in the response is identical.
- **Extension output**: values from the extension's own fields that differ, as the value on `main` → the value on that build. Long values show the part around the first difference.
- **Probe**: test-only fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), selected at a point in the query, that report what WordPress treats as current there: `post` is the global `$post`, `queried` the main query's queried object, `product` WooCommerce's global `$product`, `authordata` the global `$authordata`, and `singular`, `tax`, `isProduct` and the like are conditional tags. They aren't extension output; they show what an extension reading the current object would see. Inside a node the right answer is that node. At the root, after the nodes, it's whatever was current before the query, which on these requests is nothing (`none`).
- Fixture names such as `C0`, `C1`, `U1` are the ones in [`rankmath.ids`](../rankmath.ids), shown with their IDs.

The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), and the queries in [`queries/rankmath/`](../../queries/rankmath).

| Query | What it checks | #4575 (`node-context`) | First version of #4575 (`original-pr`) |
| --- | --- | --- | --- |
| `rm-a-single-all-fields` | one post, every `seo` field including `jsonLd`, `fullHead`, `breadcrumbs` and `seoScore`. | Same as `main`. | Same as `main`. |
| `rm-b-list-three` | three posts in a list, `seo` title, canonical, breadcrumbs, `seoScore` and `jsonLd`. | Same as `main`. | Same as `main`. |
| `rm-c-nested-author-terms-parent` | a post's author `seo` and categories' `seo`, then a second `seo` alias after them, plus a child page's parent `seo`. | Same as `main`. | Same as `main`. |
| `rm-d1-jsonld-before-fullhead` | `jsonLd` selected before `fullHead` (checks for a duplicated JSON-LD block). | Same as `main`. | Same as `main`. |
| `rm-d2-fullhead-before-jsonld` | `fullHead` and `openGraph` before `jsonLd`. | Same as `main`. | Same as `main`. |
| `rm-d3-seo-before-after-model-fields` | the same post with `seo` before and after `title`/`content`/`uri`. | Same as `main`. | Same as `main`. |
| `rm-d4-list-jsonld-before-fullhead` | `jsonLd` before `fullHead` in a list of three posts. | Same as `main`. | Same as `main`. |
| `rm-e-root-after-node` | root `contentType.seo`, `category.seo` and `rankMathSettings` after a post's `seo`, plus `debugLateObject`. | **Extension output:** same as `main`<br>**Probe** `lateObj`: authordata U1 (31) → none, post P1 (529) → none, queried P1 (529) → none, singular true → false | **Extension output:** same as `main`<br>**Probe** `lateObj`: queried P1 (529) → none, singular true → false |
| `rm-f-sibling-after-seo-probe` | two posts with `debugNodeGlobals` around `seo` and `content`, plus `debugLateObject`. | **Extension output:** same as `main`<br>**Probe** `lateObj`: authordata 1 → none, post P2 (530) → none, queried P2 (530) → none, singular true → false<br>**Probe** `one.s0`: authordata 1 → U1 (31), post P2 (530) → P1 (529), queried P2 (530) → P1 (529)<br>**Probe** `two.s0`: authordata U1 (31) → 1, post P1 (529) → P2 (530), queried P1 (529) → P2 (530) | **Extension output:** same as `main`<br>**Probe** `lateObj`: queried P2 (530) → none, singular true → false<br>**Probe** `one.s0`: queried P2 (530) → none, singular true → false<br>**Probe** `one.s1`: queried P1 (529) → none, singular true → false<br>**Probe** `two.s0`: queried P1 (529) → none, singular true → false |
| `rm-g-term-seo-under-post-then-post-field` | term `seo` under a post's categories, then an in-node probe and the post's `content`. | Same as `main`. | **Extension output:** same as `main`<br>**Probe** `post.after`: queried P1 (529) → none, singular true → false |
