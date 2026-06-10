# Changelog

## Unreleased

> **Breaking changes (5.0.0 prep)** — surfaced here ahead of release because the
> commits landed in mixed `feat(ide)!:` and `test(ide):` shapes during the rebuild.
> release-please will regenerate this section from commit history on the next
> release; until then this is the source of truth.

### Breaking changes

- **Document storage:** the IDE no longer owns a "saved query" primitive of
  its own. The 4.x `graphql_ide_query` post type, the `graphql_ide_collection`
  taxonomy, and the three Document Settings taxonomies
  (`graphql_ide_query_alias`, `graphql_ide_query_maxage`,
  `graphql_ide_query_grant`) are all removed. They duplicated WPGraphQL
  Smart Cache's existing `graphql_document` post type + `graphql_query_alias`
  / `graphql_document_grant` / `graphql_document_http_maxage` /
  `graphql_document_group` taxonomies, which are the canonical owners of the
  GraphQL-document primitive in this ecosystem.

  5.0 IDE now treats saved-document support as **progressive enhancement**:
  the IDE works standalone with local-only unsaved tabs (same model as
  GraphiQL), and lights up the full Saved Queries / Collections / Document
  Settings surface when Smart Cache is also active. No migration is provided
  for existing 4.x installs that have stored queries under `graphql_ide_query`;
  that data was developer-preview only and is considered lost on upgrade.
- **Schema:** the `IdeQuery` / `IdeQueries` GraphQL types and the
  `IdeCollection` / `IdeCollections` GraphQL types are removed (consequence
  of the post-type / taxonomy removals above). Consumers query
  `graphqlDocument` and `graphqlDocumentGroup` directly via Smart Cache's
  schema.
- **REST capability:** the share-collection dialog now requires `list_users`
  in addition to `manage_graphql_ide`. IDE users without `list_users` will see
  the Sharing affordance hidden instead of opening onto a permission-denied
  dead end.
- **REST routes removed:** `/wpgraphql-ide/v1/documents/:id/publish` and
  `/wpgraphql-ide/v1/documents/collections/:id` (the publish-with-hash and
  cascade-delete routes) are removed. The publish flow is now a standard
  `POST /wp/v2/graphql_document/:id` with `status=publish`; Smart Cache's
  `save_document_cb` validates, normalizes, and writes the sha256 hash to
  `post_name` server-side. Cascade-collection deletion is performed
  client-side (delete each child document, then delete the term) since the
  caller already has the documents loaded.
- **Publish duplicate-detection dialog removed:** the old publish endpoint
  returned `already_exists: true` with the existing document ID when a
  duplicate normalized query was detected, which drove a "this query is
  already published" choice dialog. With Smart Cache as the owner,
  `wp_unique_post_slug()` resolves collisions by suffixing the slug
  (`<hash>`, `<hash>-2`, …) and the dialog no longer fires. Identical
  queries published from different drafts will coexist as separate
  documents.
- **Capability filter integrity:** the `wpgraphql_ide_capability_required`
  filter is now consulted at every IDE permission check — REST permission
  callbacks, post-type / taxonomy capability maps, post-meta and user-meta
  auth callbacks, the admin submenu cap, the public-endpoint trimming flag,
  user-can checks against other users, and `get_users()` queries.
  Previously the filter was consulted in exactly one place (the admin menu
  render gate) and bypassed everywhere else, so a host filtering the cap
  to a different value would see the admin link but be 403'd at every
  actual operation. Hosts already relying on the filter to gate IDE
  visibility will now find their override is honored end-to-end —
  intended, but a behavior change.
  Two new global-namespace access functions are introduced:
  `wpgraphql_ide_get_capability()` (returns the filtered cap string) and
  `wpgraphql_ide_user_can()` (`current_user_can()` against it). New code
  should call these directly; the existing namespaced
  `\WPGraphQLIDE\user_has_graphql_ide_capability()` is preserved as a
  back-compat wrapper.
- **Extension hooks pruned.** Major-version surface cleanup: hooks with zero
  external consumers (verified by `gh search code`) and no internal
  callers are removed rather than carried forward as speculative extension
  points. The hooks the IDE *does* still expose
  (`wpgraphql_ide_init`, `wpgraphql_ide_enqueue_script`,
  `wpgraphql_ide_capability_required`, `wpgraphql_ide_context`,
  `wpgraphql_ide_localized_data`, `wpgraphql-ide.init`,
  `wpgraphql-ide.rendered`, `wpgraphql-ide.destroyed`, and the
  per-registry `afterRegister*` JS actions) are unchanged.

  **Removed PHP hooks** (0 external consumers, 0 internal callers):
    - `wpgraphql_ide_endpoint_api_params` filter — the public-endpoint
      GET-param allow-list is now a literal in `public-endpoint.php`.
    - `wpgraphql_ide_register_document_settings` action — the built-in
      Document Settings fields registered through it now `add_action('init',
      ..., 11)` directly. The Document Settings surface is migrating into
      WPGraphQL Smart Cache, so extending fields from outside the IDE is
      a Smart-Cache-side concern.

  **Removed JS actions** (10 paired `register*Error` actions, hypervigilant
  failure-notification surface that no consumer could productively hook):
    - `wpgraphql-ide.registerPreferenceError`
    - `wpgraphql-ide.registerToolbarButtonError`
    - `wpgraphql-ide.registerActivityBarPanelError`
    - `wpgraphql-ide.registerResponseExtensionTabError`
    - `wpgraphql-ide.registerEditorBottomTabError`
    - `wpgraphql-ide.registerStatusBarItemError`
    - `wpgraphql-ide.registerResponseViewModeError`
    - `wpgraphql-ide.registerResponseActionError`
    - `wpgraphql-ide.registerEditorActionError`
    - `wpgraphql-ide.registerDocumentTabActionError`

    Registration failures still log to `console.error` — that's the
    actionable signal. The matching `wpgraphql-ide.afterRegister*` success
    actions are unchanged.

  **Legacy `graphiql_*` hooks** (already not fired in 5.0; documented here
  for upgrade clarity since the 4.x docs referenced them):
    - `graphiql_external_fragments` — was an alias for
      `wpgraphql_ide_external_fragments`. Hook the canonical name; behavior
      now smart-merges (see "New Features" below).
    - `enqueue_graphiql_extension` — was an alias for
      `wpgraphql_ide_enqueue_script`. Hook the canonical name.
    - `graphiql_rendered` — was an alias for the `wpgraphql-ide.rendered`
      JS action. Hook the canonical name via `wp.hooks.addAction`.
    - `graphiql_toolbar_before_buttons` / `graphiql_toolbar_after_buttons` —
      no longer fired; the modern way to add toolbar items is
      `registerDocumentEditorToolbarButton()`.

### New Features

- **`wpgraphql_ide_external_fragments` (restored).** The 4.x PHP filter is
  back, with a behavior upgrade: 5.0 parses each outgoing query, finds
  unresolved fragment spreads, and prepends only the matching fragment
  definitions before sending. Transitive references between external
  fragments are resolved, so a fragment that spreads another fragment
  pulls both in. Unreferenced fragments never go over the wire, and a
  fragment defined in the user's query wins over an external definition
  with the same name. This was a real user-requested 4.x capability that
  was inadvertently dropped during the rebuild; it now ships as a
  declarative way to share canonical fragment shapes (`PostFields`,
  `UserFields`, etc.) across every editor session. See
  `docs/actions-and-filters.md`.

### Schema additions

- **`GraphqlDocument.variables`** and **`GraphqlDocument.headers`** —
  new String fields on Smart Cache's `GraphqlDocument` GraphQL type,
  exposing the IDE's per-document execution context. Same fields are
  added as inputs on `CreateGraphqlDocumentInput` and
  `UpdateGraphqlDocumentInput`. Backed by the existing
  `_graphql_ide_variables` / `_graphql_ide_headers` post meta (the
  SmartCacheBridge already registers the keys), so REST and GraphQL
  reads/writes round-trip against the same storage. Lets downstream
  GraphQL consumers (codegen, third-party tooling, the IDE itself)
  read and write execution context without falling back to REST.

### Internal: REST → GraphQL migration for the IDE client

Document and collection CRUD inside the IDE now runs through GraphQL
via `src/api/graphql-client.js` instead of `apiFetch` against the WP
REST API. The migration is internal — consumer-facing return shapes
from `src/api/documents.js` are unchanged. Execution history is
browser-local (`src/api/history-local.js`) and never went through
REST or GraphQL.

The same `_graphql_ide_*` post meta keys still back the storage and
the SmartCacheBridge keeps the REST routes exposed, so third-party
consumers of the REST surface are unaffected.

Stays REST in this release (no GraphQL equivalent, or one that doesn't
fit a single mutation):

- User preferences. `updateUser` doesn't accept arbitrary user meta.
- The aggregated `documentSettings` REST readback field.
- `/wpgraphql-ide/v1/documents/import|export|reorder` and
  `/collections/reorder` — bulk / non-CRUD orchestration.

`gql()` also gained a one-shot nonce-refresh-and-retry on 401/403
responses (mirroring `@wordpress/api-fetch`'s middleware) so long IDE
sessions that outlive the bootstrap nonce don't start silently
failing.

## [5.0.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-ide/v4.5.0...wp-graphql-ide/v5.0.0) (2026-06-10)


### ⚠ BREAKING CHANGES

* **ide:** rebuild IDE on Smart Cache primitives with WordPress components and CodeMirror 6 ([#3784](https://github.com/wp-graphql/wp-graphql/issues/3784))

### New Features

* **ide:** rebuild IDE on Smart Cache primitives with WordPress components and CodeMirror 6 ([#3784](https://github.com/wp-graphql/wp-graphql/issues/3784)) ([35070c8](https://github.com/wp-graphql/wp-graphql/commit/35070c826fdde48140707e09d196c04451b42c20))


### Bug Fixes

* **ide:** derive saved-document name from operation and fix frozen tab title ([#3897](https://github.com/wp-graphql/wp-graphql/issues/3897)) ([d493b52](https://github.com/wp-graphql/wp-graphql/commit/d493b52b9ddadab04688b0fe8cb4d6396e6aea99))

## [4.5.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-ide/v4.4.1...wp-graphql-ide/v4.5.0) (2026-06-04)


### New Features

* **deps:** bump @wordpress/hooks from 3.58.0 to 4.44.0 ([#3853](https://github.com/wp-graphql/wp-graphql/issues/3853)) ([249fe34](https://github.com/wp-graphql/wp-graphql/commit/249fe3437adc04876d54bcebdd64bad3aaf13343))
* **deps:** bump the npm-prod-minor-patch group across 1 directory with 4 updates ([#3826](https://github.com/wp-graphql/wp-graphql/issues/3826)) ([46d2c97](https://github.com/wp-graphql/wp-graphql/commit/46d2c9743525ff04e3fa4e7d5baad72c30eebe8b))


### Bug Fixes

* Allow compatible interface field override with `register_graphql_field()` ([#3539](https://github.com/wp-graphql/wp-graphql/issues/3539)) ([0cf2b22](https://github.com/wp-graphql/wp-graphql/commit/0cf2b22ab9a9faef4607b62f42cb228542ce5b87))
* **deps-dev:** bump concurrently from 8.2.2 to 9.2.1 ([#3868](https://github.com/wp-graphql/wp-graphql/issues/3868)) ([d8f5b55](https://github.com/wp-graphql/wp-graphql/commit/d8f5b5522f4a993721b8c481b2beb858c6c2fb27))
* **deps-dev:** bump phpstan/phpstan from 2.1.51 to 2.1.54 in /plugins/wp-graphql-ide in the wp-graphql-ide-composer-dev-minor-patch group ([#3818](https://github.com/wp-graphql/wp-graphql/issues/3818)) ([31961db](https://github.com/wp-graphql/wp-graphql/commit/31961db350176eb238fca007dd2b547c10c199bd))
* **deps-dev:** bump phpstan/phpstan from 2.1.54 to 2.1.55 in /plugins/wp-graphql-ide in the wp-graphql-ide-composer-dev-minor-patch group ([#3872](https://github.com/wp-graphql/wp-graphql/issues/3872)) ([d7b013c](https://github.com/wp-graphql/wp-graphql/commit/d7b013cc07d590282c2c0f2246083b48f3221a4a))
* **deps-dev:** bump symfony/dom-crawler from 5.4.48 to 5.4.52 in /plugins/wp-graphql-ide ([#3857](https://github.com/wp-graphql/wp-graphql/issues/3857)) ([c01a41c](https://github.com/wp-graphql/wp-graphql/commit/c01a41cf89878ac77423f7d3f635d2d679e70e15))
* **deps-dev:** bump symfony/yaml from 5.4.45 to 5.4.53 in /plugins/wp-graphql-ide ([#3861](https://github.com/wp-graphql/wp-graphql/issues/3861)) ([e935973](https://github.com/wp-graphql/wp-graphql/commit/e935973da50a9cbd70eadf9f4fb3dbc64f528ecc))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 7 updates ([#3827](https://github.com/wp-graphql/wp-graphql/issues/3827)) ([208feff](https://github.com/wp-graphql/wp-graphql/commit/208feffb8a2aa979dc93f6c2aec240b15e1a13ea))
* **deps-dev:** bump the npm-dev-minor-patch group with 3 updates ([#3849](https://github.com/wp-graphql/wp-graphql/issues/3849)) ([cf0a309](https://github.com/wp-graphql/wp-graphql/commit/cf0a309f5c5b58d8abfa8afd5d798b1141bd04e9))

## [4.4.1](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-ide/v4.4.0...wp-graphql-ide/v4.4.1) (2026-05-08)


### Bug Fixes

* **deps-dev:** bump dotenv from 16.6.1 to 17.4.1 ([#3807](https://github.com/wp-graphql/wp-graphql/issues/3807)) ([90f7ef8](https://github.com/wp-graphql/wp-graphql/commit/90f7ef8dc38200c0fa52a2f1b2619d39528a991b))
* **deps-dev:** bump phpstan/phpstan from 2.1.47 to 2.1.50 in /plugins/wp-graphql-ide in the wp-graphql-ide-composer-dev-minor-patch group ([#3790](https://github.com/wp-graphql/wp-graphql/issues/3790)) ([4e9cb0d](https://github.com/wp-graphql/wp-graphql/commit/4e9cb0d84cdc9143c554fb7025798a7bf2e3d544))
* **deps-dev:** bump phpstan/phpstan from 2.1.50 to 2.1.51 in /plugins/wp-graphql-ide in the wp-graphql-ide-composer-dev-minor-patch group ([#3803](https://github.com/wp-graphql/wp-graphql/issues/3803)) ([ecc4c7e](https://github.com/wp-graphql/wp-graphql/commit/ecc4c7e7e3c7d93043101c9a17e72b92d0b62fe8))
* **deps-dev:** bump the npm-dev-minor-patch group across 1 directory with 6 updates ([#3799](https://github.com/wp-graphql/wp-graphql/issues/3799)) ([5e01960](https://github.com/wp-graphql/wp-graphql/commit/5e0196088e807cabeb502b3e868685c8d6878863))

## [4.4.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-ide/v4.3.0...wp-graphql-ide/v4.4.0) (2026-04-22)


### New Features

* **telemetry:** mirror Appsero insights to telemetry.wpgraphql.com ([#3785](https://github.com/wp-graphql/wp-graphql/issues/3785)) ([bd0c310](https://github.com/wp-graphql/wp-graphql/commit/bd0c310147b7129a74dc9619d11fdf8d3f0d1975))

## [4.3.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-ide/v4.2.0...wp-graphql-ide/v4.3.0) (2026-04-13)


### New Features

* add Appsero telemetry tracking to WPGraphQL IDE ([#3765](https://github.com/wp-graphql/wp-graphql/issues/3765)) ([f82c939](https://github.com/wp-graphql/wp-graphql/commit/f82c939b88de8b08bdd5aff3baa1da4d055dafdd))


### Bug Fixes

* sync readme.txt changelogs with releases ([#3744](https://github.com/wp-graphql/wp-graphql/issues/3744)) ([9054847](https://github.com/wp-graphql/wp-graphql/commit/905484787816133c2f4f09b3b6ff01b40bd1cd4f))

## [4.2.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-ide/v4.1.0...wp-graphql-ide/v4.2.0) (2026-04-07)


### New Features

* **deps:** bump @babel/runtime from 7.24.1 to 7.29.2 in /plugins/wp-graphql-ide ([#3700](https://github.com/wp-graphql/wp-graphql/issues/3700)) ([ec1faff](https://github.com/wp-graphql/wp-graphql/commit/ec1faff41a13b97036117e7409e1fd3c342ec6b3))
* **deps:** bump axios from 1.7.6 to 1.14.0 in /plugins/wp-graphql-ide ([#3706](https://github.com/wp-graphql/wp-graphql/issues/3706)) ([5212b6d](https://github.com/wp-graphql/wp-graphql/commit/5212b6d8528eca6e025592abecf5b072ba44468e))
* **deps:** bump basic-ftp from 5.0.5 to 5.2.0 in /plugins/wp-graphql-ide ([#3684](https://github.com/wp-graphql/wp-graphql/issues/3684)) ([b4a80d4](https://github.com/wp-graphql/wp-graphql/commit/b4a80d4e2a5ef10c93e81df0eb7624064371a804))
* **deps:** bump flatted from 3.3.1 to 3.4.2 in /plugins/wp-graphql-ide ([#3689](https://github.com/wp-graphql/wp-graphql/issues/3689)) ([04d19ab](https://github.com/wp-graphql/wp-graphql/commit/04d19ab0c08c08878d0a451f56f0cb2f9e73164d))
* **deps:** bump form-data from 4.0.0 to 4.0.5 in /plugins/wp-graphql-ide ([#3693](https://github.com/wp-graphql/wp-graphql/issues/3693)) ([f539ac9](https://github.com/wp-graphql/wp-graphql/commit/f539ac9275af221c5fe419c04280dd31c5d82379))
* **deps:** bump http-proxy-middleware from 2.0.6 to 2.0.9 in /plugins/wp-graphql-ide ([#3702](https://github.com/wp-graphql/wp-graphql/issues/3702)) ([36444aa](https://github.com/wp-graphql/wp-graphql/commit/36444aa6be4daeb71c3a4a45e5a5fcf7e073cec7))
* **deps:** bump immutable from 4.3.5 to 4.3.8 in /plugins/wp-graphql-ide ([#3687](https://github.com/wp-graphql/wp-graphql/issues/3687)) ([f2cae8f](https://github.com/wp-graphql/wp-graphql/commit/f2cae8fa9ccd5ef2bc43a48708770113096d5270))
* **deps:** bump lodash from 4.17.21 to 4.18.1 in /plugins/wp-graphql-ide ([#3691](https://github.com/wp-graphql/wp-graphql/issues/3691)) ([df30963](https://github.com/wp-graphql/wp-graphql/commit/df3096332be87c13940f7db1e719cf355bf6dc28))
* **deps:** bump node-forge from 1.3.1 to 1.4.0 in /plugins/wp-graphql-ide ([#3686](https://github.com/wp-graphql/wp-graphql/issues/3686)) ([5a86628](https://github.com/wp-graphql/wp-graphql/commit/5a86628c62e03ccbfa2ba64cd646493771a2fefe))
* **deps:** bump on-headers and compression in /plugins/wp-graphql-ide ([#3678](https://github.com/wp-graphql/wp-graphql/issues/3678)) ([b823e13](https://github.com/wp-graphql/wp-graphql/commit/b823e1383725c7ac497325d7bd560d875a18e442))
* **deps:** bump picomatch from 2.3.1 to 2.3.2 in /plugins/wp-graphql-ide ([#3688](https://github.com/wp-graphql/wp-graphql/issues/3688)) ([61da252](https://github.com/wp-graphql/wp-graphql/commit/61da2522950748cf0c4a12a4c965e2bbee6b7727))
* **deps:** bump qs and body-parser in /plugins/wp-graphql-ide ([#3696](https://github.com/wp-graphql/wp-graphql/issues/3696)) ([7d6b4be](https://github.com/wp-graphql/wp-graphql/commit/7d6b4be973af4104fd4dbf6792b26982bc337712))
* **deps:** bump simple-git from 3.23.0 to 3.33.0 in /plugins/wp-graphql-ide ([#3675](https://github.com/wp-graphql/wp-graphql/issues/3675)) ([d25bdec](https://github.com/wp-graphql/wp-graphql/commit/d25bdecc1a3f335f314cec9eb5cf62eb350f1b4c))
* **deps:** bump svgo from 3.2.0 to 3.3.3 in /plugins/wp-graphql-ide ([#3690](https://github.com/wp-graphql/wp-graphql/issues/3690)) ([b8a4a77](https://github.com/wp-graphql/wp-graphql/commit/b8a4a77a4afb58387e48c1713f1886f677cae76d))
* **deps:** bump the npm-prod-minor-patch group across 1 directory with 5 updates ([#3739](https://github.com/wp-graphql/wp-graphql/issues/3739)) ([6f51727](https://github.com/wp-graphql/wp-graphql/commit/6f5172707a60e2697f4acb6c392eecdca7dddded))
* **deps:** bump webpack from 5.94.0 to 5.105.4 in /plugins/wp-graphql-ide ([#3677](https://github.com/wp-graphql/wp-graphql/issues/3677)) ([ecd97ad](https://github.com/wp-graphql/wp-graphql/commit/ecd97ad4ebea5f38624de8512aee4d3d8a9385a3))
* **deps:** bump yaml from 1.10.2 to 1.10.3 in /plugins/wp-graphql-ide ([#3698](https://github.com/wp-graphql/wp-graphql/issues/3698)) ([3664cea](https://github.com/wp-graphql/wp-graphql/commit/3664cea1a11da2b3211301a7704c7e8d1defdb3e))
* migrate WPGraphQL for ACF to monorepo ([#3581](https://github.com/wp-graphql/wp-graphql/issues/3581)) ([40967cb](https://github.com/wp-graphql/wp-graphql/commit/40967cb5e68a3e6964828d054a4323b2327b271d))


### Bug Fixes

* **deps-dev:** bump rimraf from 5.0.10 to 6.1.3 ([#3666](https://github.com/wp-graphql/wp-graphql/issues/3666)) ([efcbbc1](https://github.com/wp-graphql/wp-graphql/commit/efcbbc15f772aaa6914f538de6941f61a039113a))
* resolve post by percent-encoded slug/URI when post_name is stored encoded ([#3582](https://github.com/wp-graphql/wp-graphql/issues/3582)) ([#3611](https://github.com/wp-graphql/wp-graphql/issues/3611)) ([a473d9b](https://github.com/wp-graphql/wp-graphql/commit/a473d9b9e6dc1bdf4350f6ea5f6847b769d42ea5))

## [4.1.0](https://github.com/wp-graphql/wp-graphql/compare/wp-graphql-ide/v4.0.24...wp-graphql-ide/v4.1.0) (2026-02-06)


### New Features

* import WPGraphQL IDE into monorepo ([#3542](https://github.com/wp-graphql/wp-graphql/issues/3542)) ([e7c1e33](https://github.com/wp-graphql/wp-graphql/commit/e7c1e336ee82e8fe020ca5d6052fa9d330185387))


### Bug Fixes

* resolve all JavaScript linting errors in wp-graphql-ide ([#3548](https://github.com/wp-graphql/wp-graphql/issues/3548)) ([52c39e2](https://github.com/wp-graphql/wp-graphql/commit/52c39e24483344874ab742e9698da4ea5fabe9b6))

## 4.0.24

### Patch Changes

- fde59ee: test13

## 4.0.23

### Patch Changes

- dc527b3: test12

## 4.0.22

### Patch Changes

- 3af6609: test11

## 4.0.21

### Patch Changes

- 4bebba0: test10

## 4.0.20

### Patch Changes

- f0194e1: test9

## 4.0.19

### Patch Changes

- 002a858: test8

## 4.0.18

### Patch Changes

- 4c4fd15: test7

## 4.0.17

### Patch Changes

- fbd12e3: test6

## 4.0.16

### Patch Changes

- 55d17f2: test5

## 4.0.15

### Patch Changes

- 7fd23b6: test4

## 4.0.14

### Patch Changes

- 47bac26: test3

## 4.0.13

### Patch Changes

- 81c75a8: test2

## 4.0.12

### Patch Changes

- 5f99ebc: test

## 4.0.11

### Patch Changes

- 7a53dbc: chore: trigger release

## 4.0.10

### Patch Changes

- 74c832c: chore: add period to description in readme.txt

## 4.0.9

### Patch Changes

- 2eab1a7: chore: update license format in readme.txt to GPL-3.0

## 4.0.8

### Patch Changes

- f610132: fix: remove duplicate git tag creation in release workflow

## 4.0.7

### Patch Changes

- bf627cc: Fixed an issue with plugin updates not appearing on WordPress.org
- b47b46d: ci: attempt to fix GitHub actions auto deploy to wp.org.

## 4.0.6

### Patch Changes

- d1df1d4: chore: update tested WordPress version to 6.8

## 4.0.5

### Patch Changes

- c6cfbc1: fix: linting tooltips are now visible when using the IDE in the drawer
- 6500ef3: fix: broken query composer by adding missing import statements for AbstractArgView and FieldView components. Props to @hacknug for the fix!

## 4.0.4

### Patch Changes

- b4d7302: Test

## 4.0.3

### Patch Changes

- fd9d099: chore: set workflow environment

## 4.0.2

### Patch Changes

- a2b5fbd: - chore: Bump supported WordPress version

## 4.0.1

### Patch Changes

- 477a555: ### Added

  - Introduced `wp_localize_escaped_data()` function for recursively escaping data before localizing it in WordPress. This ensures safe output of strings, URLs, integers, and nested arrays when passing data to JavaScript, using native WordPress functions like `wp_kses_post()` and `esc_url()`.

  ### Improved

  - Enhanced security by ensuring all localized data is properly sanitized before being passed to `wp_localize_script()`, preventing potential XSS vulnerabilities and ensuring safe use of dynamic data in JavaScript.

- 4da3973: - chore: Bump the npm_and_yarn group across 1 directory with 7 updates

## 4.0.0

### Major Changes

- eda911d: Updated the plugin's custom filter and action names to be consistent across the plugin

### Patch Changes

- eda911d: Fixed bug where credentials were being sent in the headers unnecessarily under certain conditions

## 3.0.0

### Major Changes

- 7a07c0c: Change JavaScript hook names to have consistent prefix, and update codebase to meet WordPress.org standards.

## 2.1.5

### Patch Changes

- cb6eda0: Reorder sidebar menu to always have the IDE first.
- 1f50c93: Fixes issue where custom capability was not being assigned to the administrator role. This now happens on plugin activation.

## 2.1.4

### Patch Changes

- 8f6f131: Update license to GPL-3

## 2.1.3

### Patch Changes

- 66f7e28: - Remove npm workspaces and have webpack handle compiling of the main app and internal plugins.
- 43479e0: - Add settings link to the IDE Settings tab from the WordPress settings page.
- 1cfbdff: - **New Capability**: Introduced a new custom capability `manage_graphql_ide`. This capability allows administrators to control access to the WPGraphQL IDE functionalities. The capability has been assigned to the `administrator` role by default.

## 2.1.2

### Patch Changes

- f6745e9: - fix missing styles in latest release

## 2.1.1

### Patch Changes

- c450e5a: - Reorganized plugin directories/files.

## 2.1.0

### Minor Changes

- 6752c37: - Added new settings section to WPGraphQL, IDE Settings

  - Added new setting, Admin Bar Link Behavior

  ![WPGraphQL IDE Settings tab showing the admin bar link behavior and Show legacy editor settings](https://github.com/wp-graphql/wpgraphql-ide/assets/6676674/59236b4c-0019-40a8-ae9b-a1228997f30c)

## 2.0.0

### Major Changes

- 43eea79: Refactored stores, including renaming 'wpgraphql-ide' to 'wpgraphql-ide/app', and adding additional stores such as 'wpgraphql-ide/editor-toolbar.

  Added `registerDocumentEditorToolbarButton` function to public API.

  This function allows registering a new editor toolbar button with the following parameters:

  - `name` (string): The name of the button to register.
  - `config` (Object): The configuration object for the button.
  - `priority` (number, optional): The priority for the button, with lower numbers meaning higher priority. Default is 10.

  Example usage:

  ```js
  const { registerDocumentEditorToolbarButton } = window.WPGraphQLIDE;

  registerDocumentEditorToolbarButton("toggle-auth", toggleAuthButton, 1);
  ```

  ![Screenshot of the GraphiQL IDE highlighting the Toolbar buttons within the Document Editor region.](https://github.com/wp-graphql/wpgraphql-ide/assets/1260765/2395c3c8-1915-4a24-b64e-35ebe16e674f)

## 1.1.9

### Patch Changes

- 194821c: - fix: The IDE no longer waits on `DOMContentLoaded` in order to help client side performance with heavier pages.
  - add: New PHP filters for updating the drawer label:
    - `wpgraphqlide_drawer_button_label`
    - `wpgraphqlide_drawer_button_loading_label`
- f5130d9: docs: Remove link to community Slack on "Help Page" in favor of link community Discord (recently migrated)

## 1.1.8

### Patch Changes

- b005b0e: update tested up to version to WordPress 6.5

## 1.1.7

### Patch Changes

- 195dba9: fix: update z-index of the CodeMirror-info tooltip to show above the drawer

## 1.1.6

### Patch Changes

- b3164da: fix GitHub release upload

## 1.1.5

### Patch Changes

- 364b930: Fix release automation

## 1.1.4

### Patch Changes

- f0aaec1: automate github release upload

## 1.1.3

### Patch Changes

- 1f3d5b4: Fix automation of release artifact

## 1.1.3

### Patch Changes

- 3f6969a: Fix automation of release artifact

## 1.1.2

### Patch Changes

- 4660517: Fix automation of release artifact

## 1.1.1

### Patch Changes

- 45333ab: Fix automation of release artifact
- 6d73d1b: Fix automation of release artifact

## [1.1.0] - 2024-04-3

### Minor Changes

- 75ec916: Add help page as a built-in plugin

### Patch Changes

- c76d592: test
- e616aab: Added changesets to assist with releases

## [1.0.1] - 2024-01-22

### Fixed

- Fixed inability to select text inside of editor

### Added

- Focusable dismiss button to close the drawer

## [1.0.0] - 2023-11-22

### Added

- GraphiQL 3.\*
