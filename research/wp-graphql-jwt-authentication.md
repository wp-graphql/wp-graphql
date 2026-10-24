# WPGraphQL JWT Authentication (wp-graphql/wp-graphql-jwt-authentication)

## Sources and install

- GitHub `wp-graphql/wp-graphql-jwt-authentication` HEAD `d003a3485f` (2026-03-13). Plugin header 0.7.0; latest release v0.7.2.
- `wp plugin install https://github.com/wp-graphql/wp-graphql-jwt-authentication/releases/download/v0.7.2/wp-graphql-jwt-authentication.zip --activate`, plus `wp config set GRAPHQL_JWT_AUTH_SECRET_KEY 'test-secret-key-change-me'`.
- **Conflicts with WPGraphQL Headless Login** (both register the `login` mutation). See `wp-graphql-headless-login.md`.

## Findings

There are no reads of post or query globals (I grepped `src/` and the main file). Current-user handling only:

| Type.field / mutation | File:line | Global | Category |
| --- | --- | --- | --- |
| `User.jwtAuthToken`, `jwtRefreshToken`, `jwtUserSecret`, `jwtAuthExpiration`, `isJwtAuthSecretRevoked` | `src/ManageTokens.php:70-180` (`register_jwt_fields_to`, which reads `$user->userId` / `$user->ID`) | `get_current_user_id()` / `current_user_can()` (`src/Auth.php:149`, `239-240`) | SAFE |
| Same fields on mutation payloads | `src/ManageTokens.php:208` | same | SAFE |
| `login` mutation | `src/Login.php:22`, `src/Auth.php:75` (`wp_set_current_user`) | sets the current user | 4 (low): no query-global reads |
| `refreshJwtAuthToken` mutation | `src/RefreshToken.php:21-38` | token validation only | SAFE |
| `determine_current_user` filter | `wp-graphql-jwt-authentication.php:168-169`, `src/Auth.php:408` | runs before execution | SAFE |

Risky fields: **0**.

## Fixture and smoke queries

```bash
U=$(wp user create jwtuser jwtuser@example.com --user_pass='jwt-pass-123' --role=author --porcelain)
P1=$(wp post create --post_title='JWT post' --post_status=publish --post_author=$U --porcelain)
```

(f) Login: the payload user's token fields must be non-null.

```graphql
mutation JwtLogin { login(input: { username: "jwtuser", password: "jwt-pass-123" }) { authToken user { databaseId jwtAuthToken jwtRefreshToken } } }
```

(c) post > author > JWT fields, sent as `jwtuser` with `Authorization: Bearer <authToken>`. Expect identical results on main and new.

```graphql
query JwtNested($p1: ID!) { post(id: $p1, idType: DATABASE_ID) { author { node { jwtAuthExpiration isJwtAuthSecretRevoked } } } }
```
