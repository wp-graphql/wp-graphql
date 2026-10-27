# WPGraphQL for Rank Math: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

10 queries. Against `main`: **node-context** (#4575 as it stands) has 8 identical, 2 changed only in test probe fields, 0 with changed extension output; **original-pr** (the first version of #4575) has 7 identical, 3 probe only, 0 with changed extension output.

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

**Anything to do.** Nothing required. [IDEAS.md](../../IDEAS.md), "WPGraphQL for Rank Math: less duplicated setup", sketches how the SEO models could skip repeating core's setup on WPGraphQL versions with node context.

`seoScore.badgeHtml` errors on every build, including `main` (`str_replace(): Argument #3 ($subject) must be of type array|string, int given`). `ContentNodeSeo.php:171` passes `rank_math_get_seo_score()`'s integer return to `str_replace()` on PHP 8. That is unrelated to this change.

## Results by query

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/rankmath/`](../../queries/rankmath), and the fixture IDs they used in [`rankmath.ids`](../rankmath.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `rm-a-single-all-fields` | one post, every `seo` field including `jsonLd`, `fullHead`, `breadcrumbs` and `seoScore`. | identical | identical |
| `rm-b-list-three` | three posts in a list, `seo` title, canonical, breadcrumbs, `seoScore` and `jsonLd`. | identical | identical |
| `rm-c-nested-author-terms-parent` | a post's author `seo` and categories' `seo`, then a second `seo` alias after them, plus a child page's parent `seo`. | identical | identical |
| `rm-d1-jsonld-before-fullhead` | `jsonLd` selected before `fullHead` (checks for a duplicated JSON-LD block). | identical | identical |
| `rm-d2-fullhead-before-jsonld` | `fullHead` and `openGraph` before `jsonLd`. | identical | identical |
| `rm-d3-seo-before-after-model-fields` | the same post with `seo` before and after `title`/`content`/`uri`. | identical | identical |
| `rm-d4-list-jsonld-before-fullhead` | `jsonLd` before `fullHead` in a list of three posts. | identical | identical |
| `rm-e-root-after-node` | root `contentType.seo`, `category.seo` and `rankMathSettings` after a post's `seo`, plus `debugLateObject`. | probe only | probe only |
| `rm-f-sibling-after-seo-probe` | two posts with `debugNodeGlobals` around `seo` and `content`, plus `debugLateObject`. | probe only | probe only |
| `rm-g-term-seo-under-post-then-post-field` | term `seo` under a post's categories, then an in-node probe and the post's `content`. | identical | probe only |
