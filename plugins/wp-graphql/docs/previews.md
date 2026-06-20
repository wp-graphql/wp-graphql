---
uri: "/docs/previews/"
title: "Previews"
---

WPGraphQL can return the unpublished, in-progress version of a post (a "preview") instead of the published version. This is what lets a headless front end render a preview of edits an author has made but not yet published.

There are two ways to request a preview:

- The **`preview` request extension** (recommended). Preview is a request-level concern, so it is expressed in the request `extensions` rather than as a field argument.
- The **`asPreview` field argument** (deprecated). This still works for backwards compatibility, but new integrations should use the extension.

## The `preview` request extension

GraphQL requests may carry an `extensions` object alongside `query` and `variables`. WPGraphQL reads a `preview` envelope from it:

```jsonc
{
  "query": "query Post($id: ID!) { post(id: $id, idType: DATABASE_ID) { title content featuredImage { node { sourceUrl } } } }",
  "variables": { "id": 123 },
  "extensions": {
    "preview": {
      "databaseId": 123,
      "featuredImageDatabaseId": 456,
      "nonce": "45d5b05f1b"
    }
  }
}
```

The envelope mirrors the query parameters WordPress core adds to a front-end preview URL (`preview_id`, `_thumbnail_id`, `preview_nonce`):

| Field                     | Maps to         | Purpose                                                                 |
| ------------------------- | --------------- | ----------------------------------------------------------------------- |
| `databaseId`              | `preview_id`    | The database ID of the published post being previewed.                  |
| `featuredImageDatabaseId` | `_thumbnail_id` | The previewed featured image. `0` means the featured image was removed. |
| `nonce`                   | `preview_nonce` | Reserved for forward compatibility. Not currently verified.             |

When the request resolves the post identified by `databaseId`, it **overlays the previewable fields** (for example `title`, `content`, `excerpt`, and the featured image) from **the current user's autosave**, while **preserving the node's published identity**. The `id` and `databaseId` stay the published post's, and any field that is not previewable still resolves from the published post. This mirrors how WordPress core previews a post: the URL is `?preview_id=43`, the post is still `postid-43`, but the content comes from the autosave.

The overlay source is the current user's autosave (the `{id}-autosave-v1` revision WordPress saves while editing), resolved with `wp_get_post_autosave()`, exactly as core's preview does. Autosaves are per user, so a request only ever previews the authenticated user's own in-progress edits, never another editor's. If the user has no autosave for the post (for example a draft saved directly), nothing is overlaid and the post's own values are returned.

Because identity is preserved, the overlay also works for a previewed post that appears **inside a connection** (for example previewing how your edits look in a list of posts), and the node keeps its real `databaseId` and cursor.

You do **not** need to pass `asPreview` as well.

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

Meta keys that WordPress revisions, those registered with `revisions_enabled` (or added via the `wp_post_revision_meta_keys` filter, such as core's `footnotes`), resolve from the revision's own value in a preview, mirroring core. Other meta keys continue to resolve from the published post, and the `graphql_resolve_revision_meta_from_parent` filter can still be used to opt a specific key into resolving from the revision.

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

With `extensions.preview` set to `{ "databaseId": 123, "featuredImageDatabaseId": 456 }`, the query above returns attachment `456` as the featured image.

### The preview flow, end to end

The goal is to reproduce, in a headless app, what happens when an author clicks **Preview** in wp-admin. Here is how the pieces line up.

What WordPress does natively when you click **Preview**:

1. The editor saves an **autosave** for the current user (the `{id}-autosave-v1` revision) holding the in-progress, unsaved edits.
2. It opens a preview URL built from the post's permalink: `…/?preview=true&preview_id=43&preview_nonce=<nonce>`. Note `preview_id` is the **published post id**, not a revision id.
3. On the front end, WordPress verifies the nonce, then overlays the current user's autosave onto the published post and renders it.

The headless equivalent:

1. The author edits and clicks **Preview** in wp-admin. The autosave is saved exactly as above.
2. WordPress generates the preview link. A headless framework (such as Faust) overrides the `preview_post_link` filter to point that link at the headless app, carrying the `preview_id` (and `preview_nonce`) query parameters.
3. The headless app reads those parameters and runs its normal page query, adding the `preview` extension to the request:

   ```jsonc
   "extensions": {
     "preview": {
       "databaseId": 43,                // from preview_id
       "featuredImageDatabaseId": 47    // optional, see below
     }
   }
   ```

4. WPGraphQL resolves the authenticated user's autosave for post `43` and overlays the previewable fields. The page renders in a preview state, with the post's identity (`databaseId`, `uri`, and so on) preserved.

The request must be authenticated as the same user who made the edits (via cookie or a token), because the autosave is per user and the capability check requires an editor of the post.

The featured image is a special case. WordPress does not store the previewed featured image on the autosave (the block editor sends it as `featured_media`, not as revisioned meta), and it is not included in the preview URL for the block editor. If you want the previewed featured image to appear, the headless framework can read it from the editor state and pass it as `featuredImageDatabaseId` in the envelope.

## The `asPreview` argument (deprecated)

Before the `preview` extension, previews were requested with an `asPreview` field argument:

```graphql
query Post($id: ID!) {
  post(id: $id, idType: DATABASE_ID, asPreview: true) {
    title
    content
  }
}
```

This continues to work for requests that do **not** carry a `preview` extension, and is now marked deprecated in the schema. Unlike the extension, `asPreview: true` swaps the whole node to the revision, so `databaseId` becomes the *revision's* id rather than the published post's.

The argument and the extension are separate mechanisms and should not be combined. When a request provides **both** a `preview` extension and `asPreview: true`, the extension wins (the identity-preserving overlay is applied), the `asPreview` argument is ignored, and a debug notice is added under `GRAPHQL_DEBUG`.

The argument is planned for removal in a future major version. To migrate, drop `asPreview: true` from your queries and send the `preview` extension on the request instead. As a bonus, the extension preserves the node's published `databaseId`, so toolbars and editors no longer need to map the revision id back to the published post.
