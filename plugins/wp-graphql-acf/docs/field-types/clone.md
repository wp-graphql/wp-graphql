# Clone Field

Clone is an ACF Pro field type that lets a field group (or specific fields from it) be re-used inside another field group. WPGraphQL for ACF maps the resulting clone shape onto the GraphQL schema, but a few aspects of how ACF stores values for clone fields are worth understanding before you query them.

## How a clone field appears in the schema depends on three settings

Three ACF settings on the Clone field combine to determine what gets registered in the schema:

1. **`clone[]`** — what was selected:
   - A field-group key (e.g. `group_xxx`) → "whole-group" clone
   - One or more field keys (e.g. `field_xxx`) → "cherry-picked" clone
2. **`display`** — `seamless` or `group`
3. **`prefix_name`** — `0` (no prefix) or `1` (prefix the cloned fields with the clone field's name)

The four common combinations:

| `clone[]` selects | `display` | `prefix_name` | Schema result on the parent type |
|---|---|---|---|
| **Whole group** | `seamless` | `0` | Parent type **implements** the source group's `<Source>_Fields` interface, exposing every source field flat on the parent. No wrapper field for the clone. |
| **Whole group** | `group` | `1` | Parent gets a `<cloneFieldGraphQLName>: <Prefixed>` field. The prefixed type implements the source group's `<Source>_Fields` interface. |
| **Cherry-picked** (field keys) | `seamless` | `0` | Each cherry-picked field is spliced flat onto the parent type (using each field's own `graphql_field_name`). No wrapper, no source interface. |
| **Cherry-picked** (field keys) | `group` | `1` | Parent gets a `<cloneFieldGraphQLName>: <Prefixed>` field. The prefixed type contains only the cherry-picked fields. |

The seamless variants intentionally do **not** produce a wrapper field. Querying for the clone field by its own `graphql_field_name` will fail with `Cannot query field "xxx" on type "..."` — the cloned fields appear flat on the parent instead.

## Gotcha: seamless clones share storage with sibling fields of the same name

ACF stores field values by **field name** within a given parent. When you place multiple `display: seamless` clone fields (or a mix of seamless clone and a same-named regular field) on the same parent group, they all read from and write to the **same underlying meta key**.

This is ACF behavior, not WPGraphQL-for-ACF behavior — but it surfaces in the schema because the GraphQL field appears once and resolves once to that shared value. See ACF's own [Clone field docs](https://www.advancedcustomfields.com/resources/clone/) for the storage semantics.

### What this looks like in admin

Given a field group containing both:
- A whole-group seamless clone of `Source` (which has a `section_links` field)
- A cherry-picked seamless clone of just `Source.section_links`

…ACF will render **two** "Section Links" input boxes in the post editor, but typing in one writes to the same meta key as the other. Save the post and both boxes read back the same value.

### What this looks like in GraphQL

Both seamless paths register `sectionLinks` on the parent type (the whole-group via interface inheritance, the cherry-picked via inline splice). The schema correctly has the field once; the resolver reads the one stored value. You will only ever see one value per field name regardless of how many seamless clones contribute it.

### Workarounds

Choose whichever fits your data model:

1. **Use `display: group` + `prefix_name: 1`** on at least one of the clones. The cloned fields are then namespaced under the clone field's name (`wholeGroupGroup.sectionLinks`, `cherryPickedGroup.sectionLinks`), giving each its own storage and its own GraphQL path.
2. **Use distinct field keys/names in the source(s)**. If the two clones need independent values, point them at different source fields (different `name` and `key`) rather than two clones of the same field.
3. **Don't mix two seamless clones that produce the same field name on the same parent.** ACF won't stop you, but the values will share storage by design.

## Sample query reference

For a parent (`CloneMatrixParent`) that includes all four permutations from the table above:

```graphql
query CloneMatrix($id: ID!) {
  post(id: $id, idType: DATABASE_ID) {
    cloneMatrixParent {
      __typename

      # From whole-group seamless interface AND cherry-picked seamless splice
      # (these share storage — see "Gotcha" above):
      sectionTitle
      sectionLinks

      # From whole-group + group display + prefix:
      wholeGroupGroup { sectionTitle sectionLinks }

      # From cherry-picked + group display + prefix:
      cherryPickedGroup { sectionLinks }
    }
  }
}
```

Inspect the interfaces and field shape directly:

```graphql
query InspectCloneMatrix {
  __type(name: "CloneMatrixParent") {
    interfaces { name }   # includes CloneMatrixSource_Fields
    fields {
      name
      type { kind name ofType { kind name } }
    }
  }
}
```

## See also

- [ACF Clone field documentation](https://www.advancedcustomfields.com/resources/clone/) — upstream behavior, including how cloned values are stored and how `display` + `prefix_name` affect the rendered form.
- [tests/_data/clone-scenarios/](../../tests/_data/clone-scenarios/) — importable JSON artifacts for every common clone shape, including the matrix used above. Useful for reproducing bugs and validating fixes in your own environment.
