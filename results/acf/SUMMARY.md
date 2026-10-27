# WPGraphQL for ACF (WYSIWYG fields): node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

13 queries. Against `main`: **node-context** (#4575 as it stands) has 3 identical, 0 changed only in test probe fields, 10 with changed extension output; **original-pr** (the first version of #4575) has 12 identical, 1 probe only, 0 with changed extension output.

## Anything to do?

**No changes needed.** WYSIWYG fields now render `[gallery]` with no `ids` against the node the field belongs to, with no change to WPGraphQL for ACF.

One thing to tell site owners: options page, term and user fields used to pick up whichever post resolved before them. They now get WordPress's no-current-post result, unattached media. A site that relied on that, knowingly or not, should pass explicit `ids` to the shortcode.

## Findings

**What in the extension reads the current object.** Each ACF field resolves in `FieldConfig::resolve_field()` (`src/FieldConfig.php:449`). It gets the node's ID from `Utils::get_node_acf_id()` (`src/Utils.php:24`, which reads `databaseId` for posts at `:44`) and reads the value with `get_field()` and an explicit ID (`:589`). WYSIWYG fields are fetched formatted (`should_format_field_value()`, `:323`), which runs shortcodes. `prepare_acf_field_value()` then applies `the_content` to them (`:673-675`). WordPress's `[gallery]` with no `ids` lists the attachments of `get_post()`, the global post (`gallery_shortcode()`, `wp-includes/media.php`). The field group field returns `['node' => $node, ...]` to its fields (`src/Registry.php:839-846`). Options pages are root fields resolved through a deferred loader (`src/Registry.php:425-433`). Their `AcfOptionsPage` model (`src/Model/AcfOptionsPage.php:22`) has no `setup()`, so it isn't a node.

**What was tested.** The fixture puts `[gallery]` (no ids) in WYSIWYG fields on posts, a repeater row, a category, a user and an options page. Shapes: a single post, a list, nested term > posts, user under a post, field order, and options, term and user fields at the root after other nodes.

**Before (`main`).** Single posts were right. In lists, every post got the gallery of the last post in the list. In `acf-b` the last post has no images, so all three bodies came out empty (`"<p>Post 490</p>\n"`, `rowBody` `""`). In `acf-c1` both nested posts showed Post Two's (`galleryid-491`). Root fields showed whichever post resolved before them:
- options after a post: `galleryid-490`;
- options after a list: `galleryid-491`;
- term or user after Post Two: `galleryid-491`.

Moving the options page first (`acf-e2rev`) didn't help, because its deferred loader resolves after the post's `title`. After a media item (`acf-e1`) the options gallery was empty. The research predicted attachment markup would be prepended there, but that didn't happen on any build: core's Post model removes `prepend_attachment` when it models a media item.

**After (`node-context`).** Each post's fields see that post: `galleryid-490` for Post One, `galleryid-491` for Post Two, empty for Post Three. Options, term and user fields at the root no longer see an earlier post. They get WordPress's no-current-post result (`galleryid-0`, unattached media, including a `woocommerce-placeholder` image left on the shared test site). That result is the same whatever ran first. A user nested under a post still sees that post (`acf-c2`, identical on all builds).

**First version of #4575 (`original-pr`).** Extension values were identical to `main` in all 13 queries, so it fixed none of these.

## Results by query

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/acf/`](../../queries/acf), and the fixture IDs they used in [`content.ids`](../content.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `acf-a-single-post-gallery` | one post, WYSIWYG `body` and a repeater row's `rowBody`, each with `[gallery]`. | identical | identical |
| `acf-b-list-three-posts` | three posts in a list (two with images, one without), `databaseId` then the field group. | `data.posts.nodes[0].extMatrixStory.body`: "<p>Post 490</p>\n" → "<p>Post 490</p>\n<div id='gallery-1' class='gallery gall...<br>`data.posts.nodes[0].extMatrixStory.rows[0].rowBody`: "" → "<div id='gallery-2' class='gallery galleryid-490 gallery...<br>`data.posts.nodes[1].extMatrixStory.body`: "<p>Post 491</p>\n" → "<p>Post 491</p>\n<div id='gallery-3' class='gallery gall...<br>…and 1 more | identical |
| `acf-c1-term-then-nested-posts` | a category's own WYSIWYG field, then its posts' fields. | `data.category.posts.nodes[0].extMatrixStory.body`: "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... → "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... | identical |
| `acf-c1b-nested-posts-then-term-field` | the same with the category's field after the nested posts. | `data.category.posts.nodes[0].extMatrixStory.body`: "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... → "<p>Post 490</p>\n<div id='gallery-2' class='gallery gall... | identical |
| `acf-c2-user-under-post` | the author's WYSIWYG field under a post. | identical | identical |
| `acf-d-field-before-after-model-fields` | the same post with the ACF field before and after `title`/`databaseId`. | identical | identical |
| `acf-e1-options-after-media` | an options page field at the root after a media item. | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n" → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e2-options-after-post` | an options page field at the root after a post. | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n<div id='gallery-1' class='gallery galle... → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e2rev-options-before-post` | the options page selected before the post. | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n<div id='gallery-1' class='gallery galle... → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e3-term-root-after-post` | a category's WYSIWYG field at the root after Post Two. | `data.category.extMatrixTerm.body`: "<div id='gallery-1' class='gallery galleryid-491 gallery... → "<div id='gallery-1' class='gallery galleryid-0 gallery-c... | identical |
| `acf-e4-user-root-after-post` | a user's WYSIWYG field at the root after Post Two. | `data.user.extMatrixUser.body`: "<div id='gallery-1' class='gallery galleryid-491 gallery... → "<div id='gallery-1' class='gallery galleryid-0 gallery-c... | identical |
| `acf-e5-options-after-post-list` | an options page after a list of two posts. | `data.extMatrixSiteSettings.extMatrixSiteFields.body`: "<p>Options</p>\n<div id='gallery-1' class='gallery galle... → "<p>Options</p>\n<div id='gallery-1' class='gallery galle... | identical |
| `acf-e6-term-root-after-post-p1` | a category field after Post One, plus `debugGlobalsLate` and `debugLateObject` probes. | `data.category.extMatrixTerm.body`: "<div id='gallery-1' class='gallery galleryid-490 gallery... → "<div id='gallery-1' class='gallery galleryid-0 gallery-c... | probe only |
