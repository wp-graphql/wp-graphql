# WPGraphQL Polylang: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

6 queries. Against `main`: **node-context** (#4575 as it stands) has 5 identical, 1 changed only in test probe fields, 0 with changed extension output; **original-pr** (the first version of #4575) has 5 identical, 1 probe only, 0 with changed extension output.

## Findings

**What in the extension reads the current object.** Nothing. The fields use explicit IDs: `language` calls `pll_get_post_language( $post_id )` (`src/PostObject.php:143-151`), and `translation` calls `pll_get_post_translations( $post->ID )` (`:200`), returning a `Model\Post` built directly (`:207-209`). For GraphQL requests the extension puts Polylang in admin context (`src/Loader.php:86-95`), so the current language never comes from the queried object. (Branch snapshot, header 0.7.1, with Polylang 3.9.1.)

**What was tested.** Single, a list of nine posts across three languages, language filters, a translation nested under its post, field order, and root `defaultLanguage`, `languages` and `translateString` after a post.

**Results.** Extension output is identical on all three builds in all 6 queries. For example, `defaultLanguage.homeUrl` is `"http://localhost:8888/"` everywhere, and nested translations keep their own categories. The only change is the `debugLateObject` probe in `pll-e`: on `main` it reported the post (`"post":523`), and on `node-context` there is no current post. That's expected, since nothing here depends on the current object.

**Anything to do.** Nothing.

## Results by query

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/polylang/`](../../queries/polylang), and the fixture IDs they used in [`polylang.ids`](../polylang.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `pll-a-single-language-translations` | one post's `language`, `translations` and `translation(language: FI)`. | identical | identical |
| `pll-b-list-nine` | nine posts across three languages in a list, each with `language` and `translations`. | identical | identical |
| `pll-b2-language-filters` | `language`/`languages` where-args on posts, content nodes and categories. | identical | identical |
| `pll-c-nested-translation-then-parent` | a translation nested under its post (with its categories and their translations), then the parent's own fields. | identical | identical |
| `pll-d-before-after-model-fields` | the same post with language fields before and after `title`, after another post resolved. | identical | identical |
| `pll-e-root-after-node` | root `defaultLanguage`, `languages` and `translateString` after a post, plus `debugLateObject`. | probe only | probe only |
