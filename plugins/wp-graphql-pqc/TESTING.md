# Manual Testing Guide for WPGraphQL Persisted Query Cache

This guide walks you through manually testing the plugin's core functionality.

## Prerequisites

1. **WordPress environment running** (via `wp-env` or similar)
2. **WPGraphQL** and **WPGraphQL Smart Cache** plugins activated
3. **WPGraphQL Persisted Query Cache** plugin activated
4. **Rewrite rules flushed** (if you just activated the plugin, visit Settings → Permalinks and click "Save Changes", or run `wp rewrite flush` via WP-CLI)

## Test 1: POST Request - Store Query in Index

### Step 1: Make a POST request to GraphQL

Use curl, Postman, or any HTTP client to make a POST request to your GraphQL endpoint:

```bash
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetPosts { posts { nodes { id title } } }"
  }'
```

**Expected Result:**
- Query executes successfully
- Response includes `extensions.persistedQueryUrl` with a URL like `/graphql/persisted/{hash}`

### Step 2: Verify Database Entry

Check the `wp_wpgraphql_pqc_url_keys` table in your database (using TablePlus, phpMyAdmin, etc.):

```sql
SELECT * FROM wp_wpgraphql_pqc_url_keys ORDER BY created_at DESC LIMIT 1;
```

**Expected Result:**
- A new row with:
  - `url_hash`: SHA-256 hash of the URL
  - `url`: The persisted query URL (e.g., `/graphql/persisted/abc123...`)
  - `query_hash`: SHA-256 hash of the query
  - `variables_hash`: Empty string (no variables)
  - `query_document`: The original query string
  - `variables`: Empty string
  - `cache_key`: One or more cache keys (space-separated, e.g., `list:post`)
  - `created_at`: Current timestamp

### Step 3: Test with Variables

Make a POST request with variables:

```bash
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetPost($id: ID!) { post(id: $id) { id title } }",
    "variables": { "id": "cG9zdDox" }
  }'
```

**Expected Result:**
- Response includes `extensions.persistedQueryUrl` with a URL like `/graphql/persisted/{queryHash}/variables/{variablesHash}`
- Database entry includes both `query_hash` and `variables_hash`

## Test 2: GET Request - Retrieve and Execute Persisted Query

### Step 1: Get the Persisted URL from Previous Test

From the POST response, copy the `extensions.persistedQueryUrl` value (e.g., `/graphql/persisted/abc123...`).

### Step 2: Make a GET Request to the Persisted URL

```bash
curl -X GET http://localhost:8888/graphql/persisted/{queryHash}
```

Replace `{queryHash}` with the actual hash from the URL.

**Expected Result:**
- Query is retrieved from the database
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

Check that the entry exists and has a cache key like `post:1` or similar:

```sql
SELECT url, cache_key FROM wp_wpgraphql_pqc_url_keys WHERE url LIKE '%graphql/persisted%';
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

Make a POST request while logged in:

```bash
# First, get a session cookie by logging in via browser
# Then include the cookie in your curl request
curl -X POST http://localhost:8888/graphql \
  -H "Content-Type: application/json" \
  -H "Cookie: wordpress_logged_in_xxx=..." \
  -d '{
    "query": "query GetPosts { posts { nodes { id title } } }"
  }'
```

**Expected Result:**
- Query executes normally
- **No entry stored in database** (authenticated requests are skipped)
- **No `persistedQueryUrl` in response**

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

Use these SQL queries to inspect the index:

```sql
-- Count total entries
SELECT COUNT(*) FROM wp_wpgraphql_pqc_url_keys;

-- View all entries
SELECT url, query_hash, variables_hash, cache_key, created_at 
FROM wp_wpgraphql_pqc_url_keys 
ORDER BY created_at DESC;

-- Find entries for a specific cache key
SELECT url, cache_key 
FROM wp_wpgraphql_pqc_url_keys 
WHERE cache_key LIKE '%list:post%';

-- Find entries for a specific URL
SELECT * FROM wp_wpgraphql_pqc_url_keys WHERE url = '/graphql/persisted/...';
```

## Common Issues

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
