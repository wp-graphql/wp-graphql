---
uri: "/docs/previews/"
title: "Previews"
---

WPGraphQL can return the unpublished, in-progress version of a post (a "preview") instead of the published version. This is what lets a headless front end render a preview of edits an author has made but not yet published.

There are two ways to request a preview:

- The **`X-GraphQL-Preview` request header** (recommended). Preview is a request-level concern, so it is expressed as request context rather than a field argument. A header works in every GraphQL IDE's headers panel.
- The **`asPreview` field argument** (deprecated). This still works for backwards compatibility, but new integrations should use the header.

## The `X-GraphQL-Preview` header

Send the preview context as an `X-GraphQL-Preview` request header. The value is an [RFC 8941 Structured Field](https://www.rfc-editor.org/rfc/rfc8941) dictionary: comma-separated `key=value` members with lowercase keys, integer values bare and string values double-quoted.

```http
X-GraphQL-Preview: database_id=123, featured_image_database_id=456, nonce="45d5b05f1b"
```

The fields mirror the query parameters WordPress core adds to a front-end preview URL (`preview_id`, `_thumbnail_id`, `preview_nonce`):

| Header field (`extensions` field)              | Maps to         | Purpose                                                                 |
| ---------------------------------------------- | --------------- | ----------------------------------------------------------------------- |
| `database_id` (`databaseId`)                   | `preview_id`    | The database ID of the published post being previewed.                  |
| `featured_image_database_id` (`featuredImageDatabaseId`) | `_thumbnail_id` | The previewed featured image. `0` means the featured image was removed. |
| `nonce` (`nonce`)                              | `preview_nonce` | Reserved for forward compatibility. Not currently verified.             |

The header value uses Structured Fields (the HTTP standard for structured header values), so its keys are lowercase `snake_case`; the JSON `extensions` fallback below uses the `camelCase` keys shown in parentheses. The header is included in `Access-Control-Allow-Headers`, so cross-origin clients (a headless app on a different domain) can send it.

When the request resolves the post identified by `database_id`, it **overlays the previewable fields** (for example `title`, `content`, `excerpt`, and the featured image) from **the current user's autosave**, while **preserving the node's published identity**. The `id` and `databaseId` stay the published post's, and any field that is not previewable still resolves from the published post. This mirrors how WordPress core previews a post: the URL is `?preview_id=43`, the post is still `postid-43`, but the content comes from the autosave.

The overlay source is the current user's autosave (the `{id}-autosave-v1` revision WordPress saves while editing), resolved with `wp_get_post_autosave()`, exactly as core's preview does. Autosaves are per user, so a request only ever previews the authenticated user's own in-progress edits, never another editor's. If the user has no autosave for the post (for example a draft saved directly), nothing is overlaid and the post's own values are returned.

Because identity is preserved, the overlay also works for a previewed post that appears **inside a connection** (for example previewing how your edits look in a list of posts), and the node keeps its real `databaseId` and cursor.

You do **not** need to pass `asPreview` as well.

### The `extensions.preview` fallback

The same object may instead be sent as a `preview` entry in the request `extensions`, alongside `query` and `variables`:

```jsonc
{
  "query": "query Post($id: ID!) { post(id: $id, idType: DATABASE_ID) { title content } }",
  "variables": { "id": 123 },
  "extensions": {
    "preview": { "databaseId": 123, "featuredImageDatabaseId": 456, "nonce": "45d5b05f1b" }
  }
}
```

This is useful when you want the preview context to travel inside the operation body rather than the transport (for example to keep it with a logged or replayed operation). The `X-GraphQL-Preview` header takes precedence when both are present. You can also send both for resilience against an intermediary that drops one of them.

### Which fields are previewable

Previewing is **opt-in per field**. A field overlays from the revision only when its registration declares it previewable, so identity and structural fields (`id`, `databaseId`, `slug`, `uri`, `status`, `parent`, and so on) always resolve from the published post. Core marks `title`, `content`, and `excerpt` previewable, and resolves the featured image from the request's `featuredImageDatabaseId`.

Plugins can opt their own fields into preview resolution via field config:

```php
// Resolve this field's normal resolver against the revision when previewing.
register_graphql_field( 'Post', 'myDraftField', [
    'type'          => 'String',
    'isPreviewable' => true,
] );

// Or supply a request-derived previewed value (e.g. from the preview envelope).
register_graphql_field( 'Post', 'myComputedField', [
    'type'           => 'String',
    'previewResolve' => static function ( $source, $args, $context, $info, $preview ) {
        // $preview is the normalized `preview` envelope for the request.
        return '...';
    },
] );
```

A field with neither option resolves from the published post, so forgetting to opt in is safe (the value is current, never broken).

`previewResolve` runs only inside an authorized preview (the request is authenticated and the viewer can edit the post being previewed). It receives the raw `preview` envelope, including client-supplied values, so if your callback exposes anything sensitive beyond what an editor of that post may already see, apply your own checks.

### Previewing post meta

Meta keys that WordPress revisions, those registered with `revisions_enabled` (or added via the `wp_post_revision_meta_keys` filter, such as core's `footnotes`), resolve from the revision's own value in a preview, mirroring core. Other meta keys continue to resolve from the published post. The `graphql_resolve_revision_meta_from_parent` filter overrides this per key in either direction: return `false` to resolve a key from the revision, or `true` to force a revisioned key back to the published post.

> **Requires WordPress 6.4+.** Revisioned post meta is built on the meta revisions framework added in WordPress 6.4 (`revisions_enabled` and `wp_post_revision_meta_keys()`). On earlier versions these keys resolve from the published post instead. The rest of the preview overlay (`title`, `content`, `excerpt`, the featured image, and identity preservation) works on all supported WordPress versions.

### Authentication and authorization

A preview is only resolved when **all** of the following are true:

- The request is authenticated.
- The authenticated user can edit the post (`current_user_can( 'edit_post', id )`).
- The `databaseId` in the envelope matches the post being resolved.

If any of these is not met, the request is resolved exactly as if no `preview` envelope had been provided: the published node (or `null`, per the usual access rules) is returned, and **no error is thrown**. This is intentional: an invalid or unauthorized envelope produces a response identical to a request without one, so it cannot be used to probe for posts a user cannot access.

When `GRAPHQL_DEBUG` is enabled, a debug notice is added to the response `extensions` when a `preview` envelope was provided for a post the current user is not allowed to preview.

Because previews require an authenticated, edit-capable user, preview responses are not cached.

### Previewing the featured image

WordPress core never stores the previewed featured image on the revision; it passes it as a request parameter on the preview URL. A headless client should forward that value as `featuredImageDatabaseId` in the envelope. When previewing, WPGraphQL then resolves `featuredImage`, `featuredImageId`, and `featuredImageDatabaseId` from `featuredImageDatabaseId` instead of the published featured image.

```graphql
query Preview($id: ID!) {
  post(id: $id, idType: DATABASE_ID) {
    title
    featuredImageDatabaseId
    featuredImage {
      node {
        sourceUrl
      }
    }
  }
}
```

With the preview context set to `{ "databaseId": 123, "featuredImageDatabaseId": 456 }` (via the header or `extensions.preview`), the query above returns attachment `456` as the featured image.

### The preview flow, end to end

The goal is to reproduce, in a headless app, what happens when an author clicks **Preview** in wp-admin. Here is how the pieces line up.

What WordPress does natively when you click **Preview**:

1. The editor saves an **autosave** for the current user (the `{id}-autosave-v1` revision) holding the in-progress, unsaved edits.
2. It opens a preview URL built from the post's permalink: `…/?preview=true&preview_id=43&preview_nonce=<nonce>`. Note `preview_id` is the **published post id**, not a revision id.
3. On the front end, WordPress verifies the nonce, then overlays the current user's autosave onto the published post and renders it.

The headless equivalent:

1. The author edits and clicks **Preview** in wp-admin. The autosave is saved exactly as above.
2. WordPress generates the preview link. A headless framework (such as Faust) overrides the `preview_post_link` filter to point that link at the headless app, carrying the `preview_id` (and `preview_nonce`) query parameters.
3. The headless app reads those parameters and runs its normal page query, adding the preview context as an `X-GraphQL-Preview` header:

   ```http
   X-GraphQL-Preview: database_id=43, featured_image_database_id=47
   ```

   (`database_id` comes from `preview_id`; `featured_image_database_id` is optional, see below. The same context may instead go in `extensions.preview` as a JSON object.)

4. WPGraphQL resolves the authenticated user's autosave for post `43` and overlays the previewable fields. The page renders in a preview state, with the post's identity (`databaseId`, `uri`, and so on) preserved.

The request must be authenticated as the same user who made the edits (via cookie or a token), because the autosave is per user and the capability check requires an editor of the post.

The featured image is a special case. WordPress does not store the previewed featured image on the autosave (the block editor sends it as `featured_media`, not as revisioned meta), and it is not included in the preview URL for the block editor. If you want the previewed featured image to appear, the headless framework can read it from the editor state and pass it as `featuredImageDatabaseId` in the preview context.

## The `asPreview` argument (deprecated)

Before the preview context (header / `extensions.preview`), previews were requested with an `asPreview` field argument:

```graphql
query Post($id: ID!) {
  post(id: $id, idType: DATABASE_ID, asPreview: true) {
    title
    content
  }
}
```

This continues to work for requests that do **not** carry a preview context, and is now marked deprecated in the schema. Unlike the preview context, `asPreview: true` swaps the whole node to the revision, so `databaseId` becomes the *revision's* id rather than the published post's.

The argument and the preview context are separate mechanisms and should not be combined. When a request provides **both** a preview context and `asPreview: true`, the preview context wins (the identity-preserving overlay is applied), the `asPreview` argument is ignored, and a debug notice is added under `GRAPHQL_DEBUG`.

The argument is planned for removal in a future major version. To migrate, drop `asPreview: true` from your queries and send the preview context (the `X-GraphQL-Preview` header) on the request instead. As a bonus, the preview context preserves the node's published `databaseId`, so toolbars and editors no longer need to map the revision id back to the published post.
