# WPGraphQL Headless Login: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

3 queries. Against `main`: **node-context** (#4575 as it stands) has 2 identical, 1 changed only in test probe fields, 0 with changed extension output; **original-pr** (the first version of #4575) has 2 identical, 1 probe only, 0 with changed extension output.

## Findings

**What in the extension reads the current object.** Nothing. `User.auth` re-wraps the user from `$source->ID` (`src/Type/WPObject/AuthenticationData.php:42-50`). Token fields check the current user, not the current object (`TokenManager::current_user_can()`, `src/Auth/TokenManager.php:554-570`). The `login` mutation sets the current user (`src/Auth/Auth.php:79`). (Version 0.4.4.)

**What was tested.** A post's author `auth` with `viewer`, and the `login` payload's user with `auth` and with a `posts` connection.

**Results.** Extension output is identical on all builds: `authToken` returned, `isUserSecretRevoked: false`, the user's posts listed, `viewer` null for these guest requests. The only change is the `debugLateObject` probe in `hl-c-nested-author-viewer`: on `main` it reported the post and author (`"post":528`, `"queried":30`), and on `node-context` nothing.

**Anything to do.** Nothing.

## Results by query

Each build's responses were captured twice and matched. "Probe only" means the only values that changed are test-only probe fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), which report the current object at a point in the query. Otherwise the cell lists the extension values that changed, as the value on `main` → the value on that build. The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), the queries in [`queries/headless-login/`](../../queries/headless-login), and the fixture IDs they used in [`headless-login.ids`](../headless-login.ids).

| Query | What it checks | node-context vs `main` | original-pr vs `main` |
| --- | --- | --- | --- |
| `hl-c-nested-author-viewer` | a post's author `auth { isUserSecretRevoked }`, plus `viewer` and `debugLateObject`. | probe only | probe only |
| `hl-f-login-payload-user` | the `login` mutation, payload `authToken` and `user { auth }`. | identical | identical |
| `hl-g-login-then-root-probe` | the `login` payload's user with its `posts` connection (despite the name, it has no probe field). | identical | identical |
