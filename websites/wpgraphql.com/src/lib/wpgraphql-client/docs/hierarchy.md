# Hierarchy

Given a normalized seed object, `resolveTemplateName(seed, registry)` walks a candidate list (most specific first) and returns the first key that exists in the registry.

## Candidate list

| candidate | when |
|---|---|
| `front-page` | `seed.isFrontPage` |
| `home` | `seed.isPostsPage` |
| `single-{postType}-{slug}` → `single-{postType}` → `single` → `singular` | content node singletons |
| `page-{slug}` → `page` → `singular` | `__typename === "Page"` |
| `category-{slug}` → `category` → `archive` | `__typename === "Category"` |
| `tag-{slug}` → `tag` → `archive` | `__typename === "Tag"` |
| `taxonomy-{tax}-{slug}` → `taxonomy-{tax}` → `taxonomy` → `archive` | other terms |
| `author-{slug}` → `author` → `archive` | `__typename === "User"` |
| `archive-{postType}` → `archive` | `__typename === "ContentType"` |
| `404` | no node and not the front page |
| `index` | final fallback |

This mirrors WordPress's [template hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/).

## Algorithm

1. Build the candidate list from the seed.
2. Normalize slugs, post type names, and taxonomy names to lowercase.
3. Deduplicate the list (some seeds map to the same candidate twice — e.g. a Page named "page").
4. Walk in order; return the first key present in the registry.
5. If no key matches, throw an error listing the candidates that were tried.

The candidate-building logic is exposed separately as `buildCandidateNames(seed)` so it can be tested without a registry.

## Adding a new specificity slot

Hierarchy slots are just keys in the registry. To add a custom template for a single post type and slug, just add the corresponding key:

```js
const templates = {
  "single-post-hello-world": HelloWorldOverride,
  "single-post": SinglePost,
  singular: Singular,
  index: Index,
  // …
}
```

No changes to the resolver — it already builds these candidates.

## Custom-typename handling

For typenames not covered by the explicit cases (i.e. not `Page` / `Category` / `Tag` / `User` / `ContentType` / a TermNode subtype), the resolver falls back to:

- `singular` (if `seed.typename` is set)
- `index` (the final fallback)

So a custom WPGraphQL type without a corresponding template slot will still render through `singular` or `index`.

## See also

- [Templates](./templates.md) — registering templates and writing queries
- [Transport](./transport.md#seed-query) — what the seed query fetches
