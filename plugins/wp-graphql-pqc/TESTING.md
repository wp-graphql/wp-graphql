# Manual Testing Guide for WPGraphQL Persisted Query Cache

This guide walks you through manually testing the plugin's core functionality. For the formal protocol (cold vs warm GET, hashes, POST extensions), see [docs/SPEC.md](./docs/SPEC.md).

## Prerequisites

1. **WordPress environment running** (via `wp-env` or similar)
2. **WPGraphQL** and **WPGraphQL Smart Cache** plugins activated
3. **WPGraphQL Persisted Query Cache** plugin activated
4. **Rewrite rules flushed** (if you just activated the plugin, visit Settings → Permalinks and click "Save Changes", or run `wp rewrite flush` via WP-CLI)

## Important: Nonce-Based PQC Flow

**Breaking Change**: As of v0.1.0-beta.1, the plugin requires a nonce-based flow for security:

1. **GET** `/graphql/persisted/{hash}` → HTTP **200** with GraphQL `errors` (`PERSISTED_QUERY_NOT_FOUND`) and `extensions.persistedQueryNonce`
2. **POST** with query + nonce + hashes in extensions → Validates and stores

This prevents random POST requests from persisting queries. GraphQL IDEs that don't support this flow (like WPGraphQL IDE) will need to be updated. For testing, use Postman, curl, or other tools that support custom request extensions.

## Database Structure

The plugin uses a normalized database structure:

- **`wp_wpgraphql_pqc_documents`**: Stores unique query documents (one row per unique `query_hash`)
  - `query_hash` (PRIMARY KEY): SHA-256 hash of the normalized query document
  - `query_document`: The full GraphQL query string
  - `created_at`: Timestamp when the document was first stored

- **`wp_wpgraphql_pqc_url_keys`**: Junction table mapping URLs to cache keys (one row per URL + cache key combination)
  - `url_hash` + `cache_key` (PRIMARY KEY): Composite key
  - `url`: The persisted query URL (e.g., `/graphql/persisted/{queryHash}/variables/{variablesHash}`)
  - `query_hash`: References `wp_wpgraphql_pqc_documents.query_hash`
  - `variables_hash`: SHA-256 hash of the canonicalized variables JSON
  - `variables`: The variables JSON string
  - `cache_key`: A single cache key (e.g., `post:123`, `list:post`)
  - `created_at`: Timestamp when the mapping was created

This normalization prevents duplicate storage of the same query document when it's used with different variables or cache keys.

## Test 1: Complete PQC Flow - Store Query in Index (with Nonce)

This test demonstrates the complete PQC flow from start to finish. The flow requires:
1. Computing the query hash (client-side)
2. GETting the persisted URL to receive a nonce
3. POSTing with the query, nonce, and hashes

### Step 1: Compute Query Hash

First, compute the hash for your query. The hash must match exactly what the server computes.

**Using wp-cli (for testing):**
```bash
npm run wp-env -- run cli -- wp eval '
require_once "wp-content/plugins/wp-graphql-pqc/vendor/autoload.php";
use WPGraphQL\PQC\Utils\Hasher;
$query = "query GetPosts { posts { nodes { id title } } }";
$hash = Hasher::hash_query($query);
echo "Query: " . $query . "\n";
echo "Hash: " . $hash . "\n";
'
```

**Example Output:**
```
Query: query GetPosts { posts { nodes { id title } } }
Hash: a924d644607ac5c62709bd3dfd4d20f4523fe0207f16fd1329f0bda855e40e40
```

**Note**: In a real client implementation, you would compute the hash client-side using SHA-256 of the normalized query document (using GraphQL Printer for normalization, same as the server).

### Step 2: GET the Persisted URL (Receive Nonce)

Make a GET request to the persisted URL using the hash from Step 1:

```bash
QUERY_HASH="a924d644607ac5c62709bd3dfd4d20f4523fe0207f16fd1329f0bda855e40e40"

curl -X GET "http://localhost:8888/graphql/persisted/${QUERY_HASH}" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 200 status (GraphQL convention: errors in response, not HTTP status)
- JSON response with error and nonce:
  - `errors`: Array with error message and code
  - `extensions.persistedQueryNonce`: A nonce token (64 character hex string)

**Example Response:**
```json
{
  "errors": [
    {
      "message": "Persisted query not found",
      "extensions": {
        "code": "PERSISTED_QUERY_NOT_FOUND"
      }
    }
  ],
  "extensions": {
    "persistedQueryNonce": "ea3ae26445c0e2b136f862750518fd1fbd35f972df19f6f4d4df42625029be4e"
  }
}
```

**Extract the nonce:**
```bash
NONCE=$(curl -s -X GET "http://localhost:8888/graphql/persisted/${QUERY_HASH}" \
  -H "Accept: application/json" | jq -r '.extensions.persistedQueryNonce')

echo "Nonce: $NONCE"
```

### Step 3: POST Query with Nonce and Hashes

Now make a POST request with the query, nonce, and computed hashes:

```bash
curl -X POST "http://localhost:8888/graphql" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"query\": \"query GetPosts { posts { nodes { id title } } }\",
    \"extensions\": {
      \"persistedQueryNonce\": \"${NONCE}\",
      \"persistedQueryHash\": \"${QUERY_HASH}\",
      \"persistedVariablesHash\": \"\"
    }
  }"
```

**Expected Result:**
- Query executes successfully
- Response includes `extensions.persistedQueryUrl` with the persisted URL
- Query is stored in the database

**Example Response:**
```json
{
  "data": {
    "posts": {
      "nodes": [...]
    }
  },
  "extensions": {
    "persistedQueryUrl": "/graphql/persisted/a924d644607ac5c62709bd3dfd4d20f4523fe0207f16fd1329f0bda855e40e40",
    "queryAnalyzer": {
      "keys": "..."
    }
  }
}
```

**Important**: 
- The `persistedQueryHash` in extensions **must match** the hash used in the GET request
- The `persistedQueryHash` **must match** the server's computed hash for the query
- If hashes don't match, the query will execute but **will not be stored**
- If nonce is missing or invalid, the query will execute but **will not be stored**

### Step 4: Verify Database Entry

Check the database tables in your database (using TablePlus, phpMyAdmin, etc.):

**Check the documents table:**
```sql
SELECT * FROM wp_wpgraphql_pqc_documents ORDER BY created_at DESC LIMIT 1;
```

**Check the url_keys table:**
```sql
SELECT * FROM wp_wpgraphql_pqc_url_keys ORDER BY created_at DESC LIMIT 1;
```

**Expected Result:**
- **In `wp_wpgraphql_pqc_documents`**: A new row with:
  - `query_hash`: SHA-256 hash of the query (primary key) - matches the hash you computed
  - `query_document`: The original query string
  - `created_at`: Current timestamp

- **In `wp_wpgraphql_pqc_url_keys`**: One or more rows (one per cache key) with:
  - `url_hash`: SHA-256 hash of the URL
  - `url`: The persisted query URL (e.g., `/graphql/persisted/a924d644...`)
  - `query_hash`: SHA-256 hash of the query (references documents table)
  - `variables_hash`: Empty string (no variables)
  - `variables`: Empty string
  - `cache_key`: A single cache key (e.g., `list:post`)
  - `created_at`: Current timestamp

### Step 5: Test GET to Persisted URL

Now test that the persisted URL works:

```bash
PERSISTED_URL="/graphql/persisted/a924d644607ac5c62709bd3dfd4d20f4523fe0207f16fd1329f0bda855e40e40"

curl -X GET "http://localhost:8888${PERSISTED_URL}" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 200 status
- Same data as the original POST request
- Response headers include cache headers (for public requests) or `no-store` (for authenticated requests)

### Step 6: Test with Variables

For queries with variables, follow the same flow:

1. **GET the persisted URL** (with variables hash in the path):
```bash
curl -X GET http://localhost:8888/graphql/persisted/{queryHash}/variables/{variablesHash}
```

2. **POST with nonce, query, variables, and hashes**:
```bash
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetPost($id: ID!) { post(id: $id) { id title } }",
    "variables": { "id": "cG9zdDox" },
    "extensions": {
      "persistedQueryNonce": "nonce_from_404_response",
      "persistedQueryHash": "computed_query_hash",
      "persistedVariablesHash": "computed_variables_hash"
    }
  }'
```

**Expected Result:**
- Response includes `extensions.persistedQueryUrl` with a URL like `/graphql/persisted/{queryHash}/variables/{variablesHash}`
- Database entries include both `query_hash` and `variables_hash`
- Query document is stored in `wp_wpgraphql_pqc_documents` (normalized, stored once)
- URL-key mappings are stored in `wp_wpgraphql_pqc_url_keys` (one row per cache key)

## Test 2: GET Request - Retrieve and Execute Persisted Query

This test verifies that persisted queries can be retrieved and executed via GET requests.

### Step 1: Get the Persisted URL from Previous Test

From the POST response in Test 1, copy the `extensions.persistedQueryUrl` value (e.g., `/graphql/persisted/a924d644...`).

### Step 2: Make a GET Request to the Persisted URL

```bash
PERSISTED_URL="/graphql/persisted/a924d644607ac5c62709bd3dfd4d20f4523fe0207f16fd1329f0bda855e40e40"

curl -X GET "http://localhost:8888${PERSISTED_URL}" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 200 status
- Query is retrieved from the database (JOIN between documents and url_keys tables)
- Query is re-executed
- Same data as the original POST request
- Response headers include `Content-Type: application/json`
- For public requests: cacheable headers (respects Smart Cache settings)
- For authenticated requests: `Cache-Control: no-store` headers

**Example Response:**
```json
{
  "data": {
    "posts": {
      "nodes": [
        {
          "id": "cG9zdDoxMA==",
          "title": "PoC post"
        }
      ]
    }
  }
}
```

### Step 3: Test with Variables

If you have a persisted query with variables, use the full URL:

```bash
PERSISTED_URL="/graphql/persisted/{queryHash}/variables/{variablesHash}"

curl -X GET "http://localhost:8888${PERSISTED_URL}" \
  -H "Accept: application/json"
```

**Expected Result:**
- Query and variables are retrieved from the database
- Query is re-executed with the variables
- Same response as the original POST request

### Step 4: Test Error Response for Non-Existent Query

```bash
curl -X GET "http://localhost:8888/graphql/persisted/0000000000000000000000000000000000000000000000000000000000000000" \
  -H "Accept: application/json"
```

**Expected Result:**
- HTTP 200 status (GraphQL convention: errors in response, not HTTP status)
- JSON error response with:
  - `errors[0].message`: "Persisted query not found"
  - `errors[0].extensions.code`: "PERSISTED_QUERY_NOT_FOUND"
  - `extensions.persistedQueryNonce`: Nonce token for the PQC flow
- `Cache-Control: no-store` headers (nonces should not be cached)

## Complete Test Flow Scripts

### Script 1: Query Without Variables

Here's a complete bash script that tests the full PQC flow for a query without variables:

```bash
#!/bin/bash

# Complete PQC Flow Test Script

# Step 1: Compute query hash
echo "=== Step 1: Computing query hash ==="
QUERY='query GetPosts { posts { nodes { id title } } }'
QUERY_HASH=$(npm run wp-env -- run cli -- wp eval "
require_once 'wp-content/plugins/wp-graphql-pqc/vendor/autoload.php';
use WPGraphQL\PQC\Utils\Hasher;
echo Hasher::hash_query('$QUERY');
" 2>/dev/null | tail -1)

echo "Query: $QUERY"
echo "Hash: $QUERY_HASH"
echo ""

# Step 2: GET persisted URL to receive nonce
echo "=== Step 2: GET persisted URL (receive nonce) ==="
GET_RESPONSE=$(curl -s -X GET "http://localhost:8888/graphql/persisted/${QUERY_HASH}" \
  -H "Accept: application/json")

echo "$GET_RESPONSE" | jq '.'

NONCE=$(echo "$GET_RESPONSE" | jq -r '.extensions.persistedQueryNonce // empty')

if [ -z "$NONCE" ]; then
  echo "ERROR: No nonce received"
  exit 1
fi

echo "Nonce: $NONCE"
echo ""

# Step 3: POST with query, nonce, and hashes
echo "=== Step 3: POST query with nonce and hashes ==="
POST_RESPONSE=$(curl -s -X POST "http://localhost:8888/graphql" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"query\": \"$QUERY\",
    \"extensions\": {
      \"persistedQueryNonce\": \"$NONCE\",
      \"persistedQueryHash\": \"$QUERY_HASH\",
      \"persistedVariablesHash\": \"\"
    }
  }")

echo "$POST_RESPONSE" | jq '.extensions | {persistedQueryUrl, queryAnalyzer: .queryAnalyzer.keys}'

PERSISTED_URL=$(echo "$POST_RESPONSE" | jq -r '.extensions.persistedQueryUrl // empty')

if [ -z "$PERSISTED_URL" ]; then
  echo "ERROR: Query was not persisted"
  exit 1
fi

echo ""
echo "✓ Query persisted successfully!"
echo "Persisted URL: $PERSISTED_URL"
echo ""

# Step 4: Test GET to persisted URL
echo "=== Step 4: GET persisted URL (should return cached data) ==="
GET_CACHED=$(curl -s -X GET "http://localhost:8888${PERSISTED_URL}" \
  -H "Accept: application/json")

echo "$GET_CACHED" | jq '.data.posts.nodes[0] | {id, title}'

echo ""
echo "✓ Complete PQC flow test successful!"
```

### Script 2: Query With Variables

Here's a complete bash script that tests the full PQC flow for a query **with variables**:

```bash
#!/bin/bash

# Complete PQC Flow Test Script (with Variables)

# Step 1: Compute query and variables hashes
echo "=== Step 1: Computing query and variables hashes ==="
QUERY='query GetPost($id: ID!) { post(id: $id) { id title } }'
VARIABLES='{"id":"cG9zdDox"}'

QUERY_HASH=$(npm run wp-env -- run cli -- wp eval 'require_once "wp-content/plugins/wp-graphql-pqc/vendor/autoload.php"; use WPGraphQL\PQC\Utils\Hasher; echo Hasher::hash_query("query GetPost(\$id: ID!) { post(id: \$id) { id title } }");' 2>/dev/null | tail -1)

VARIABLES_HASH=$(npm run wp-env -- run cli -- wp eval 'require_once "wp-content/plugins/wp-graphql-pqc/vendor/autoload.php"; use WPGraphQL\PQC\Utils\Hasher; $vars = ["id" => "cG9zdDox"]; echo Hasher::hash_variables($vars);' 2>/dev/null | tail -1)

echo "Query: $QUERY"
echo "Variables: $VARIABLES"
echo "Query Hash: $QUERY_HASH"
echo "Variables Hash: $VARIABLES_HASH"
echo ""

# Step 2: GET persisted URL to receive nonce
echo "=== Step 2: GET persisted URL (receive nonce) ==="
GET_RESPONSE=$(curl -s -X GET "http://localhost:8888/graphql/persisted/${QUERY_HASH}/variables/${VARIABLES_HASH}" \
  -H "Accept: application/json")

echo "$GET_RESPONSE" | jq '.'

NONCE=$(echo "$GET_RESPONSE" | jq -r '.extensions.persistedQueryNonce // empty')

if [ -z "$NONCE" ]; then
  echo "ERROR: No nonce received"
  exit 1
fi

echo "Nonce: $NONCE"
echo ""

# Step 3: POST with query, variables, nonce, and hashes
echo "=== Step 3: POST query with variables, nonce, and hashes ==="

# Create POST data file (easier for complex JSON)
cat > /tmp/post_vars.json << JSON
{
  "query": "query GetPost(\$id: ID!) { post(id: \$id) { id title } }",
  "variables": {"id": "cG9zdDox"},
  "extensions": {
    "persistedQueryNonce": "NONCE_PLACEHOLDER",
    "persistedQueryHash": "QUERY_HASH_PLACEHOLDER",
    "persistedVariablesHash": "VARIABLES_HASH_PLACEHOLDER"
  }
}
JSON

# Replace placeholders
sed -i '' "s/NONCE_PLACEHOLDER/$NONCE/g" /tmp/post_vars.json
sed -i '' "s/QUERY_HASH_PLACEHOLDER/$QUERY_HASH/g" /tmp/post_vars.json
sed -i '' "s/VARIABLES_HASH_PLACEHOLDER/$VARIABLES_HASH/g" /tmp/post_vars.json

POST_RESPONSE=$(curl -s -X POST "http://localhost:8888/graphql" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d @/tmp/post_vars.json)

echo "$POST_RESPONSE" | jq '.extensions | {persistedQueryUrl, queryAnalyzer: .queryAnalyzer.keys}'

PERSISTED_URL=$(echo "$POST_RESPONSE" | jq -r '.extensions.persistedQueryUrl // empty')

if [ -z "$PERSISTED_URL" ]; then
  echo "ERROR: Query was not persisted"
  exit 1
fi

echo ""
echo "✓ Query persisted successfully!"
echo "Persisted URL: $PERSISTED_URL"
echo ""

# Step 4: Test GET to persisted URL
echo "=== Step 4: GET persisted URL (should return cached data) ==="
GET_CACHED=$(curl -s -X GET "http://localhost:8888${PERSISTED_URL}" \
  -H "Accept: application/json")

echo "$GET_CACHED" | jq '.data.post | {id, title}'

echo ""
echo "✓ Complete PQC flow with variables test successful!"
```

Save these scripts as `test-pqc-flow.sh` and `test-pqc-flow-variables.sh`, make them executable (`chmod +x test-pqc-flow*.sh`), and run them to test the complete flows.

## Test 3: Cache Invalidation - Purge Handler

This test verifies that the PQC plugin correctly identifies and logs URLs that should be purged when WPGraphQL Smart Cache emits purge events.

### Prerequisites

1. **Enable WP_DEBUG logging:**
   Add to `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   ```

2. **Optional: Prevent database entry deletion during testing:**
   Add to your theme's `functions.php` or a plugin:
   ```php
   // Prevent deletion of index entries for testing
   add_filter( 'wpgraphql_pqc_delete_entries_on_purge', '__return_false' );
   ```
   This allows you to test the purge logic multiple times without losing database entries.

### Step 1: Create a Post and Persist a Query

First, create a post and persist a query that returns that post:

```bash
# Create a post via WP-CLI
POST_ID=$(npm run wp-env -- run cli -- wp post create --post_title="Test Post for Purge" --post_status=publish --porcelain)

# Get the post's GraphQL ID
POST_GRAPHQL_ID=$(npm run wp-env -- run cli -- wp eval "echo \GraphQLRelay\Relay::toGlobalId('post', $POST_ID);")

echo "Post ID: $POST_ID"
echo "Post GraphQL ID: $POST_GRAPHQL_ID"
```

Now persist a query that returns this post (follow Test 1 steps, but use this query):

```graphql
query GetPost {
  post(id: "cG9zdDox") {
    id
    title
  }
}
```

Replace `cG9zdDox` with the actual GraphQL ID from above (base64 encoded).

Note the `persistedQueryUrl` from the POST response.

### Step 2: Verify Database Entry

Check that the entries exist and have cache keys:

```bash
# Check url_keys entries
npm run wp-env -- run cli -- wp db query "
  SELECT url, query_hash, cache_key 
  FROM wp_wpgraphql_pqc_url_keys 
  WHERE url LIKE '%graphql/persisted%'
  LIMIT 10;
"
```

**Expected Result:**
- You should see entries with cache keys like `post:1` (where `1` is the post ID) or the GraphQL global ID
- The `url` column should contain the persisted query URL

### Step 3: Trigger a Purge Event

Update the post (this will trigger Smart Cache to emit a `graphql_purge` action):

```bash
# Update the post title
npm run wp-env -- run cli -- wp post update $POST_ID --post_title="Updated Test Post"
```

Or update it via the WordPress admin at `http://localhost:8888/wp-admin`.

### Step 4: Check the Logs

**Check error log for purge events:**

```bash
# View the last 50 lines of the debug log
tail -n 50 /path/to/wp-content/debug.log | grep "WPGraphQL PQC"
```

Or if using `wp-env`:

```bash
# Access the container and check logs
npm run wp-env -- run cli -- tail -n 50 /var/www/html/wp-content/debug.log | grep "WPGraphQL PQC"
```

**Expected Log Output:**

```
[WPGraphQL PQC] Purge Event: key="post:cG9zdDox" event="post_updated" hostname="localhost:8888" urls_count=1
[WPGraphQL PQC]   → Would purge URL: /graphql/persisted/{queryHash}/variables/{variablesHash}
[WPGraphQL PQC] NullAdapter: Would purge URL: /graphql/persisted/{queryHash}/variables/{variablesHash}
```

**What to verify:**
- The cache key matches the post that was updated (e.g., `post:cG9zdDox`)
- The event name is correct (e.g., `post_updated`, `transition_post_status`)
- The URL(s) listed are the persisted query URLs that should be purged
- The URL count matches the number of persisted queries that reference this post

### Step 5: Test Different Purge Events

Test various events that trigger purges:

**Publish a new post (triggers `list:post` purge):**
```bash
npm run wp-env -- run cli -- wp post create --post_title="New Post" --post_status=publish
```

**Update post meta:**
```bash
npm run wp-env -- run cli -- wp post meta update $POST_ID test_meta "test value"
```

**Delete a post:**
```bash
npm run wp-env -- run cli -- wp post delete $POST_ID --force
```

**Expected Results:**
- Each event should log different cache keys:
  - Publishing: `list:post`
  - Updating: `post:{id}` and `skipped:post`
  - Deleting: `post:{id}` and `skipped:post`
- The logged URLs should match persisted queries that reference the affected content

### Step 6: Verify Database Entries (if deletion enabled)

If you did NOT use the filter to prevent deletion, check that entries were removed:

```bash
# Check that ALL entries for the URL are deleted (not just the specific cache key)
npm run wp-env -- run cli -- wp db query "
  SELECT * FROM wp_wpgraphql_pqc_url_keys 
  WHERE url = '/graphql/persisted/{your-query-hash}'
  LIMIT 10;
"
```

**Expected Result:**
- **ALL entries for the URL should be deleted** (not just entries with the purged cache key)
- When a cache key is purged, the entire cached response at that URL is invalid
- All cache key associations for that URL are removed to ensure complete cleanup
- The URL will be re-indexed with fresh cache keys on the next request

### Troubleshooting

**No purge events logged:**
- Ensure `WP_DEBUG` is enabled
- Check that WPGraphQL Smart Cache is active and tracking events
- Verify the query was actually persisted (check database)

**Wrong URLs being purged:**
- Check that cache keys in the database match what Smart Cache is emitting
- Verify the query was executed as a public request (authenticated requests may have different cache keys)

**No URLs found for cache key:**
- This is normal if no persisted queries reference that content
- The log will show `urls_count=0` and `No URLs found for this cache key`

## Test 4: Edge Cases

### Test 4.1: Authenticated Requests

Authenticated requests can use persisted query URLs, but by default they cannot persist new queries (unless filtered).

**To test authenticated GET requests:**
1. First persist a query as a public user (following Test 1)
2. Then make a GET request while logged in:

```bash
curl -X GET http://localhost:8888/graphql/persisted/{hash} \
  -H "Cookie: wordpress_logged_in_xxx=..."
```

**Expected Result:**
- Query executes as the authenticated user (they see their own data)
- Response headers include `Cache-Control: no-store` (not cacheable)

**To test authenticated POST requests (with persistence):**
By default, authenticated POST requests will not store queries. To allow it, use the filter:

```php
add_filter( 'wpgraphql_pqc_allow_authenticated', '__return_true' );
```

Then make a POST request with nonce (same flow as Test 1):

```bash
# First GET to receive nonce
curl -X GET http://localhost:8888/graphql/persisted/{hash}

# Then POST with nonce
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "query": "query GetPosts { posts { nodes { id title } } }",
    "extensions": {
      "persistedQueryNonce": "nonce_from_get_404",
      "persistedQueryHash": "computed_hash",
      "persistedVariablesHash": ""
    }
  }'
```

**Expected Result:**
- Query executes successfully
- Entry is stored in database (if filter allows authenticated persistence)
- Response includes `extensions.persistedQueryUrl`

### Test 4.2: Mutation Requests

```bash
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "mutation { createPost(input: {title: \"Test\"}) { post { id } } }"
  }'
```

**Expected Result:**
- Mutation executes
- **No entry stored** (only queries are stored, not mutations)
- **No `persistedQueryUrl` in response**

### Test 4.3: Query Without Cache Keys

Some queries might not generate cache keys (e.g., introspection queries). These should be skipped.

## Test 5: Rewrite Rules

### Verify Rewrite Rules are Registered

```bash
wp rewrite list | grep graphql
```

Or check in WordPress admin: Settings → Permalinks

**Expected Result:**
- Rewrite rules for `/graphql/persisted/{hash}` and `/graphql/persisted/{hash}/variables/{hash}` are present

### Test URL Structure

The default base path is `graphql/persisted/`. You can filter it:

```php
add_filter( 'wpgraphql_pqc_url_base', function() {
    return 'api/persisted/';
} );
```

After filtering, flush rewrite rules again.

## Debugging Tips

### Enable Debug Logging

Add to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Check `wp-content/debug.log` for purge events (if using NullAdapter).

### Check Query Analyzer Output

You can inspect what cache keys are generated:

```php
add_action( 'graphql_return_response', function( $response, $original_response, $schema, $operation, $query, $variables, $request ) {
    if ( $request ) {
        $analyzer = $request->get_query_analyzer();
        $keys = $analyzer->get_graphql_keys();
        error_log( 'Cache keys: ' . print_r( $keys, true ) );
    }
}, 10, 7 );
```

### Database Queries

Use these SQL queries to inspect the normalized database structure:

```sql
-- Count total documents (unique queries)
SELECT COUNT(*) FROM wp_wpgraphql_pqc_documents;

-- Count total URL-key mappings
SELECT COUNT(*) FROM wp_wpgraphql_pqc_url_keys;

-- View all documents with preview
SELECT query_hash, LEFT(query_document, 100) as query_preview, created_at 
FROM wp_wpgraphql_pqc_documents 
ORDER BY created_at DESC;

-- View all URL-key mappings
SELECT url, query_hash, variables_hash, cache_key, created_at 
FROM wp_wpgraphql_pqc_url_keys 
ORDER BY created_at DESC;

-- Join documents and url_keys to see full query with mappings
SELECT d.query_hash, LEFT(d.query_document, 100) as query_preview, uk.url, uk.cache_key
FROM wp_wpgraphql_pqc_documents d
INNER JOIN wp_wpgraphql_pqc_url_keys uk ON d.query_hash = uk.query_hash
ORDER BY uk.created_at DESC;

-- Find entries for a specific cache key
SELECT url, cache_key 
FROM wp_wpgraphql_pqc_url_keys 
WHERE cache_key LIKE '%list:post%';

-- Find entries for a specific URL
SELECT * FROM wp_wpgraphql_pqc_url_keys WHERE url = '/graphql/persisted/...';

-- Find the document for a specific query hash
SELECT * FROM wp_wpgraphql_pqc_documents WHERE query_hash = '...';
```

## Common Issues

### Issue: POST requests not storing queries

**Symptom**: Query executes successfully but no entry appears in database.

**Possible Causes**:
1. **Nonce not provided**: Nonce is required by default. Make sure you:
   - First GET the persisted URL to receive a nonce
   - Include the nonce in POST request extensions
   - Include computed hashes in extensions

2. **Nonce expired**: Nonces expire after 5 minutes. Get a fresh nonce if needed.

3. **Nonce already used**: Each nonce can only be used once. If you retry the same POST, get a new nonce.

4. **Hash mismatch**: Client-provided hashes must match server-computed hashes. Ensure you're using the same hashing algorithm (SHA-256, normalized query, canonicalized variables).

5. **Authenticated request**: By default, authenticated requests don't persist. Use the filter to allow it:
   ```php
   add_filter( 'wpgraphql_pqc_allow_authenticated', '__return_true' );
   ```

**Solution**: Follow the proper PQC flow:
1. GET `/graphql/persisted/{hash}` → Receive nonce
2. POST with query + nonce + hashes in extensions → Query stored

### Issue: Rewrite rules not working

**Solution:** Flush rewrite rules:
- WordPress Admin: Settings → Permalinks → Save Changes
- WP-CLI: `wp rewrite flush`

### Issue: No entries in database after POST

**Check:**
1. Is the request unauthenticated? (Logged-in users are skipped)
2. Is it a query (not a mutation)?
3. Does the query have cache keys? (Check Query Analyzer output)
4. Are there any PHP errors in the debug log?

### Issue: GET request returns 404

**Check:**
1. Are rewrite rules flushed?
2. Is the query hash correct? (Must be 64-character hex string)
3. Does the entry exist in the database?

### Issue: Purge not working

**Check:**
1. Is Smart Cache active and tracking events?
2. Are cache keys matching? (Check what keys are stored vs. what keys are being purged)
3. Is the purge adapter configured correctly?

## Next Steps

Once manual testing is complete, consider:
1. Writing automated tests (WPUnit, Acceptance, Functional)
2. Testing on WordPress VIP environment
3. Testing with Redis store adapter (when implemented)
4. Performance testing with many persisted queries
