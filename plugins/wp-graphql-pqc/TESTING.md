# Manual Testing Guide for WPGraphQL Persisted Query Cache

This guide walks you through manually testing the plugin's core functionality.

## Prerequisites

1. **WordPress environment running** (via `wp-env` or similar)
2. **WPGraphQL** and **WPGraphQL Smart Cache** plugins activated
3. **WPGraphQL Persisted Query Cache** plugin activated
4. **Rewrite rules flushed** (if you just activated the plugin, visit Settings → Permalinks and click "Save Changes", or run `wp rewrite flush` via WP-CLI)

## Important: Nonce-Based PQC Flow

**Breaking Change**: As of v0.1.0-beta.1, the plugin requires a nonce-based flow for security:

1. **GET** `/graphql/persisted/{hash}` → Returns 404 with nonce in extensions
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

## Test 1: PQC Flow - Store Query in Index (with Nonce)

The PQC flow requires a two-step process: first GET the persisted URL (which returns 404 with nonce), then POST with the nonce.

### Step 1: Compute Query Hash (Client-Side)

First, you need to compute the query hash. For testing, you can use the same hashing logic the server uses, or you can skip to Step 2 and let the server compute it.

**Note**: In a real client implementation, you would compute the hash client-side using SHA-256 of the normalized query document.

### Step 2: GET the Persisted URL (Get Nonce)

Make a GET request to the persisted URL (this will 404 and return a nonce):

```bash
# First, compute the query hash (example - you'd do this client-side)
# For this test, we'll use a placeholder hash. In practice, compute it using SHA-256
# of the normalized query: "query GetPosts { posts { nodes { id title } } }"

curl -X GET http://localhost:8888/graphql/persisted/PLACEHOLDER_HASH
```

**Expected Result:**
- HTTP 404 response
- JSON response with:
  - `errors`: Array with error message
  - `extensions.persistedQueryNonce`: A nonce token (64 character hex string)

**Example Response:**
```json
{
  "errors": [
    {
      "message": "Persisted query not found"
    }
  ],
  "extensions": {
    "persistedQueryNonce": "abc123def456..."
  }
}
```

### Step 3: POST with Nonce and Hashes

Now make a POST request with the nonce and computed hashes in the extensions:

```bash
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetPosts { posts { nodes { id title } } }",
    "extensions": {
      "persistedQueryNonce": "abc123def456...",
      "persistedQueryHash": "computed_query_hash_here",
      "persistedVariablesHash": ""
    }
  }'
```

**Expected Result:**
- Query executes successfully
- Response includes `extensions.persistedQueryUrl` with a URL like `/graphql/persisted/{hash}`
- Query is stored in the database

**Note**: If you don't provide the nonce or hashes, the query will execute but **will not be stored** (nonce is required by default for security).

### Step 2: Verify Database Entry

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
  - `query_hash`: SHA-256 hash of the query (primary key)
  - `query_document`: The original query string
  - `created_at`: Current timestamp

- **In `wp_wpgraphql_pqc_url_keys`**: One or more rows (one per cache key) with:
  - `url_hash`: SHA-256 hash of the URL
  - `url`: The persisted query URL (e.g., `/graphql/persisted/abc123...`)
  - `query_hash`: SHA-256 hash of the query (references documents table)
  - `variables_hash`: Empty string (no variables)
  - `variables`: Empty string
  - `cache_key`: A single cache key (e.g., `list:post`)
  - `created_at`: Current timestamp

### Step 4: Test with Variables

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

### Step 1: Get the Persisted URL from Previous Test

From the POST response, copy the `extensions.persistedQueryUrl` value (e.g., `/graphql/persisted/abc123...`).

### Step 2: Make a GET Request to the Persisted URL

```bash
curl -X GET http://localhost:8888/graphql/persisted/{queryHash}
```

Replace `{queryHash}` with the actual hash from the URL.

**Expected Result:**
- Query is retrieved from the database (JOIN between documents and url_keys tables)
- Query is re-executed
- Same response as the original POST request
- Response headers include `Content-Type: application/json`

### Step 3: Test with Variables

If you have a persisted query with variables, use the full URL:

```bash
curl -X GET http://localhost:8888/graphql/persisted/{queryHash}/variables/{variablesHash}
```

**Expected Result:**
- Query and variables are retrieved
- Query is re-executed with the variables
- Same response as the original POST request

### Step 4: Test 404 for Non-Existent Query

```bash
curl -X GET http://localhost:8888/graphql/persisted/0000000000000000000000000000000000000000000000000000000000000000
```

**Expected Result:**
- HTTP 404 status
- Empty response

## Test 3: Cache Invalidation

### Step 1: Create a Query that Returns Specific Content

First, create a post and make a query for it:

```bash
# Create a post (via WP-CLI or admin)
wp post create --post_title="Test Post" --post_status=publish

# Query for that specific post
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetPost { post(id: \"cG9zdDox\") { id title } }"
  }'
```

Note the `persistedQueryUrl` from the response.

### Step 2: Verify Database Entry

Check that the entries exist and have cache keys like `post:1` or similar:

```sql
-- Check url_keys entries
SELECT url, query_hash, cache_key FROM wp_wpgraphql_pqc_url_keys WHERE url LIKE '%graphql/persisted%';

-- Check the corresponding document
SELECT query_hash, LEFT(query_document, 100) as query_preview FROM wp_wpgraphql_pqc_documents;
```

### Step 3: Trigger a Purge Event

Update the post (this will trigger Smart Cache to emit a `graphql_purge` action):

```bash
wp post update 1 --post_title="Updated Test Post"
```

Or update it via the WordPress admin.

### Step 4: Verify Purge Handler Executed

**If using NullAdapter (default in development):**
- Check your error log (if `WP_DEBUG` is true, you should see log entries)
- The purge will be logged but not actually executed

**If using VIPAdapter (on WordPress VIP):**
- The URL will be purged via `wpcom_vip_purge_edge_cache_for_url()`

### Step 5: Verify Index Entry Deleted

Check the database:

```sql
SELECT * FROM wp_wpgraphql_pqc_url_keys WHERE cache_key LIKE '%post:1%';
```

**Expected Result:**
- The entry should be deleted (or at least the cache_key should no longer match)

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
