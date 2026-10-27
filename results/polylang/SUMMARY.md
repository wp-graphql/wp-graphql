# WPGraphQL Polylang: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

6 queries. Compared with `main`, **#4575** (`node-context`) changed extension output in 0, changed only the probe in 1, and left 5 the same. **The first version of #4575** (`original-pr`) changed extension output in 0, only the probe in 1, and left 5 the same.

## Anything to do?

**No changes needed.** Extension output is identical on `main` and with #4575.

## Findings

**What in the extension reads the current object.** Nothing. The fields use explicit IDs: `language` calls `pll_get_post_language( $post_id )` (`src/PostObject.php:143-151`), and `translation` calls `pll_get_post_translations( $post->ID )` (`:200`), returning a `Model\Post` built directly (`:207-209`). For GraphQL requests the extension puts Polylang in admin context (`src/Loader.php:86-95`), so the current language never comes from the queried object. (Branch snapshot, header 0.7.1, with Polylang 3.9.1.)

**What was tested.** Single, a list of nine posts across three languages, language filters, a translation nested under its post, field order, and root `defaultLanguage`, `languages` and `translateString` after a post.

**Results.** Extension output is identical on all three builds in all 6 queries. For example, `defaultLanguage.homeUrl` is `"http://localhost:8888/"` everywhere, and nested translations keep their own categories. The only change is the `debugLateObject` probe in `pll-e`: on `main` it reported the post (`"post":523`), and on `node-context` there is no current post. That's expected, since nothing here depends on the current object.

## Results by query

Each build's responses were captured twice and matched. Each cell compares that build's response with `main`'s for the same query:

- **Same as `main`**: every value in the response is identical.
- **Extension output**: values from the extension's own fields that differ, as the value on `main` → the value on that build. Long values show the part around the first difference.
- **Probe**: test-only fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), selected at a point in the query, that report what WordPress treats as current there: `post` is the global `$post`, `queried` the main query's queried object, `product` WooCommerce's global `$product`, `authordata` the global `$authordata`, and `singular`, `tax`, `isProduct` and the like are conditional tags. They aren't extension output; they show what an extension reading the current object would see. Inside a node the right answer is that node. At the root, after the nodes, it's whatever was current before the query, which on these requests is nothing (`none`).
- Fixture names such as `EN1`, `FI1`, `DE1` are the ones in [`polylang.ids`](../polylang.ids), shown with their IDs.

The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), and the queries in [`queries/polylang/`](../../queries/polylang).

| Query | What it checks | #4575 (`node-context`) | First version of #4575 (`original-pr`) |
| --- | --- | --- | --- |
| `pll-a-single-language-translations` | one post's `language`, `translations` and `translation(language: FI)`. | Same as `main`. | Same as `main`. |
| `pll-b-list-nine` | nine posts across three languages in a list, each with `language` and `translations`. | Same as `main`. | Same as `main`. |
| `pll-b2-language-filters` | `language`/`languages` where-args on posts, content nodes and categories. | Same as `main`. | Same as `main`. |
| `pll-c-nested-translation-then-parent` | a translation nested under its post (with its categories and their translations), then the parent's own fields. | Same as `main`. | Same as `main`. |
| `pll-d-before-after-model-fields` | the same post with language fields before and after `title`, after another post resolved. | Same as `main`. | Same as `main`. |
| `pll-e-root-after-node` | root `defaultLanguage`, `languages` and `translateString` after a post, plus `debugLateObject`. | **Extension output:** same as `main`<br>**Probe** `lateObj`: post FI2 (523) → none, queried FI2 (523) → none, singular true → false | **Extension output:** same as `main`<br>**Probe** `lateObj`: queried FI2 (523) → none, singular true → false |
