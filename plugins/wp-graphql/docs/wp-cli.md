---
uri: "/docs/wp-cli/"
title: "WP-CLI Commands"
---

WPGraphQL adds commands to [WP-CLI](https://wp-cli.org/) under `wp graphql`. Run `wp help graphql` to see them, or `wp help graphql <command>` for the options of one command.

## `wp graphql generate-static-schema`

Writes the GraphQL schema to a file in the GraphQL Schema Definition Language (SDL). This is useful for tools that read a schema file, such as code generators, schema linters and schema diff checks in CI.

```bash
wp graphql generate-static-schema [--output=<output>]
```

- `--output=<output>`: the file to write. Its folder must exist and be writable. Without it, the schema is written to `schema.graphql` in the server's temporary directory, and the command prints the path.

`wp graphql generate` is an alias.

```bash
# Write the schema to the temporary directory
wp graphql generate-static-schema

# Write the schema to a specific file
wp graphql generate-static-schema --output=./schema.graphql
```

The schema includes everything registered on the site, including types and fields added by other plugins, so run the command on a site with the same plugins active as the one your app queries.

## `wp graphql settings-review`

Shows or records the status of the [settings review](/docs/security#settings-review), which walks administrators through settings for access, request limits and debugging.

```bash
wp graphql settings-review <status|skip>
```

- `status` shows whether the review was completed or skipped, and lists any settings that haven't been reviewed yet, such as settings added by an update.
- `skip` records every setting currently in the review as reviewed, without changing any settings. Administrators are then no longer invited to the review. Use it for sites whose settings are managed in code or by deployment scripts.

```bash
# See whether the settings review has been done
wp graphql settings-review status

# Mark the settings review as done without changing any settings
wp graphql settings-review skip
```

On multisite, the review is tracked for each site. Pass `--url` to choose the site:

```bash
wp graphql settings-review skip --url=https://example.com/blog
```

## Commands from extensions

Extensions can add their own commands under `wp graphql`. Each extension documents its commands in its own docs.
