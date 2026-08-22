---
uri: "/docs/previews/"
title: "Previews"
---

WPGraphQL can return the unpublished, in-progress version of a post (a "preview") instead of the published version. This is what lets a headless front end render a preview of edits an author has made but not yet published.

There are two ways to request a preview:

- The **`X-GraphQL-Preview` request header** (recommended). Preview is a request-level concern, so it is expressed as request context rather than a field argument. A header works in every GraphQL IDE's headers panel.
- The **`asPreview` field argument** (deprecated). This still works for backwards compatibility, but new integrations should use the header.

## The `X-GraphQL-Preview` header

Send the preview context as an `X-GraphQL-Preview` request header. The value is an [RFC 8941 Structured Field](https://www.rfc-editor.org/rfc/rfc8941) dictionary: comma-separated `key=value` members with lowercase keys, integer values bare and string values double-quoted. The full dictionary syntax is parsed (any compliant serializer's output works); the keys defined below take Integer or String values, and members of any other type, like unknown keys, are ignored. As the RFC requires, a value that fails to parse is discarded in its entirety, in which case the `extensions.preview` fallback (if present) applies.

```http
X-GraphQL-Preview: database_id=123, featured_image_database_id=456, nonce="45d5b05f1b"
```

The fields mirror the query parameters WordPress core adds to a front-end preview URL (`preview_id`, `_thumbnail_id`, `preview_nonce`):

| Header field (`extensions` field)              | Maps to         | Purpose                                                                 |
| ---------------------------------------------- | --------------- | ----------------------------------------------------------------------- |
| `database_id` (`databaseId`)                   | `preview_id`    | The database ID of the published post being previewed.                  |
| `featured_image_database_id` (`featuredImageDatabaseId`) | `_thumbnail_id` | The previewed featured image. `0` means the featured image was removed. |
| `nonce` (`nonce`)                              | `preview_nonce` | Accepted but not verified today; see [The `nonce` field](#the-nonce-field). |

The header value uses Structured Fields (the HTTP standard for structured header values), so its keys are lowercase `snake_case`; the JSON `extensions` fallback below uses the `camelCase` keys shown in parentheses. The header is included in `Access-Control-Allow-Headers`, so cross-origin clients (a headless app on a different domain) can send it.

When the request resolves the post identified by `database_id`, it **overlays the previewable fields** (for example `title`, `content`, `excerpt`, and the featured image) from **the post's autosave**, while **preserving the node's published identity**. The `id` and `databaseId` stay the published post's, and any field that is not previewable still resolves from the published post. This mirrors how WordPress core previews a post: the URL is `?preview_id=43`, the post is still `postid-43`, but the content comes from the autosave.

The overlay source is the post's newest autosave (the `{id}-autosave-v1` revision WordPress saves while editing), resolved with `wp_get_post_autosave()` exactly as core's preview (`_set_preview()`) does, regardless of who authored it. If the post has no autosave (for example a draft saved directly), nothing is overlaid and the post's own values are returned (see [Detecting the preview state](#detecting-the-preview-state)).

The one deliberate divergence from core's flow is authorization. Core authorizes a front-end preview with the `preview_nonce` URL parameter; WPGraphQL authorizes with a capability check instead (the request must be authenticated as a user who can edit the post, see below). This is what makes shared preview links work: a WordPress nonce is bound to the session of the user who created it, so core's own preview links fail for a colleague who opens them, while the capability check admits exactly the audience core intends (any user who can edit the post) no matter who opens the link.

The capability model has two limits worth knowing:

- **No account-less previews.** A stakeholder without a WordPress account cannot be granted a preview by link (core cannot do this either). The headless pattern for that is Draft-Mode-style: the front end's server authenticates to WordPress with its own credentials and gates viewer access itself (a shared secret, its own session, and so on).
- **The capability is per post.** A Contributor can preview their own drafts but not another author's post, the same audience wp-admin allows. And the request must be authenticated with a method WordPress recognizes (cookie, application password, a token plugin) for previews to work at all.

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

### Batch and GET requests

- **Batch requests** (an array of operations in one HTTP request): `extensions.preview` is per-operation, while the preview overlay is request-level, so it is **not supported in a batch**. Send the `X-GraphQL-Preview` header instead; it applies to **every** operation in the batch. A per-operation `extensions.preview` inside a batch is ignored, with a debug notice under `GRAPHQL_DEBUG`.
- **GET requests**: both transports work. With the query-string form of `extensions`, the preview parameters become part of the URL (and any access logs); the header keeps them out of the URL.

### Sending the preview context from clients and IDEs

- **WPGraphQL IDE** (the standalone plugin): add the header in the Headers panel:

  ```json
  { "X-GraphQL-Preview": "database_id=123" }
  ```

  The legacy GraphiQL IDE bundled with WPGraphQL core does not support request headers and is deprecated in favor of the standalone WPGraphQL IDE plugin. If you are still on the bundled IDE, use the `extensions.preview` form, or switch to the standalone IDE.

- **Altair**: use the headers pane, or Altair's built-in Request Extensions editor for the `extensions.preview` form.

- **Postman / Insomnia / Apollo Sandbox**: add `X-GraphQL-Preview` in the request's headers. Pre-request scripts (Postman) and preflight scripts (Apollo Sandbox) can build the value from the preview URL's query parameters.

- **JavaScript clients** (Apollo, urql, plain `fetch`): set the header on the authenticated request. GraphQL clients do not emit RFC 8941 themselves, so serialize the value directly:

  ```js
  const previewHeader = ({ databaseId, featuredImageDatabaseId, nonce }) =>
    [
      `database_id=${databaseId}`,
      featuredImageDatabaseId != null &&
        `featured_image_database_id=${featuredImageDatabaseId}`,
      nonce && `nonce=${JSON.stringify(nonce)}`,
    ]
      .filter(Boolean)
      .join(', ');
  ```

  `JSON.stringify` double-quotes the nonce and escapes `"` and `\`, which matches the RFC 8941 string syntax for these values.

### Which fields are previewable

Previewing is **opt-in per field**. A field overlays from the revision only when its registration declares it previewable, so identity and structural fields (`id`, `databaseId`, `slug`, `uri`, `status`, `parent`, and so on) always resolve from the published post. Core marks `title`, `content`, and `excerpt` previewable, and resolves the featured image from the request's `featuredImageDatabaseId`.

Plugins can opt their own fields into preview resolution via field config:

```php
// Resolve this field's normal resolver against the revision when previewing.
register_graphql_field( 'Post', 'myDraftField', [
    'type'          => 'String',
    'isPreviewable' => true,
] );

// Or supply a request-derived previewed value (e.g. from the preview context).
register_graphql_field( 'Post', 'myComputedField', [
    'type'           => 'String',
    'previewResolve' => static function ( $source, $args, $context, $info, $preview ) {
        // $preview is the normalized preview context for the request.
        return '...';
    },
] );
```

A field with neither option resolves from the published post, so forgetting to opt in is safe (the value is current, never broken).

`previewResolve` runs only inside an authorized preview (the request is authenticated and the viewer can edit the post being previewed). It receives the raw preview context, including client-supplied values, so if your callback exposes anything sensitive beyond what an editor of that post may already see, apply your own checks.

### Detecting the preview state

Whether a preview was actually applied is queryable on the node, so a client (a preview toolbar, for example) can render a truthful preview state by including the fields it cares about:

```graphql
query Preview($id: ID!) {
  post(id: $id, idType: DATABASE_ID) {
    isPreview
    previewRevisionDatabaseId
    title
    content
  }
}
```

- `isPreview` is `true` only when previewed values are actually overlaid on this node in this request: there is an autosave to overlay from, or a previewed featured image. An authorized preview with nothing to overlay resolves `false`, exactly like a request without preview context.
- `previewRevisionDatabaseId` exposes the overlay source (the autosave the previewed values come from), or `null` when nothing is overlaid.

Because an unauthorized request never applies the overlay, these fields resolve identically to a request without preview context, so they cannot be used to probe for unpublished content.

### Previewing post meta

Meta keys that WordPress revisions, those registered with `revisions_enabled` (or added via the `wp_post_revision_meta_keys` filter, such as core's `footnotes`), resolve from the revision's own value in a preview, mirroring core. Other meta keys continue to resolve from the published post. The `graphql_resolve_revision_meta_from_parent` filter overrides this per key in either direction: return `false` to resolve a key from the revision, or `true` to force a revisioned key back to the published post.

> **Requires WordPress 6.4+.** Revisioned post meta is built on the meta revisions framework added in WordPress 6.4 (`revisions_enabled` and `wp_post_revision_meta_keys()`). On earlier versions these keys resolve from the published post instead. The rest of the preview overlay (`title`, `content`, `excerpt`, the featured image, and identity preservation) works on all supported WordPress versions.

### Authentication and authorization

A preview is only resolved when **all** of the following are true:

- The request is authenticated.
- The authenticated user can edit the post (`current_user_can( 'edit_post', id )`).
- The `databaseId` in the preview context matches the post being resolved.

If any of these is not met, the request is resolved exactly as if no preview context had been provided: the published node (or `null`, per the usual access rules) is returned, and **no error is thrown**. This is intentional: invalid or unauthorized preview context produces a response identical to a request without one, so it cannot be used to probe for posts a user cannot access.

When `GRAPHQL_DEBUG` is enabled, a debug notice is added to the response `extensions` when preview context was provided for a post the current user is not allowed to preview.

Preview responses are sent with `Cache-Control: no-store, private`, and every GraphQL response includes `Vary: X-GraphQL-Preview`, so a compliant cache never stores a previewed response and never mixes previewed and published responses under one cache key. If a CDN or reverse proxy in front of `/graphql` uses a custom cache key or ignores `Vary`, configure it to honor these headers or to bypass caching for requests carrying `X-GraphQL-Preview`.

### The `nonce` field

The `nonce` field carries the `preview_nonce` WordPress includes in the preview URL, and is **accepted but not verified today**: sending it, omitting it, or sending a stale value all behave identically, and authorization rests entirely on the capability check above. A valid nonce grants nothing on its own.

It exists so a client can forward the query parameters from core's preview URL wholesale, without filtering them. No verification is planned: a WordPress nonce is bound to the session of the user who created it, so it cannot authorize a different viewer, cross-domain or not, and it is therefore the wrong primitive for link-based previews. If WPGraphQL ever offers previews for viewers without accounts, that would be a purpose-built, signed, expiring token under its own key, designed in its own RFC. Clients may send the nonce or omit it; it must not be relied on for any security property.

### Previewing the featured image

WordPress core never stores the previewed featured image on the revision; it passes it as a request parameter on the preview URL. A headless client should forward that value as `featuredImageDatabaseId` in the preview context. When previewing, WPGraphQL then resolves `featuredImage`, `featuredImageId`, and `featuredImageDatabaseId` from `featuredImageDatabaseId` instead of the published featured image. The value must identify an existing image: a `featuredImageDatabaseId` that does not match an existing attachment resolves as no featured image (it is never echoed back as if it were one), and `0` explicitly means the featured image was removed in the preview.

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

4. WPGraphQL resolves the newest autosave for post `43` and overlays the previewable fields. The page renders in a preview state, with the post's identity (`databaseId`, `uri`, and so on) preserved.

The request must be authenticated as a user who can edit the post (via cookie or a token). As in core, the newest autosave is previewed regardless of who authored it, so a preview link opened by another user who can edit the post shows the same preview.

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

**Check your custom fields before migrating.** The two mechanisms invert the previewing convention. The `asPreview` swap was effectively opt-out: the whole node became the revision, so every field resolved against it. The preview context is opt-in per field: a field previews only when it is marked previewable (see [Which fields are previewable](#which-fields-are-previewable)), and an unmarked field resolves from the published post. A custom or extension-provided field that appeared previewed under `asPreview` will show published values under the preview context until it is opted in. If you rely on an extension's fields in previews (WPGraphQL for ACF, for example), confirm the extension has adopted the opt-in before migrating.
