# Extension compatibility tests: node-scoped context

Test kit and results for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575), which fixes [#4572](https://github.com/wp-graphql/wp-graphql/issues/4572). This branch isn't meant to be merged. It holds the queries, setup scripts and responses we used to check the change against popular WPGraphQL extensions, so extension maintainers can see exactly what was tested for their plugin and run it themselves.

## The change, briefly

While a post, term or user resolves, WPGraphQL sets it up as the current object (the main query, the global `$post`, `$authordata`), so WordPress functions and filters that read "the current" object see it. Before #4575 it was set up before each of the node's own fields and never put back, so the last object resolved stayed current for the rest of the request.

With #4575, a node is the current object for every field selected under it, including fields other plugins register and nested values such as blocks, and the state from before is restored once resolution leaves it. A node is set up again if a field changes the global post, author or queried object, and connections run their deferred callbacks with their own node's state. See the [Node Context and Global State](https://github.com/wp-graphql/wp-graphql/blob/main/plugins/wp-graphql/docs/node-context.md) docs (on the PR branch until it merges).

## Builds compared

| Label | What it is |
| --- | --- |
| `main` | WPGraphQL before #4575: setup before each model field, never restored (#4572). |
| `original-pr` | The first version of #4575 (`71b90651a`): restored after each model field. |
| `node-context` | #4575 as it stands, the version under review. |

To test a build yourself, install WPGraphQL from that commit or the PR branch (the core plugin is `plugins/wp-graphql` in the repository).

## Results

85 queries in 8 groups, each run twice per build (both runs matched). Each extension's `results/<group>/SUMMARY.md` opens with its findings: what in the extension reads the current object (with file and line references), what was right and wrong on `main` and why, what's different with #4575, what the first version of #4575 got wrong, and whether the maintainer needs to do anything. Below that, a table lists every query, what it checks, and exactly which values differ from `main`.

[ACF](results/acf/SUMMARY.md) · [Content Blocks](results/content-blocks/SUMMARY.md) · [Faust](results/faust/SUMMARY.md) · [Yoast SEO](results/yoast/SUMMARY.md) · [Rank Math](results/rankmath/SUMMARY.md) · [WooCommerce](results/woo/SUMMARY.md) · [Polylang](results/polylang/SUMMARY.md) · [Headless Login](results/headless-login/SUMMARY.md)

| Extension | Version | Queries | `node-context` vs `main` |
| --- | --- | --- | --- |
| WPGraphQL for ACF (WYSIWYG fields) | 3.0.0, with ACF PRO 6.8.4 | 13 | 10 corrected values, 3 identical |
| Faust | 1.9.0 | 11 | 7 corrected values, 4 identical |
| WPGraphQL for Yoast SEO | 5.1.0, with Yoast SEO 28.7 | 21 | 1 corrected value, the rest identical apart from the probe |
| WPGraphQL Content Blocks | 4.8.9 | 11 | Extension output identical |
| WPGraphQL for Rank Math | 0.3.4, with Rank Math 1.0.280 | 10 | Extension output identical |
| WPGraphQL WooCommerce | 1.0.3, with WooCommerce 10.4.3 | 10 | Extension output identical |
| WPGraphQL Polylang | 0.7.1, with Polylang 3.9.1 | 6 | Extension output identical |
| WPGraphQL Headless Login | 0.4.4 | 3 | Extension output identical |

No regressions: every value that changed now comes from the node the field is selected under, where on `main` it came from a different node. Some queries also select test-only probe fields (from `probe/`) that report the current object at a point in the query; 12 queries changed only there. For comparison, `original-pr` changed extension values in 18 queries, including WPGraphQL for Yoast SEO losing schema output and the Blog breadcrumb, Faust's `templates` returning the front page's hierarchy for every node, and Content Blocks rendering another post's blocks in a list.

The corrected values, which no issue we could find had reported:

- Faust's `templates` was off by one in lists (each post got the previous post's templates).
- Galleries without explicit IDs in WPGraphQL for ACF WYSIWYG fields on options pages, terms and users showed images from whichever post resolved before them.
- A WPGraphQL for Yoast SEO taxonomy term's canonical URL read `/graphql/` when a media item resolved before it.

Not tested: WPGraphQL for Gravity Forms (paid base plugin), authenticated requests (previews, restricted fields), multisite, and other PHP and WordPress versions.

[IDEAS.md](IDEAS.md) has some ideas for how particular extensions could build on node context, including the WooCommerce `$product` global, which these queries can't show because WooGraphQL's own fields don't read it.

## Environment

wp-env site, WordPress 7.0, PHP 8.3, Twenty Twenty-Five, `/%postname%/` permalinks, guest POST requests, with ACF PRO 6.8.4, WPGraphQL for ACF 3.0.0, WPGraphQL Smart Cache 2.3.4 (object cache off) and WPGraphQL IDE 5.7.0 active. One group of extensions active at a time.

## Running it

You need a disposable WordPress site with WPGraphQL active, WP-CLI that can reach it, zsh, and Python 3. Every script talks to the site through WP-CLI, so set `WP` to however you run it, including arguments:

```sh
export WP="wp --path=/srv/test-site"           # or WP="npx wp-env run cli -- wp"
zsh bin/setup.sh woo                           # install the group's plugins, add the probe, create fixtures
zsh bin/run.sh woo main https://example.test/graphql         # with WPGraphQL from main active
zsh bin/run.sh woo node-context https://example.test/graphql # after switching WPGraphQL to the PR build
python3 tools/matrix.py diff --queries=queries/woo --out=out/woo --a=main --b=node-context
zsh bin/teardown.sh woo                        # delete the fixtures, restore options, deactivate plugins
```

Groups: `content` (WPGraphQL Content Blocks, Faust, and the WPGraphQL for ACF canaries, which use ACF or Secure Custom Fields), `yoast`, `rankmath`, `woo`, `polylang`, `headless-login`. `yoast` and `rankmath` can't be set up together.

- `bin/setup.sh` installs the group's plugins at the versions above (or activates them if they're already installed at those versions), installs the test-only probe mu-plugin, saves the options the fixtures change, and creates the fixtures. Fixture IDs go to `fixtures/<group>.ids`.
- `bin/run.sh` runs the group's queries and saves normalized responses under `out/`.
- `bin/teardown.sh` deletes only what setup created, found by its markers (titles starting `[ext-matrix]`, `extmx-` slugs and logins), never by ID. It deactivates the group's plugins (`--keep-plugins` leaves them active), restores the saved options and removes the probe. Content plugins create on their own, like WooCommerce's shop pages or Polylang's languages, is left in place and listed.

Fixture IDs are specific to each site, so compare your own runs across builds rather than against the saved responses directly. The IDs our runs used are in `results/<group>.ids`.

## What's here

```
bin/                setup.sh, run.sh and teardown.sh, plus their shared lib.sh
queries/<dir>/      one query per .graphql file (+ .vars.json); "#### next-operation" splits a batch request
fixtures/           the content each group's queries use
probe/              test-only mu-plugin: the probe fields, plus test types and ACF settings some queries use
results/<dir>/      responses per build (main/, original-pr/, node-context/) and SUMMARY.md
research/           source-reading notes written before any query ran (background, not results)
tools/              matrix2.py runs a query directory, matrix.py diffs two runs, normalize.py removes known noise
IDEAS.md            ideas for building on node context in existing extensions
```

## Notes

- **Normalized noise:** WooCommerce quantity input IDs, WooCommerce's shuffled "Related products" (replaced by the sorted list of product IDs) and Headless Login tokens. `normalize.py` is safe to run more than once.
- **The `debugGlobalsLate` probe** takes its snapshot inside a bare, unbound `Deferred` callback at the root of the query. That callback runs after the nodes' fields, so it still reports a leftover node on every build, including `node-context`. That's the case `graphql_bind_node_context()` exists for (see "Deferred resolvers" in the docs): a callback that needs a node's context should be bound in the resolver that creates it. The `debugLateObject` probe, which reads the state from a field resolved late instead, reports no current object on `node-context`.
- **The test site was shared across groups**, so content one plugin created on its own can show up in another group's results. For example, WooCommerce's placeholder image appears among the unattached media in some ACF gallery responses on `node-context`.
- **Rank Math `seoScore.badgeHtml`** returns an error on every build, including `main`: the resolver passes `rank_math_get_seo_score()`'s return value to `str_replace()`, which fails on PHP 8 when that value is an integer. It's unrelated to this change.
- **Content Blocks and query loops:** our early research predicted a Query Loop block could leave the global post on the loop's last post for later blocks. On WordPress 7.0 that doesn't happen on any build, because `WP_Block::render()` restores `$post` around each dynamic block.
- **zsh:** the scripts are zsh. One fixture bug we hit: in zsh, `"$P1:Alpha"` applies the `:A` path modifier to `$P1`, so the scripts use braces (`"${P1}:Alpha"`).
