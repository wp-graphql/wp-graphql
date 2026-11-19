---
uri: "/field-types/clone-field/"
title: "Clone Field"
provider: "ACF Pro"
---

> This field type does not have full documentation yet. Contributions are welcome: edit this file and open a pull request on the [WPGraphQL monorepo](https://github.com/wp-graphql/wp-graphql).

**Provided by:** ACF Pro

## Restricted field groups

The fields of a cloned field group keep that field group’s `graphql_capability` wherever they're cloned to, so cloning a restricted field group into another field group doesn't make its fields public. See [How field group and field capabilities combine](../adding-acf-fields-to-the-wpgraphql-schema.md#how-field-group-and-field-capabilities-combine).
