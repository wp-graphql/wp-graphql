#!/bin/bash

# Comprehensive PQU Test Script
# Tests queries with shared cache keys and different cache keys
# Then verifies purge behavior when post 9 is updated

set -e

# Change to monorepo root (where wp-env script is located)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Go up from plugins/wp-graphql-pqu to root
MONOREPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$MONOREPO_ROOT"

# Verify we're in the right place
if [ ! -f "package.json" ] || ! grep -q '"wp-env"' package.json 2>/dev/null; then
    echo "ERROR: Could not find monorepo root with wp-env script"
    echo "Expected to find package.json with wp-env script at: $MONOREPO_ROOT"
    exit 1
fi

BASE_URL="http://localhost:8888"
GRAPHQL_URL="${BASE_URL}/graphql"

echo "=========================================="
echo "WPGraphQL Persisted Query URLs — comprehensive test"
echo "=========================================="
echo "Running from: $(pwd)"
echo ""

# PQU only stores when Smart Cache / Query Analyzer produces X-GraphQL-Keys (cache key list).
# Without that, PostHandler skips storage. Force the setting on (Smart Cache normally does this).
echo "=== Prerequisites: Query Analyzer + PQU tables ==="
npm run wp-env -- run cli -- wp eval '
$opts = get_option( "graphql_general_settings", [] );
if ( ! is_array( $opts ) ) {
	$opts = [];
}
$opts["query_analyzer_enabled"] = "on";
update_option( "graphql_general_settings", $opts );
echo "query_analyzer_enabled=on\n";
' 2>/dev/null | grep -v '^[[:space:]]*>' | grep -v '^[[:space:]]*$' || true

npm run wp-env -- run cli -- wp eval '
require_once ABSPATH . "wp-admin/includes/plugin.php";
if ( ! function_exists( "is_plugin_active" ) ) {
	echo "pqu_check=skip\n";
	exit;
}
$active = is_plugin_active( "wp-graphql-pqu/wp-graphql-pqu.php" );
$schema_ok = class_exists( "\\WPGraphQL\\PQU\\Database\\Schema" ) && \WPGraphQL\PQU\Database\Schema::table_exists();
echo "pqu_active=" . ( $active ? "yes" : "no" ) . " tables=" . ( $schema_ok ? "yes" : "no" ) . "\n";
if ( ! $active ) {
	echo "WARNING: Activate wp-graphql-pqu in wp-admin or: wp plugin activate wp-graphql-pqu\n";
}
if ( ! $schema_ok && class_exists( "\\WPGraphQL\\PQU\\Database\\Schema" ) ) {
	\WPGraphQL\PQU\Database\Schema::create_table();
	echo "tables_created=1\n";
}
' 2>/dev/null | grep -v '^[[:space:]]*>' | grep -v '^[[:space:]]*$' || true

# Persisted URLs only work with pretty permalinks + registered rewrite rules.
echo "Flushing rewrite rules (required for GET /graphql/persisted/{hash})..."
npm run wp-env -- run cli -- wp rewrite flush 2>/dev/null | grep -v '^[[:space:]]*>' | grep -v '^[[:space:]]*$' || true
echo "rewrite_flush=done"
echo "Checking persisted-query rewrite rules (expect lines containing graphql/persisted):"
npm run wp-env -- run cli -- wp rewrite list 2>/dev/null | grep -F 'graphql/persisted' || echo "WARNING: No graphql/persisted rules found — PQU GET URLs will not work."
echo ""

# Step 0: Ensure WP_DEBUG is enabled for logging
echo "=== Step 0: Ensuring WP_DEBUG is enabled ==="
npm run wp-env -- run cli -- wp eval "
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    // Try to add WP_DEBUG to wp-config.php
    \$wp_config_path = ABSPATH . 'wp-config.php';
    if (file_exists(\$wp_config_path)) {
        \$config = file_get_contents(\$wp_config_path);
        if (strpos(\$config, \"define('WP_DEBUG'\") === false && strpos(\$config, 'define(\"WP_DEBUG\"') === false) {
            // Add before 'That's all, stop editing!'
            \$config = str_replace(
                '/* That\\'s all, stop editing! Happy blogging. */',
                \"define('WP_DEBUG', true);\\ndefine('WP_DEBUG_LOG', true);\\n/* That's all, stop editing! Happy blogging. */\",
                \$config
            );
            file_put_contents(\$wp_config_path, \$config);
            echo 'WP_DEBUG enabled in wp-config.php\\n';
        } else {
            echo 'WP_DEBUG already defined in wp-config.php\\n';
        }
    }
}
// Force enable for this session
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
    echo 'WP_DEBUG enabled for this session\\n';
} else {
    echo 'WP_DEBUG is already enabled\\n';
}
" 2>/dev/null || echo "Note: Could not modify wp-config.php, but WP_DEBUG should be enabled via wp-env config"
echo ""

# Parse JSON that may have junk before the first "{" (e.g. PHP Notices on stdout).
# Usage: echo "$raw" | pqu_json_get "extensions.persistedQueryNonce"
pqu_json_get() {
    local dot_path="$1"
    PQU_JSON_PATH="$dot_path" python3 -c "
import json, sys, os
raw = sys.stdin.read()
path = os.environ.get('PQU_JSON_PATH', '').split('.')
path = [p for p in path if p]
needle = '{'
idx = 0
val = ''
while True:
    i = raw.find(needle, idx)
    if i < 0:
        break
    try:
        obj = json.loads(raw[i:])
        cur = obj
        for part in path:
            if isinstance(cur, dict) and part in cur:
                cur = cur[part]
            else:
                cur = None
                break
        if cur is not None and not isinstance(cur, (dict, list)):
            val = str(cur)
        elif cur is not None:
            val = json.dumps(cur, separators=(',', ':'))
        break
    except json.JSONDecodeError:
        idx = i + 1
print(val)
"
}

# Run wp eval that echoes a single integer; strip PHP notices and wp-env noise from stdout.
pqu_wp_eval_int() {
	local php="$1"
	local out
	out=$(npm run wp-env -- run cli -- wp eval "$php" 2>/dev/null) || true
	local n
	n=$(echo "$out" | grep -E '^[0-9]+$' | tail -1)
	echo "${n:-0}"
}

# Helper function to compute query hash
compute_query_hash() {
    local query="$1"
    local out
    out=$(npm run wp-env -- run cli -- wp eval "
    require_once 'wp-content/plugins/wp-graphql-pqu/vendor/autoload.php';
    use WPGraphQL\PQU\Utils\Hasher;
    echo Hasher::hash_query('$query');
    " 2>/dev/null)
    # wp-env / npm may add extra lines; take last 64-char hex line.
    echo "$out" | grep -oE '[a-f0-9]{64}' | tail -1
}

# Helper function to compute variables hash
compute_variables_hash() {
    local vars_json="$1"
    local out
    out=$(npm run wp-env -- run cli -- wp eval "
    require_once 'wp-content/plugins/wp-graphql-pqu/vendor/autoload.php';
    use WPGraphQL\PQU\Utils\Hasher;
    \$vars = json_decode('$vars_json', true);
    echo Hasher::hash_variables(\$vars ?: []);
    " 2>/dev/null)
    echo "$out" | grep -oE '[a-f0-9]{64}' | tail -1
}

# Helper function to get nonce
get_nonce() {
    local query_hash="$1"
    local variables_hash="${2:-}"
    
    local url="${BASE_URL}/graphql/persisted/${query_hash}"
    if [ -n "$variables_hash" ]; then
        url="${url}/variables/${variables_hash}"
    fi
    
    local tmp
    tmp=$(mktemp)
    local http_code
    http_code=$(curl -sS --compressed -o "$tmp" -w "%{http_code}" -X GET "$url" \
        -H "Accept: application/json" \
        -H "User-Agent: wp-graphql-pqu-test/1" || echo "000")
    local body
    body=$(cat "$tmp" 2>/dev/null || true)
    rm -f "$tmp"
    
    local nonce
    nonce=$(echo "$body" | pqu_json_get "extensions.persistedQueryNonce")
    
    if [ -z "$nonce" ]; then
        echo "get_nonce: HTTP ${http_code} GET $url" >&2
        echo "Response preview (first 700 bytes):" >&2
        echo "${body:0:700}" >&2
    fi
    
    echo "$nonce"
}

# After a query is stored, GET /graphql/persisted/{hash} executes on the origin (warm path).
# Edge caches (Varnish, etc.) may still forward to WP; this asserts the warm response is valid GraphQL JSON.
verify_warm_persisted_get() {
    local persisted_path="$1"
    local label="$2"
    local url="${BASE_URL}${persisted_path}"

    local tmpd
    tmpd=$(mktemp -d)
    local hdr="${tmpd}/headers.txt"
    local body="${tmpd}/body.json"
    local http_code
    http_code=$(curl -sS --compressed -o "$body" -D "$hdr" -w "%{http_code}" -X GET "$url" \
        -H "Accept: application/json" \
        -H "User-Agent: wp-graphql-pqu-test/warm-get" || echo "000")

    local body_str
    body_str=$(cat "$body" 2>/dev/null || true)
    local cc
    cc=$(grep -i '^[Cc]ache-[Cc]ontrol:' "$hdr" 2>/dev/null | tail -1 | tr -d '\r' || true)
    rm -rf "$tmpd"

    if [ "$http_code" != "200" ]; then
        echo "ERROR (warm GET $label): expected HTTP 200, got $http_code for $url" >&2
        echo "${body_str:0:800}" >&2
        return 1
    fi

    if echo "$body_str" | grep -q 'PERSISTED_QUERY_NOT_FOUND'; then
        echo "ERROR (warm GET $label): got cold-miss response; index may be empty or hash mismatch: $url" >&2
        return 1
    fi

    local data_json
    data_json=$(echo "$body_str" | pqu_json_get "data")
    if [ -z "$data_json" ] || [ "$data_json" = "null" ]; then
        echo "ERROR (warm GET $label): expected JSON with non-null data; URL: $url" >&2
        echo "Preview: ${body_str:0:800}" >&2
        return 1
    fi

    local warm_nonce
    warm_nonce=$(echo "$body_str" | pqu_json_get "extensions.persistedQueryNonce")
    if [ -n "$warm_nonce" ]; then
        echo "ERROR (warm GET $label): did not expect persistedQueryNonce on warm path (got one): $url" >&2
        return 1
    fi

    echo "✓ Warm GET OK: $label" >&2
    echo "  URL: $url" >&2
    if [ -n "$cc" ]; then
        echo "  $cc" >&2
    else
        echo "  (no Cache-Control header — origin may omit it in some setups)" >&2
    fi

    return 0
}

# Helper function to persist a query
# Logs go to stderr; only the persisted URL is printed to stdout (for VAR=$(persist_query ...) assignments).
persist_query() {
    local query="$1"
    local variables_json="${2:-}"
    local description="$3"
    
    echo "" >&2
    echo "=== Persisting: $description ===" >&2
    echo "Query: $query" >&2
    
    # Compute hashes
    local query_hash=$(compute_query_hash "$query")
    local variables_hash=""
    
    if [ -n "$variables_json" ]; then
        variables_hash=$(compute_variables_hash "$variables_json")
        echo "Variables: $variables_json" >&2
    fi
    
    echo "Query Hash: $query_hash" >&2
    if [ -n "$variables_hash" ]; then
        echo "Variables Hash: $variables_hash" >&2
    fi
    
    if [ -z "$query_hash" ] || [ "${#query_hash}" -ne 64 ]; then
        echo "ERROR: Could not compute a 64-char query hash (wp-env / Hasher output unexpected)." >&2
        echo "Re-run: npm run wp-env -- run cli -- wp eval '...Hasher::hash_query...' and check output." >&2
        return 1
    fi
    
    # Get nonce
    local nonce=$(get_nonce "$query_hash" "$variables_hash")
    if [ -z "$nonce" ]; then
        echo "ERROR: Failed to get nonce (GET ${BASE_URL}/graphql/persisted/<hash> must return JSON with extensions.persistedQueryNonce)." >&2
        echo "Check: wp rewrite flush; plugin active; URL reachable from this machine." >&2
        return 1
    fi
    echo "Nonce: $nonce" >&2
    
    # Build POST data
    local post_data
    if [ -n "$variables_json" ]; then
        post_data=$(jq -n \
            --arg query "$query" \
            --argjson variables "$variables_json" \
            --arg nonce "$nonce" \
            --arg query_hash "$query_hash" \
            --arg variables_hash "$variables_hash" \
            '{
                query: $query,
                variables: $variables,
                extensions: {
                    persistedQueryNonce: $nonce,
                    persistedQueryHash: $query_hash,
                    persistedVariablesHash: $variables_hash
                }
            }')
    else
        post_data=$(jq -n \
            --arg query "$query" \
            --arg nonce "$nonce" \
            --arg query_hash "$query_hash" \
            '{
                query: $query,
                extensions: {
                    persistedQueryNonce: $nonce,
                    persistedQueryHash: $query_hash,
                    persistedVariablesHash: ""
                }
            }')
    fi
    
    # POST the query
    local response
    response=$(curl -sS --compressed -X POST "$GRAPHQL_URL" \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -d "$post_data" || true)
    
    local gql_errors
    gql_errors=$(echo "$response" | pqu_json_get "errors")
    if [ -n "$gql_errors" ] && [ "$gql_errors" != "null" ] && [ "$gql_errors" != "[]" ]; then
        echo "GraphQL response included errors:" >&2
        echo "$gql_errors" >&2
    fi
    
    local persisted_url
    persisted_url=$(echo "$response" | pqu_json_get "extensions.persistedQueryUrl")
    local cache_keys
    cache_keys=$(echo "$response" | pqu_json_get "extensions.queryAnalyzer.keys")
    
    if [ -z "$persisted_url" ]; then
        echo "ERROR: extensions.persistedQueryUrl missing — PQU did not index this request." >&2
        echo "Common causes: Query Analyzer produced no cache keys (enable query_analyzer_enabled), nonce/hash mismatch, or grant_mode blocks public document creation." >&2
        echo "--- Raw response (first 2000 chars) ---" >&2
        echo "${response:0:2000}" >&2
        return 1
    fi
    
    echo "✓ Query persisted successfully!" >&2
    echo "Persisted URL: $persisted_url" >&2
    echo "Cache Keys (extensions.queryAnalyzer.keys): ${cache_keys:-<empty — turn on WPGraphQL Debug to see in JSON>}" >&2
    
    # Return the persisted URL for later use (stdout only)
    echo "$persisted_url"
}

# Step 1: Get or create post 9 and get its GraphQL ID
echo "=== Step 1: Getting Post 9 ==="
POST_ID=9
POST_EXISTS=$(npm run wp-env -- run cli -- wp post get $POST_ID --field=ID 2>/dev/null || echo "")

if [ -z "$POST_EXISTS" ]; then
    echo "Post 9 doesn't exist, creating it..."
    POST_ID=$(npm run wp-env -- run cli -- wp post create \
        --post_title="Test Post 9" \
        --post_content="This is test post 9 for PQU testing" \
        --post_status=publish \
        --porcelain)
    echo "Created post with ID: $POST_ID"
else
    echo "Post 9 exists with ID: $POST_ID"
fi

# Get GraphQL ID for post 9 (strip npm ">" status lines from stdout)
POST_GRAPHQL_ID=$(npm run wp-env -- run cli -- wp eval "
require_once 'wp-content/plugins/wp-graphql/vendor/autoload.php';
echo \GraphQLRelay\Relay::toGlobalId('post', $POST_ID);
" 2>/dev/null | grep -v '^[[:space:]]*>' | grep -v '^[[:space:]]*$' | tail -1 | tr -d '\r\n')

echo "Post Database ID: $POST_ID"
echo "Post GraphQL ID: $POST_GRAPHQL_ID"
echo ""

# Step 2: Persist queries that will share cache keys with post:9
echo "=========================================="
echo "Step 2: Persisting Queries with Shared Cache Keys"
echo "=========================================="
echo ""
echo "Clearing PQU index tables so GET /graphql/persisted/{hash} returns a nonce (cold miss)."
echo "If documents already exist, GET executes the query (warm) and has no persistedQueryNonce."
npm run wp-env -- run cli -- wp eval '
global $wpdb;
$p = $wpdb->prefix;
$wpdb->query( "TRUNCATE TABLE {$p}wpgraphql_pqu_key_urls" );
$wpdb->query( "TRUNCATE TABLE {$p}wpgraphql_pqu_urls" );
$wpdb->query( "TRUNCATE TABLE {$p}wpgraphql_pqu_cache_keys" );
$wpdb->query( "TRUNCATE TABLE {$p}wpgraphql_pqu_documents" );
echo "pqu_index_truncated\n";
' 2>/dev/null | grep -E 'pqu_index_truncated' || echo "WARNING: Could not truncate PQU tables (check wp-env)."
echo ""

# Query 1: Single post query for post:9
QUERY_POST_9='query GetPost9 { post(id: "'"$POST_GRAPHQL_ID"'") { id title content } }'
PERSISTED_URL_POST_9=$(persist_query "$QUERY_POST_9" "" "Single Post Query (post:9)")

# Query 2: List of posts (should include post:9 and generate list:post cache key)
QUERY_POSTS_LIST='query GetPosts { posts(first: 10) { nodes { id title } } }'
PERSISTED_URL_POSTS_LIST=$(persist_query "$QUERY_POSTS_LIST" "" "Posts List Query (includes post:9)")

echo ""
echo "=========================================="
echo "Step 3: Persisting Queries with Different Cache Keys"
echo "=========================================="

# Query 3: Tags query
QUERY_TAGS='query GetTags { tags(first: 10) { nodes { id name } } }'
PERSISTED_URL_TAGS=$(persist_query "$QUERY_TAGS" "" "Tags Query")

# Query 4: Categories query
QUERY_CATEGORIES='query GetCategories { categories(first: 10) { nodes { id name } } }'
PERSISTED_URL_CATEGORIES=$(persist_query "$QUERY_CATEGORIES" "" "Categories Query")

# Query 5: Users query
QUERY_USERS='query GetUsers { users(first: 10) { nodes { id name } } }'
PERSISTED_URL_USERS=$(persist_query "$QUERY_USERS" "" "Users Query")

# Query 6: Single user query (if we can get a user ID)
USER_ID=$(npm run wp-env -- run cli -- wp user list --field=ID --number=1 2>/dev/null | head -1)
if [ -n "$USER_ID" ]; then
    USER_GRAPHQL_ID=$(npm run wp-env -- run cli -- wp eval "
    require_once 'wp-content/plugins/wp-graphql/vendor/autoload.php';
    echo \GraphQLRelay\Relay::toGlobalId('user', $USER_ID);
    " 2>/dev/null | tail -1 | tr -d '\r\n')
    
    QUERY_USER='query GetUser { user(id: "'"$USER_GRAPHQL_ID"'") { id name } }'
    PERSISTED_URL_USER=$(persist_query "$QUERY_USER" "" "Single User Query")
fi

echo ""
echo "=========================================="
echo "Step 3b: Warm persisted GET (origin executes GraphQL)"
echo "=========================================="
echo "Edge caches may forward to WordPress. These checks assert warm GETs return HTTP 200, JSON data,"
echo "no PERSISTED_QUERY_NOT_FOUND, and no persistedQueryNonce (registration is cold-miss only)."
echo ""

verify_warm_persisted_get "$PERSISTED_URL_POST_9" "Single post (post:$POST_ID)" || exit 1
verify_warm_persisted_get "$PERSISTED_URL_POSTS_LIST" "Posts list" || exit 1
verify_warm_persisted_get "$PERSISTED_URL_TAGS" "Tags list" || exit 1
verify_warm_persisted_get "$PERSISTED_URL_CATEGORIES" "Categories list" || exit 1
verify_warm_persisted_get "$PERSISTED_URL_USERS" "Users list" || exit 1
if [ -n "${PERSISTED_URL_USER:-}" ]; then
    verify_warm_persisted_get "$PERSISTED_URL_USER" "Single user" || exit 1
fi

echo ""
echo "=========================================="
echo "Step 4: Verifying Database Entries"
echo "=========================================="

echo ""
echo "--- Documents Table (unique queries) ---"
npm run wp-env -- run cli -- wp db query "
SELECT 
    query_hash, 
    LEFT(query_document, 80) as query_preview, 
    created_at 
FROM wp_wpgraphql_pqu_documents 
ORDER BY created_at DESC;
"

echo ""
echo "--- Key map (urls + cache_keys + key_urls) ---"
npm run wp-env -- run cli -- wp db query "
SELECT u.url, k.cache_key, u.last_seen_at
FROM wp_wpgraphql_pqu_key_urls ku
INNER JOIN wp_wpgraphql_pqu_urls u ON u.id = ku.url_id
INNER JOIN wp_wpgraphql_pqu_cache_keys k ON k.id = ku.key_id
ORDER BY u.last_seen_at DESC;
"

echo ""
echo "--- Cache Keys Summary ---"
npm run wp-env -- run cli -- wp db query "
SELECT k.cache_key,
    COUNT(DISTINCT ku.url_id) AS url_count,
    GROUP_CONCAT(DISTINCT u.url SEPARATOR ' | ') AS urls
FROM wp_wpgraphql_pqu_key_urls ku
INNER JOIN wp_wpgraphql_pqu_cache_keys k ON k.id = ku.key_id
INNER JOIN wp_wpgraphql_pqu_urls u ON u.id = ku.url_id
GROUP BY k.cache_key
ORDER BY k.cache_key;
"

echo ""
echo "--- Key map rows for Post 9 / list:post (should see entries here) ---"
npm run wp-env -- run cli -- wp db query "
SELECT u.url, k.cache_key, u.query_hash, u.last_seen_at
FROM wp_wpgraphql_pqu_key_urls ku
INNER JOIN wp_wpgraphql_pqu_urls u ON u.id = ku.url_id
INNER JOIN wp_wpgraphql_pqu_cache_keys k ON k.id = ku.key_id
WHERE k.cache_key = '$POST_GRAPHQL_ID'
   OR k.cache_key LIKE '%post:$POST_ID%'
   OR k.cache_key LIKE '%post:$POST_GRAPHQL_ID%'
   OR k.cache_key = 'list:post'
ORDER BY u.last_seen_at DESC;
"

echo ""
echo "--- Key map for unrelated queries (tags, categories, users) ---"
npm run wp-env -- run cli -- wp db query "
SELECT u.url, k.cache_key, u.last_seen_at
FROM wp_wpgraphql_pqu_key_urls ku
INNER JOIN wp_wpgraphql_pqu_urls u ON u.id = ku.url_id
INNER JOIN wp_wpgraphql_pqu_cache_keys k ON k.id = ku.key_id
WHERE k.cache_key LIKE '%tag%'
   OR k.cache_key LIKE '%category%'
   OR k.cache_key LIKE '%user%'
ORDER BY u.last_seen_at DESC;
"

echo ""
echo "=========================================="
echo "STEP 1 COMPLETE: Query Persistence"
echo "=========================================="
echo ""
echo "✓ Queries have been persisted"
echo "✓ Database entries should be visible above"
echo ""
echo "WHAT TO VERIFY:"
echo "  1. Documents table should have unique query documents"
echo "  2. Key map tables should have entries mapping URLs to cache keys"
echo "  3. You should see entries for:"
echo "     - Relay global id for the post (e.g. $POST_GRAPHQL_ID), plus list:post for list queries"
echo "     - list:post"
echo "     - tag-related cache keys"
echo "     - category-related cache keys"
echo "     - user-related cache keys"
echo ""
echo "Press ENTER to continue to Step 2 (updating post 9 and verifying purge)..."
read -r
echo ""

echo ""
echo "=========================================="
echo "Step 5: Recording Initial State (Before Purge)"
echo "=========================================="
echo ""
echo "Recording counts before updating post 9:"

# Query Analyzer uses the Relay global id (e.g. cG9zdDo5) as the post cache key, not "post:9".
INITIAL_POST_9_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; \$gid = '$POST_GRAPHQL_ID'; echo (int) \$wpdb->get_var( \$wpdb->prepare( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key = %s\", \$gid ) );")

INITIAL_LIST_POST_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key = 'list:post'\" );")

INITIAL_TAGS_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key LIKE '%tag%'\" );")

INITIAL_CATEGORIES_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key LIKE '%category%'\" );")

INITIAL_USERS_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key LIKE '%user%'\" );")

echo "Initial counts:"
echo "  - post node ($POST_GRAPHQL_ID) url_key rows: $INITIAL_POST_9_COUNT"
echo "  - list:post entries: $INITIAL_LIST_POST_COUNT"
echo "  - tag entries: $INITIAL_TAGS_COUNT"
echo "  - category entries: $INITIAL_CATEGORIES_COUNT"
echo "  - user entries: $INITIAL_USERS_COUNT"
echo ""

# Step 6: Update Post 9 (triggers purge)
echo "=========================================="
echo "Step 6: Updating Post 9 (This will trigger purge events)"
echo "=========================================="
echo ""

# Try multiple possible debug log locations
DEBUG_LOG_PATHS=(
    "/var/www/html/wp-content/debug.log"
    "/var/www/html/wp-content/plugins/wp-graphql/tests/_output/debug.log"
    "/var/www/html/wp-content/debug.log"
)

DEBUG_LOG=""
for log_path in "${DEBUG_LOG_PATHS[@]}"; do
    if npm run wp-env -- run cli -- test -f "$log_path" 2>/dev/null; then
        DEBUG_LOG="$log_path"
        break
    fi
done

if [ -z "$DEBUG_LOG" ]; then
    # Try to find any debug.log file
    DEBUG_LOG=$(npm run wp-env -- run cli -- find /var/www/html/wp-content -name "debug.log" -type f 2>/dev/null | head -1 || echo "")
fi

if [ -n "$DEBUG_LOG" ]; then
    # wc output is noisy when run via npm/wp-env; read line count with stdin redirect.
    LOG_LINE_COUNT=$(npm run wp-env -- run cli -- bash -lc "wc -l < \"$DEBUG_LOG\" 2>/dev/null || echo 0" 2>/dev/null)
    LOG_LINE_COUNT=$(echo "$LOG_LINE_COUNT" | grep -oE '[0-9]+' | tail -1)
    LOG_LINE_COUNT=${LOG_LINE_COUNT:-0}
    echo "Found debug log at: $DEBUG_LOG"
    echo "Current debug log line count: $LOG_LINE_COUNT"
else
    echo "WARNING: Could not find debug.log file. Logs may not be written."
    echo "Make sure WP_DEBUG and WP_DEBUG_LOG are enabled in wp-config.php"
    DEBUG_LOG="/var/www/html/wp-content/debug.log"  # Use default path for commands
fi
echo ""

# Update post 9
echo "Updating post 9..."
npm run wp-env -- run cli -- wp post update $POST_ID --post_title="Updated Test Post 9 - $(date +%H:%M:%S)"
echo "✓ Post 9 updated"
echo ""

# Step 7: Check logs for purge events
echo "=========================================="
echo "Step 7: Checking Purge Event Logs"
echo "=========================================="
echo ""
echo "Looking for purge events in debug.log..."
echo ""

# Wait a moment for purge events to be logged
sleep 2

if [ -n "$DEBUG_LOG" ] && npm run wp-env -- run cli -- test -f "$DEBUG_LOG" 2>/dev/null; then
    echo "--- Recent PQU purge events from debug.log ---"
    npm run wp-env -- run cli -- tail -n 100 "$DEBUG_LOG" 2>/dev/null | grep "WPGraphQL PQU" || echo "No PQU log events found in recent logs"
    
    echo ""
    echo "--- All purge events (last 50 lines) ---"
    npm run wp-env -- run cli -- tail -n 50 "$DEBUG_LOG" 2>/dev/null | grep -A 2 "Purge Event" || echo "No purge events found"
else
    echo "WARNING: Debug log not found. Checking alternative locations..."
    # Try to find and read from any debug.log
    for log_path in "${DEBUG_LOG_PATHS[@]}"; do
        if npm run wp-env -- run cli -- test -f "$log_path" 2>/dev/null; then
            echo "Found log at: $log_path"
            npm run wp-env -- run cli -- tail -n 100 "$log_path" 2>/dev/null | grep "WPGraphQL PQU" || echo "No PQU log events found"
            break
        fi
    done
fi

echo ""
echo "=========================================="
echo "STEP 2 COMPLETE: Purge Verification"
echo "=========================================="
echo ""
echo "=========================================="
echo "Step 8: Verifying Database After Purge"
echo "=========================================="
echo ""
echo "Now let's verify what was purged and what remains:"
echo ""

# Check counts after purge
AFTER_POST_9_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; \$gid = '$POST_GRAPHQL_ID'; echo (int) \$wpdb->get_var( \$wpdb->prepare( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key = %s\", \$gid ) );")

AFTER_LIST_POST_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key = 'list:post'\" );")

AFTER_TAGS_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key LIKE '%tag%'\" );")

AFTER_CATEGORIES_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key LIKE '%category%'\" );")

AFTER_USERS_COUNT=$(pqu_wp_eval_int "global \$wpdb; \$p = \$wpdb->prefix; echo (int) \$wpdb->get_var( \"SELECT COUNT(*) FROM {\$p}wpgraphql_pqu_key_urls ku INNER JOIN {\$p}wpgraphql_pqu_cache_keys k ON k.id = ku.key_id WHERE k.cache_key LIKE '%user%'\" );")

echo "After purge counts:"
echo "  - post node ($POST_GRAPHQL_ID) url_key rows: $AFTER_POST_9_COUNT (was $INITIAL_POST_9_COUNT) - should be 0"
echo "  - list:post entries: $AFTER_LIST_POST_COUNT (was $INITIAL_LIST_POST_COUNT) - should be 0"
echo "  - tag entries: $AFTER_TAGS_COUNT (was $INITIAL_TAGS_COUNT) - should remain > 0"
echo "  - category entries: $AFTER_CATEGORIES_COUNT (was $INITIAL_CATEGORIES_COUNT) - should remain > 0"
echo "  - user entries: $AFTER_USERS_COUNT (was $INITIAL_USERS_COUNT) - should remain > 0"
echo ""

echo "--- All remaining key-map rows (should NOT include $POST_GRAPHQL_ID or list:post) ---"
npm run wp-env -- run cli -- wp db query "
SELECT u.url, k.cache_key, u.last_seen_at
FROM wp_wpgraphql_pqu_key_urls ku
INNER JOIN wp_wpgraphql_pqu_urls u ON u.id = ku.url_id
INNER JOIN wp_wpgraphql_pqu_cache_keys k ON k.id = ku.key_id
ORDER BY u.last_seen_at DESC;
"

echo ""
echo "--- Verifying Post 9 entries were deleted ---"
if [ "${AFTER_POST_9_COUNT:-0}" -eq 0 ]; then
    echo "✓ post node ($POST_GRAPHQL_ID) url_key rows successfully deleted"
else
    echo "✗ WARNING: post node url_key rows still exist (count: $AFTER_POST_9_COUNT)"
fi

if [ "${AFTER_LIST_POST_COUNT:-0}" -eq 0 ]; then
    echo "✓ list:post entries successfully deleted"
else
    echo "✗ WARNING: list:post entries still exist (count: $AFTER_LIST_POST_COUNT)"
fi
echo ""

echo "--- Verifying unrelated entries still exist ---"
if [ "${AFTER_TAGS_COUNT:-0}" -gt 0 ]; then
    echo "✓ tag entries remain (count: $AFTER_TAGS_COUNT)"
else
    echo "✗ WARNING: tag entries were deleted (should remain)"
fi

if [ "${AFTER_CATEGORIES_COUNT:-0}" -gt 0 ]; then
    echo "✓ category entries remain (count: $AFTER_CATEGORIES_COUNT)"
else
    echo "✗ WARNING: category entries were deleted (should remain)"
fi

if [ "${AFTER_USERS_COUNT:-0}" -gt 0 ]; then
    echo "✓ user entries remain (count: $AFTER_USERS_COUNT)"
else
    echo "✗ WARNING: user entries were deleted (should remain)"
fi
echo ""

echo ""
echo "=========================================="
echo "FINAL SUMMARY"
echo "=========================================="
echo ""
echo "STEP 1 RESULTS:"
echo "  ✓ Queries persisted with shared cache keys (post:$POST_ID, list:post)"
echo "  ✓ Queries persisted with different cache keys (tags, categories, users)"
echo "  ✓ Database entries created in wp_wpgraphql_pqu_documents"
echo "  ✓ Database entries created in wp_wpgraphql_pqu_urls / wp_wpgraphql_pqu_cache_keys / wp_wpgraphql_pqu_key_urls"
echo ""
echo "STEP 2 RESULTS:"
echo "  ✓ Post $POST_ID updated via CLI"
echo "  ✓ Purge events should be logged (check debug.log output above)"
echo ""
echo "EXPECTED PURGE BEHAVIOR:"
echo "  ✓ post:$POST_ID entries: DELETED (purged)"
echo "  ✓ list:post entries: DELETED (purged)"
echo "  ✓ tag entries: REMAIN (not purged)"
echo "  ✓ category entries: REMAIN (not purged)"
echo "  ✓ user entries: REMAIN (not purged)"
echo ""
echo "VERIFICATION CHECKLIST:"
echo "  [ ] Purge events logged in debug.log for post:$POST_ID"
echo "  [ ] Purge events logged in debug.log for list:post"
echo "  [ ] NullAdapter logged URLs that would be purged"
echo "  [ ] Database entries deleted for post:$POST_ID (count = 0)"
echo "  [ ] Database entries deleted for list:post (count = 0)"
echo "  [ ] Database entries remain for tags (count > 0)"
echo "  [ ] Database entries remain for categories (count > 0)"
echo "  [ ] Database entries remain for users (count > 0)"
echo ""
echo "Note: To prevent deletion during testing (so you can test multiple times),"
echo "add this filter to your theme's functions.php or a plugin:"
echo "  add_filter( 'wpgraphql_pqu_delete_entries_on_purge', '__return_false' );"
echo ""
