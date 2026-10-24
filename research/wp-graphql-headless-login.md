# WPGraphQL Headless Login (AxeWP/wp-graphql-headless-login)

## Sources and install

- GitHub `AxeWP/wp-graphql-headless-login` HEAD `cbb54b78de` (2026-09-27), plugin header 0.4.4 (matches release 0.4.4). Paths below are relative to the repo root.
- `wp plugin install https://github.com/AxeWP/wp-graphql-headless-login/releases/download/0.4.4/wp-graphql-headless-login.zip --activate`
- **Conflicts with WPGraphQL JWT Authentication.** Both register a root mutation named `login`: Headless Login's is `src/Mutation/Login.php:30` (type name `Login`, lcfirst to `login`), and JWT's is `src/Login.php:22-23` in that plugin. Never activate them together.
- The Password provider must be enabled (option prefix `wpgraphql_login_provider_`, `src/Admin/Settings/ProviderSettings.php:28`). Enable it in GraphQL > Settings > Headless Login > Providers. The exact option payload is not verified here.

## Findings

I grepped `src/` for post and query globals (`global $post`, `get_post()`, `get_the_ID`, `get_queried_object`, conditional tags, `$wp_query`, `the_content`, `setup_postdata`, `switch_to_blog`, `Deferred`). There were no hits. The only "global" state this plugin touches is the **current user**:

| Type.field / mutation | File:line | Global | Category |
| --- | --- | --- | --- |
| `User.auth` (type `AuthenticationData`, registered on every type in `graphql_login_user_types`) | `src/Type/WPObject/AuthenticationData.php:34-52` | none: it reads `$source->ID` and re-wraps the user in a `User` model | SAFE |
| `AuthenticationData.authToken`, `refreshToken`, `userSecret`, `isUserSecretRevoked`, `linkedIdentities`, expirations | `src/Type/WPObject/AuthenticationData.php:75-110` | `get_current_user_id()` in `TokenManager::current_user_can()` (`src/Auth/TokenManager.php:554-566`) | SAFE (current user is not part of node context) |
| `AuthenticationData.wooSessionToken` (only with WooGraphQL) | `src/WoocommerceSchemaFilters.php:51-73` | `get_current_user_id()`, `WC()->session` | SAFE for this change. Woo session state is covered in `wp-graphql-woocommerce.md`. |
| `login` mutation | `src/Mutation/Login.php`, `src/Auth/Auth.php:79` (`wp_set_current_user`) | sets the current user | 4 (low): sets, never reads, query globals. Its payload `user` is a User node, so `user { ... }` fields resolve under user context. |
| `refreshToken` mutation | `src/Mutation/RefreshToken.php:99` (`wp_set_current_user`) | sets the current user | 4 (low) |
| `loginClients` / `loginClient` root fields | `src/Fields/RootQuery.php:44-75` | settings only | SAFE |

Risky fields: **0**. Include it only as a regression smoke test: the login payload is the main place where a User node's fields resolve right after a mutation changed global state.

## Fixture

```bash
wp plugin install https://github.com/AxeWP/wp-graphql-headless-login/releases/download/0.4.4/wp-graphql-headless-login.zip --activate
U=$(wp user create hluser hluser@example.com --user_pass='hl-pass-123' --role=author --porcelain)
P1=$(wp post create --post_title='HL post' --post_status=publish --post_author=$U --porcelain)
```

## Queries

(f) Login, then read the user node and its auth data, then a root field after it. `user.auth.authToken` must be non-null. `viewer` should be the newly logged-in user on both builds (the current user is not node context).

```graphql
mutation HlLogin { login(input: { provider: PASSWORD, credentials: { username: "hluser", password: "hl-pass-123" } }) { authToken user { databaseId name auth { userSecret isUserSecretRevoked } } } }
```

(c) Nested user: post > author > auth. Same result on both builds when authenticated as `hluser`.

```graphql
query HlNested($p1: ID!) { post(id: $p1, idType: DATABASE_ID) { author { node { databaseId auth { isUserSecretRevoked } } } } viewer { databaseId } }
```

The `PasswordProviderResponseInput` field names (`username`, `password`) were not opened in `src/Type/Input/PasswordProviderResponseInput.php:35`. Confirm them with introspection.
