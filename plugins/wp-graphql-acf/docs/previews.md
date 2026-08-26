# Previewing ACF Field Values

WPGraphQL for ACF supports WPGraphQL's post previews: a headless front end can render the in-progress, unsaved version of a post's ACF field values, the way clicking **Preview** in wp-admin shows unsaved edits.

Previews are requested through WPGraphQL's request-level preview context (the `X-GraphQL-Preview` header, or the `preview` object in the request `extensions`), documented in [WPGraphQL's previews guide](https://www.wpgraphql.com/docs/previews/). The legacy `asPreview` argument also resolves ACF values, but it is deprecated in favor of the preview context.

## How it works

When an authorized request (authenticated, and able to edit the post) carries preview context targeting a post, the ACF field group fields on that post resolve their values from the post's autosave, the revision WordPress saves when an editor clicks **Preview**, while the post keeps its published identity (`id` and `databaseId` stay the published post's). Fields resolve their published values for everyone else, and an invalid or unauthorized preview request behaves exactly like a request without one.

```graphql
query Preview($id: ID!) {
  post(id: $id, idType: DATABASE_ID) {
    databaseId # stays the published post's id
    isPreview  # true when previewed values are overlaid
    postFields {
      heroHeadline # resolves from the autosave in a preview
    }
  }
}
```

## Editor support

Whether ACF values reach the autosave depends on how the post is edited:

- **Classic editor and ACF Forms**: supported. WordPress saves the full form, including ACF values, to the autosave when an editor clicks Preview.
- **Block editor (Gutenberg)**: supported when ACF's **Block Editor Datastore** is enabled, which requires **ACF PRO 6.8.1+** and **WordPress 6.7+**. The datastore saves field values through the editor's native save flow with revision and autosave support:

  ```php
  add_filter( 'acf/settings/enable_datastore', '__return_true' );
  ```

  Without the datastore, the block editor never stores ACF values on autosaves, so there is nothing to preview and the published values are resolved instead (the post's title and content still preview). This is a limitation of how the block editor saves metabox data, not of WPGraphQL for ACF; before the datastore existed, no supported path put ACF values on block editor autosaves at all.

  Note ACF's own documented datastore limitations apply (for example, relational sub-fields inside Repeater and Group fields), see [Using the ACF Datastore](https://www.advancedcustomfields.com/resources/using-the-acf-datastore/).

## What is not previewable

Previews are a post-level concern. ACF field groups assigned to users, taxonomy terms, comments, menus, or options pages resolve their stored values regardless of preview context.

## Troubleshooting

- **ACF fields show published values in a preview (block editor)**: enable the datastore (see above). With `GRAPHQL_DEBUG` enabled, the response includes a debug message explaining this when it applies.
- **Nothing previews at all**: previews resolve from autosaves, which are revisions. Some hosts disable post revisions by default; previews require them, so ask your host to enable revisions if autosaves are not being created.
- **The request must be authorized**: previews resolve only for an authenticated user who can edit the post being previewed. See [authentication and authorization in the WPGraphQL previews guide](https://www.wpgraphql.com/docs/previews/).
