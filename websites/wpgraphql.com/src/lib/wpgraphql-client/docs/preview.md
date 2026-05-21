# Preview (planned)

> **Status:** not implemented. `pages/preview.js` currently returns `notFound`. This doc describes the design we'll follow when we add it.

## Goal

Let WordPress editors hit a "Preview" button on a draft and view the unpublished post (or a saved revision) on the headless site, with full template rendering and chrome — without exposing draft content publicly and without leaking it into the public cache.

## Approach: Application Passwords + a shared secret

Two pieces of secrecy, with distinct purposes:

1. **Shared secret** — proves a preview request actually came from WordPress, so an attacker can't hit `/api/preview?postId=42` and read drafts.
2. **Application Password** — authenticates the headless site's GraphQL fetch as a real WP user with edit access, so WPGraphQL returns draft content via WP's existing capability checks.

[Application Passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) are a WordPress core feature, so no custom WP plugin is required for the auth piece — just a small `preview_post_link` filter on the WP side to point the preview button at the headless route.

## Why not the old Faust token?

Faust shipped a custom WP plugin that issued a short-lived preview token and verified it on the headless side. That works, but it ties the headless app to a specific WP plugin. Application Passwords use WordPress core, decouple the headless site from any plugin, and reuse WP's own capability model (`current_user_can('edit_post', $id)`).

## Library changes

```
core/
  client.js                 request({ auth })  →  Authorization header + force POST
  resolve-template.js       resolveTemplate({ auth })  →  thread auth through

next/
  preview.js (new)
    enterPreviewMode(req, res, opts)   validate secret, setPreviewData, redirect
    exitPreviewMode(req, res, opts)    clearPreviewData, redirect
  get-template-static-props.js
    when ctx.preview is true, build auth from env + ctx.previewData and pass through
```

When `auth` is set, `request()`:

- Adds `Authorization: Basic <base64(user:appPassword)>`
- Always uses POST. Authenticated requests must never be GET-cached publicly.

Smart Cache already won't cache requests carrying an `Authorization` header, so the public cache stays clean. Next.js preview-mode pages also automatically bypass the static cache because `getStaticProps` re-runs per request when `ctx.preview` is true.

## Site wiring

```js
// pages/api/preview.js
import { enterPreviewMode } from "lib/wpgraphql-client/next"
export default (req, res) => enterPreviewMode(req, res)

// pages/api/exit-preview.js
import { exitPreviewMode } from "lib/wpgraphql-client/next"
export default (req, res) => exitPreviewMode(req, res)
```

Env vars:

| var | purpose |
|---|---|
| `WPGRAPHQL_PREVIEW_SECRET` | shared secret with WordPress; validated on each preview request |
| `WPGRAPHQL_PREVIEW_USER` | service-account WP username |
| `WPGRAPHQL_PREVIEW_APP_PASSWORD` | Application Password for that user |

## WordPress side

Recommended setup:

1. **Create a service-account user** in WP admin (e.g. `headless-preview`) with the `editor` role. Editor is enough to preview all post types and avoids handing the headless site full admin credentials.
2. **Generate an Application Password** for that user (Users → Edit User → Application Passwords). Store the resulting password in the headless site's `WPGRAPHQL_PREVIEW_APP_PASSWORD` env var.
3. **Define the shared secret** in `wp-config.php`:
   ```php
   define('HEADLESS_PREVIEW_SECRET', '...long random string...');
   ```
   Store the same value in the headless site's `WPGRAPHQL_PREVIEW_SECRET`.
4. **Repoint the preview button** with a small mu-plugin or theme `functions.php` snippet:
   ```php
   add_filter('preview_post_link', function ($link, $post) {
     $secret = defined('HEADLESS_PREVIEW_SECRET') ? HEADLESS_PREVIEW_SECRET : '';
     return add_query_arg([
       'postId' => $post->ID,
       'secret' => $secret,
     ], 'https://wpgraphql.com/api/preview');
   }, 10, 2);
   ```

The shared secret travels in the URL but only over HTTPS to the headless preview endpoint, which redirects (not links) into the preview-cookie'd page. The Application Password never leaves the headless server.

## Flow

```
  Editor clicks Preview in WP admin
            │
            ▼
  WP filter rewrites the link → https://site.com/api/preview?postId=42&secret=...
            │
            ▼
  pages/api/preview.js → enterPreviewMode(req, res)
    1. constant-time compare secret against WPGRAPHQL_PREVIEW_SECRET
    2. fetch post URI via WPGraphQL using the Application Password
    3. res.setPreviewData({ postId, status: 'draft' })
    4. res.redirect(303, postUri)
            │
            ▼
  Browser lands on /post-uri/ with the preview cookie set
            │
            ▼
  Catch-all getStaticProps: ctx.preview === true
    → getTemplateStaticProps detects preview, builds auth from env + previewData
    → resolveTemplate({ uri, params, auth })
    → seed + template + layout queries all sent with Authorization header
            │
            ▼
  WPGraphQL sees authenticated user with edit_post capability
    → returns draft content
            │
            ▼
  Page renders the draft with the same chrome as production
```

## Open questions to decide before implementing

1. **Revision vs. current draft.** WPGraphQL returns the published version by default even for authenticated requests. We'll need to either filter the seed (`where: { status: ANY }`) or accept a `revisionId` from the preview link and fetch the revision explicitly. Picking "latest draft" is simpler; "specific revision" matches what the WP admin's revision browser lets you preview.
2. **Live preview / unsaved changes.** Faust's experimental toolbar used `postMessage` from the WP admin iframe to push unsaved-block edits into the preview. Out of scope for v1; revisit if there's demand.
3. **Per-post-type opt-out.** Some post types (e.g. CodeSnippet) may not have meaningful preview. We can either render whatever the registered template produces, or short-circuit to a generic "preview not supported" page.
4. **Multi-site / multi-user.** This design assumes one shared service account. If different editors should preview as themselves, we'd need a per-user Application Password mechanism (e.g. issued via a small WP plugin), or fall back to a real OAuth flow.
5. **Auth-bypassing the seed query cache.** The seed query is normally GET-cached. In preview mode the auth header prevents that, but we should also consider a separate preview-only seed that includes draft / revision fields the public seed doesn't expose.

## Testing plan

- Unit-level: extend `client.test.js` to assert that `request({ auth })` sends `Authorization` and uses POST, and that preview responses don't go through the GET branch.
- Unit-level: a `preview.test.js` for `enterPreviewMode` / `exitPreviewMode` covering valid secret, invalid secret (constant-time compare), missing env vars, redirect URL shape.
- Integration: a smoke test that posts to `/api/preview` with the right secret, follows the redirect, and confirms the response includes draft content (gated on a real WPGraphQL endpoint with seeded data — likely opt-in CI rather than the default `npm run test:unit`).

## See also

- [Transport](./transport.md) — the GET+queryId default. Preview mode opts out of this for authenticated requests.
- [WordPress Application Passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
- [Next.js Preview Mode (pages router)](https://nextjs.org/docs/pages/building-your-application/configuring/preview-mode)
