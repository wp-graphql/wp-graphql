# ACF Clone Field Schema Scenarios

Focused, importable ACF field-group JSON exports — one per clone-field scenario — used for both automated and manual testing of how WPGraphQL for ACF handles clone fields.

Each artifact is intentionally small (10–30 fields) and isolates **one specific behavior** so that a regression points at exactly the scenario that broke.

## How to use these

### Manual testing (WP admin)

1. Activate WPGraphQL, WPGraphQL for ACF, and ACF Pro (most scenarios require Pro).
2. In WP admin, go to **ACF → Tools → Import Field Groups**.
3. Upload the JSON for the scenario you want to test.
4. Create a post/page of the type the field group is bound to (most are `post`, some are `page` — see the per-scenario notes below).
5. Run the sample GraphQL query against the GraphiQL IDE.

### Automated testing (wpunit)

Load via `acf_add_local_field_group()`. Example:

```php
$json = json_decode( file_get_contents( __DIR__ . '/../../_data/clone-scenarios/cherry-picked-vs-whole-group-matrix.json' ), true );
foreach ( $json as $field_group ) {
    acf_add_local_field_group( $field_group );
}
$schema = \WPGraphQL::get_schema();
$schema->assertValid();
```

## Scenarios

| File | Issue | Bound to | What it isolates |
|---|---|---|---|
| `cherry-picked-vs-whole-group-matrix.json` | [#258](https://github.com/wp-graphql/wpgraphql-acf/issues/258) | post | All 4 clone permutations: whole-group × seamless, whole-group × group, cherry-picked × seamless, cherry-picked × group |
| `prefixed-clone-in-flex-layout.json` | [#269](https://github.com/wp-graphql/wpgraphql-acf/issues/269) | post (+ source on page) | Prefixed seamless clone inside a flex layout |
| `nested-clone-with-group-subfield.json` | [#201](https://github.com/wp-graphql/wpgraphql-acf/issues/201) | post | A → flex → clones B → clones C (with group sub-field) — the wrapped-type interface override case |
| `clone-in-group-image-permutations.json` | [#250](https://github.com/wp-graphql/wpgraphql-acf/issues/250) | page | Hero pattern with 4 group sub-fields, each containing one clone of a (caption + image) source in a different display/prefix permutation |
| `top-vs-nested-display-matrix.json` | [#233](https://github.com/wp-graphql/wpgraphql-acf/issues/233) | page | 4 clone positions: top-level seamless / top-level group / nested seamless / nested group |
| `cyclical-clone-a-b.json` | [#140](https://github.com/wp-graphql/wpgraphql-acf/issues/140) | page | A clones B, B clones A — **currently OOMs the schema build**; artifact is for reproduction, not for an automated pass |
| `list-and-nonnull-wrapped-clone.json` | _no open issue; guardrail_ | post | Clone of a source whose fields use wrapping (list-typed `tags` repeater, non-null `requiredLabel`) — exercises `TypeRegistry::is_compatible_interface_field_override` Path 2 |
| `same-source-cloned-multiple-depths.json` | _no open issue; guardrail_ | post | Same source group cloned in three positions (top-level / inside repeater / inside flex layout) — guards interface dedup + type-name disambiguation |

## Per-scenario details

### `cherry-picked-vs-whole-group-matrix.json` (covers #258)

Two groups: `cloneMatrixSource` (inactive, contains `sectionTitle` + `sectionLinks`) and `cloneMatrixParent` (active, contains four clones).

> ⚠️ **Important about field naming**
>
> `wholeGroupSeamless` and `cherryPickedSeamless` are **never** field names you can query — the whole point of `display=seamless` + `prefix_name=0` is that the cloned fields appear **flat on the parent**, not wrapped in an object named after the clone field.
>
> Only the `display=group` variants produce wrapper fields whose names match the clone field's `graphql_field_name` (`wholeGroupGroup`, `cherryPickedGroup`).

**Expected schema** (approximate)
```graphql
type CloneMatrixParent implements CloneMatrixSource_Fields & AcfFieldGroup & AcfFieldGroupFields {
  # From whole-group seamless clone (via the source's _Fields interface):
  sectionTitle: String
  sectionLinks: String

  # From the whole-group group-display clone (prefixed type):
  wholeGroupGroup: CloneMatrixParentWholeGroupGroup

  # From the cherry-picked group-display clone (prefixed type):
  cherryPickedGroup: CloneMatrixParentCherryPickedGroup
}
```

The cherry-picked seamless clone splices `sectionLinks` directly onto the parent type. That field name collides with `sectionLinks` from the whole-group seamless interface — same name + same scalar type, so they coexist cleanly. The observable difference between "whole-group seamless" and "cherry-picked seamless" is in the `interfaces` list, not the `fields` list.

**Sample query**
```graphql
query CloneMatrix($id: ID!) {
  post(id: $id, idType: DATABASE_ID) {
    cloneMatrixParent {
      __typename
      # From whole-group seamless interface AND cherry-picked seamless splice:
      sectionTitle
      sectionLinks

      # From whole-group group display:
      wholeGroupGroup { sectionTitle sectionLinks }

      # From cherry-picked group display (only sectionLinks was cherry-picked):
      cherryPickedGroup { sectionLinks }
    }
  }
}
```

**Verify the interfaces directly**
```graphql
query {
  __type(name: "CloneMatrixParent") {
    interfaces { name }   # should include CloneMatrixSource_Fields
    fields { name type { name kind } }
  }
}
```

**Pre-fix symptom (#258)**: `sectionLinks` did not appear from the cherry-picked seamless clone — the clone field was silently dropped and (in cherry-pick mode) there's no source group to provide an `_Fields` interface either.

---

### `prefixed-clone-in-flex-layout.json` (covers #269)

One source on `page`, one parent on `post` with flex containing a prefixed seamless clone sub-field named `yo`.

**Sample query**
```graphql
query PrefixedFlex($id: ID!) {
  post(id: $id, idType: DATABASE_ID) {
    prefixedFlexParent {
      flexible {
        ... on PrefixedFlexParentFlexibleSomeLayoutLayout {
          yo { title }  # NOT bare `title` on the layout
        }
      }
    }
  }
}
```

**Expected schema**
- `PrefixedFlexParentFlexibleSomeLayoutLayout` has a `yo` field of type `PrefixedFlexParentFlexibleSomeLayoutLayoutYo` (or similar prefixed name).
- That type implements `PrefixedFlexSource_Fields` and exposes `title`.
- `title` does NOT appear directly on the layout type.

**Pre-fix symptom**: `title` appeared as a bare field on the layout type and `yo` was missing entirely — ACF Pro's seamless splice had eaten the clone field.

---

### `nested-clone-with-group-subfield.json` (covers #201)

Three groups stacked: C has a `group_field`, B clones C as `layout`, A's flex layout clones B as `clonedFieldGroup`. All clones use `display=group` + `prefix_name=1`.

**Sample query**
```graphql
query NestedClone {
  __type(name: "NestedCloneGroupAFlexibleContentLayoutOneLayout") {
    fields { name type { name } }
  }
}
```

**Expected schema**
- `\WPGraphQL::get_schema()->assertValid()` must succeed.
- The `layout` field on the cloned B-type resolves to B's prefixed clone type (not C's).

**Pre-fix symptom**: `Interface field NestedCloneGroupB_Fields.layout expects type NestedCloneGroupCLayout but ...layout is type NestedCloneGroupBLayout` — schema invalid, GraphiQL fails to load.

---

### `clone-in-group-image-permutations.json` (covers #250)

`mediaComponent` (caption + image, inactive) cloned into four group sub-fields of `mediaHero` (on `page`):

| Sub-field | Clone shape |
|---|---|
| `mediaAllFieldsSeamless` | whole group, seamless, no prefix |
| `mediaIndividualFieldsSeamless` | cherry-picked (caption + image), seamless, no prefix |
| `mediaAllFieldsGroup` | whole group, group display, prefixed |
| `mediaIndividualFieldsGroup` | cherry-picked, group display, prefixed |

**Sample query** (after creating a page and filling all four blocks)
```graphql
query MediaPermutations($id: ID!) {
  page(id: $id, idType: DATABASE_ID) {
    mediaHero {
      mediaAllFieldsSeamless        { caption image { node { mediaItemUrl } } }
      mediaIndividualFieldsSeamless { caption image { node { mediaItemUrl } } }
      mediaAllFieldsGroup           { caption image { node { mediaItemUrl } } }
      mediaIndividualFieldsGroup    { caption image { node { mediaItemUrl } } }
    }
  }
}
```

**Expected** — every sub-field returns its non-null caption and image. The original #250 report showed `image: null` for some permutations and missing fields entirely for others.

---

### `top-vs-nested-display-matrix.json` (covers #233)

Single source group cloned in four positions on `topVsNestedPage`: top-level × {seamless, group} × nested-in-group × {seamless, group}.

**Sample query**
```graphql
query TopVsNested($id: ID!) {
  page(id: $id, idType: DATABASE_ID) {
    topVsNestedPage {
      topLevelCloneSeamless # bare `title` flattened onto parent
      topLevelCloneGroup    { title }
      groupWrapper {
        nestedCloneSeamless # bare `title` flattened onto group
        nestedCloneGroup    { title }
      }
    }
  }
}
```

**Expected** — all four positions appear in the schema with the right shape.

---

### `cyclical-clone-a-b.json` (covers #140)

A clones B, B clones A. ACF Pro guards its own recursion via `$this->cloning[$field['key']]`, but the WPGraphQL-for-ACF schema-build side does **not** currently terminate cleanly — importing this artifact and triggering a schema build OOMs the PHP process (verified locally: SIGKILL / exit 137).

The matching wpunit test (`CloneScenarioArtifactsTest::testCyclicalCloneAB`) is marked `markTestSkipped()` for this reason — if it ran, it would kill every test scheduled after it. The artifact is kept available so that when someone tackles #140 they have a copy-pasteable repro, and so manual testers can confirm the failure mode against a fresh wp-env.

**Sample query** (after a future fix)
```graphql
query Cyclical {
  __type(name: "CyclicalCloneA") { name fields { name } }
}
```

**Future-expected (post-#140 fix)**
- Schema build must complete without exceeding PHP memory.
- `CyclicalCloneA` exists with at least `aText` and a `clonesB` field.
- `CyclicalCloneB` exists with `bText` and `clonesA`.
- Recursion through `clonesB.clonesA.clonesB...` must NOT add infinite levels to the type system.

---

### `list-and-nonnull-wrapped-clone.json` (guardrail for `TypeRegistry::is_compatible_interface_field_override` Path 2)

`wrappedSource` defines:
- `tags: [WrappedSourceTags]` (repeater → list-typed object)
- `requiredLabel: String!` (`graphql_non_null: 1`)

`wrappedParent` does a whole-group seamless clone, so it implements `WrappedSource_Fields`.

**Sample query**
```graphql
query Wrapped {
  __type(name: "WrappedParent") {
    interfaces { name }
    fields { name type { kind name ofType { kind name } } }
  }
}
```

**Expected**
- `WrappedParent` implements `WrappedSource_Fields`.
- `WrappedParent.tags` resolves to a list type (kind `LIST`).
- `WrappedParent.requiredLabel` resolves to non-null `String!`.
- No `DUPLICATE_FIELD` debug messages in the log.

---

### `same-source-cloned-multiple-depths.json` (guardrail for interface dedup)

`multiDepthSource` cloned in three positions: top-level (seamless), inside a repeater (prefixed), inside a flex layout (prefixed).

**Sample query**
```graphql
query MultiDepth {
  __type(name: "MultiDepthParent") {
    interfaces { name }
    fields { name type { name } }
  }
}
```

**Expected**
- `MultiDepthSource_Fields` interface implemented once on `MultiDepthParent` (top-level seamless), not duplicated.
- Repeater row type and flex layout type each get their own prefixed clone type, distinct names.
- Schema valid.

## Conventions

- **Naming**: `kebab-case` describing the behavior, not the issue number — issues get closed and renamed; behaviors don't rot.
- **Keys**: prefixed with `group_<scenario>_` and `field_<scenario>_` so multiple scenarios can be imported side-by-side without key collisions.
- **`active`**: Source-only groups are `active: false` so they don't attach to admin screens; consumer groups are `active: true`.
- **`graphql_field_name`**: always explicitly set in camelCase, never relying on auto-generation.
- **`show_in_graphql`**: always `1` for fields and groups intended to appear in schema.
- **Description field**: each group's `description` explains what it is *for*, in case someone discovers it in the WP admin without context.
