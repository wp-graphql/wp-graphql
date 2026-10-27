# WPGraphQL Headless Login: node-scoped context results

Part of the compatibility tests for [wp-graphql/wp-graphql#4575](https://github.com/wp-graphql/wp-graphql/pull/4575). See the [README](../../README.md) for what the change is, the builds compared, and how to run these queries yourself.

3 queries. Compared with `main`, **#4575** (`node-context`) changed extension output in 0, changed only the probe in 1, and left 2 the same. **The first version of #4575** (`original-pr`) changed extension output in 0, only the probe in 1, and left 2 the same.

## Anything to do?

**No changes needed.** Extension output is identical on `main` and with #4575.

## Findings

**What in the extension reads the current object.** Nothing. `User.auth` re-wraps the user from `$source->ID` (`src/Type/WPObject/AuthenticationData.php:42-50`). Token fields check the current user, not the current object (`TokenManager::current_user_can()`, `src/Auth/TokenManager.php:554-570`). The `login` mutation sets the current user (`src/Auth/Auth.php:79`). (Version 0.4.4.)

**What was tested.** A post's author `auth` with `viewer`, and the `login` payload's user with `auth` and with a `posts` connection.

**Results.** Extension output is identical on all builds: `authToken` returned, `isUserSecretRevoked: false`, the user's posts listed, `viewer` null for these guest requests. The only change is the `debugLateObject` probe in `hl-c-nested-author-viewer`: on `main` it reported the post and author (`"post":528`, `"queried":30`), and on `node-context` nothing.

## Results by query

Each build's responses were captured twice and matched. Each cell compares that build's response with `main`'s for the same query:

- **Same as `main`**: every value in the response is identical.
- **Extension output**: values from the extension's own fields that differ, as the value on `main` → the value on that build. Long values show the part around the first difference.
- **Probe**: test-only fields from [`probe/`](../../probe/zz-ext-matrix-probe.php), selected at a point in the query, that report what WordPress treats as current there: `post` is the global `$post`, `queried` the main query's queried object, `product` WooCommerce's global `$product`, `authordata` the global `$authordata`, and `singular`, `tax`, `isProduct` and the like are conditional tags. They aren't extension output; they show what an extension reading the current object would see. Inside a node the right answer is that node. At the root, after the nodes, it's whatever was current before the query, which on these requests is nothing (`none`).
- Fixture names such as `U`, `P1` are the ones in [`headless-login.ids`](../headless-login.ids), shown with their IDs.

The responses are in [`main/`](main), [`original-pr/`](original-pr) and [`node-context/`](node-context), and the queries in [`queries/headless-login/`](../../queries/headless-login).

| Query | What it checks | #4575 (`node-context`) | First version of #4575 (`original-pr`) |
| --- | --- | --- | --- |
| `hl-c-nested-author-viewer` | a post's author `auth { isUserSecretRevoked }`, plus `viewer` and `debugLateObject`. | **Extension output:** same as `main`<br>**Probe** `lateObj`: author true → false, authordata U (30) → none, post P1 (528) → none, queried U (30) → none | **Extension output:** same as `main`<br>**Probe** `lateObj`: author true → false, queried U (30) → none |
| `hl-f-login-payload-user` | the `login` mutation, payload `authToken` and `user { auth }`. | Same as `main`. | Same as `main`. |
| `hl-g-login-then-root-probe` | the `login` payload's user with its `posts` connection (despite the name, it has no probe field). | Same as `main`. | Same as `main`. |
