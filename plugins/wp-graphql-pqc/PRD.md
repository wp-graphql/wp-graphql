**WPGraphQL**

**Persisted Query Cache**

Product Requirements Document

  --------------------- -------------------------------------------------
  **Status**            Beta (v0.1.0-beta.1) --- Core features implemented

  **Author**            Jason Bahl

  **Date**              March 2026

  **Delivery**          New experimental plugin, companion to WPGraphQL
                        Smart Cache

  **Depends**           WPGraphQL core, WPGraphQL Smart Cache
  --------------------- -------------------------------------------------

**1. Background & Problem Statement**

WPGraphQL Smart Cache provides caching and cache invalidation for
WPGraphQL queries. Its network cache strategy works by tagging cached
responses with keys derived from the X-GraphQL-Keys response header,
then purging those tags when relevant WordPress events fire (post
published, updated, deleted, etc.).

This model works well on hosts that support tag-based cache invalidation
--- such as WP Engine\'s Varnish + xkey implementation. However, a
significant portion of WordPress hosts, including WordPress VIP, do not
support tag-based purging. These hosts purge by full URL only.

This creates a fundamental incompatibility:

-   GraphQL queries are typically sent as POST requests, which are not
    cacheable at the network layer.

-   GET requests work, but the query and variables are passed as query
    parameters (e.g. /graphql?query=\...&variables=\...).

-   Hosts that purge by URL typically ignore query parameters ---
    meaning purging /graphql would either purge everything or nothing
    useful.

-   There is no way to surgically purge a specific query+variables
    combination using URL-based purging alone.

This plugin solves the problem by introducing a permalink-based URL
convention for persisted queries and a server-side URL→cache-key index,
enabling precise URL-based purging without requiring tag-based host
support.

**2. Goals**

-   Enable surgical, tag-accurate cache invalidation on hosts that only
    support URL-based purging (WordPress VIP and similar).

-   Extend WPGraphQL Smart Cache\'s existing cache invalidation event
    system rather than replace it.

-   Require zero changes to WPGraphQL core or WPGraphQL Smart Cache
    internals --- this plugin hooks into existing extension points.

-   Work with any GraphQL client that can be configured to follow the
    documented URL convention.

-   Provide a reference client implementation (Apollo Link) to
    demonstrate the convention and lower adoption friction.

-   Degrade gracefully: standard POST /graphql always works as a
    fallback; the plugin enhances but never breaks existing workflows.

**3. Non-Goals**

-   This plugin does not replace WPGraphQL Smart Cache. It is a
    companion that extends Smart Cache\'s purge event system.

-   This plugin does not implement a new object cache layer. WPGraphQL
    Smart Cache\'s existing object cache feature remains unchanged.

-   This plugin does not store or serve GraphQL response bodies. The
    host\'s page cache (nginx, Varnish, Cloudflare, etc.) is the cache
    layer. This plugin only maintains the index needed to know which
    URLs to purge.

-   This plugin does not require Redis or any specific infrastructure
    beyond a standard WordPress database.

-   Authenticated queries can use persisted query URLs but receive
    `no-store` cache headers. Only public, unauthenticated query
    responses are cached at the network layer.

**4. How WPGraphQL Smart Cache Works Today**

Understanding the existing architecture is important context for how
this plugin fits in.

**4.1 Query Analysis & Tagging**

When a GraphQL query executes, WPGraphQL\'s Query Analyzer inspects the
query AST and the resolved data to produce a set of cache keys. These
keys identify what data the query depended on. Examples:

-   post:123 --- the response included node with database ID 123

-   list:post --- the response included a list of posts

-   user:7 --- the response included user node 7

-   skipped:post --- the response included post nodes but the ID list
    was truncated due to header size limits

These keys are emitted in the X-GraphQL-Keys HTTP response header.

**4.2 Network Cache (Tag-Based Hosts)**

On supported hosts, the network cache layer (e.g. Varnish + xkey) reads
the X-GraphQL-Keys header and tags the cached response with those keys.
When Smart Cache fires a purge event for a key (e.g. post:123), the host
purges all responses tagged with that key.

This model requires the host to support tag-based cache invalidation.
Many hosts do not.

**4.3 Object Cache**

Smart Cache also offers an object cache mode using WordPress\'s
wp_cache\_\* API. This stores query results in the persistent object
cache (Memcached on VIP, Redis elsewhere) and invalidates them using the
same key-based event system. This works on any host but requires
WordPress to bootstrap on every request --- responses are never served
from the network layer.

**4.4 The graphql_purge Action**

Smart Cache exposes a graphql_purge action that fires when a purge event
is triggered. This is the primary extension point this plugin uses.
Third-party code --- or companion plugins like this one --- can hook
into graphql_purge to take custom action when a cache key needs to be
invalidated.

> do_action( \'graphql_purge\', \$purge_key, \$event, \$hostname );

**4.5 Existing APQ Support**

Smart Cache has existing Automated Persisted Queries (APQ) support,
storing query documents as a graphql_document custom post type. The APQ
implementation stores query documents by hash so they can be retrieved
and re-executed. This plugin\'s GET-first flow builds on a similar
concept but is distinct: it does not require documents to be
pre-registered, and it stores the full query document in the URL index
table rather than a CPT.

**5. Proposed Solution**

**5.1 Core Concept**

This plugin introduces a permalink-based URL convention for GraphQL
queries. Instead of sending queries as POST requests or GET requests
with query parameters, clients send GET requests to clean WordPress
permalink URLs:

> /graphql/persisted/{queryHash}/variables/{variablesHash}

Because the query hash and variables hash are baked into the URL path
--- not query parameters --- the page cache can cache these responses at
the full URL. Individual persisted query responses can be purged by
their specific URL without affecting other cached responses.

The plugin maintains a server-side index mapping each URL to its
associated cache keys. When Smart Cache fires a purge event, the plugin
looks up all URLs tagged with that key and issues URL-based purge
requests for each one.

**5.2 The Two-Phase Request Flow**

The server never reconstructs a query from a hash alone --- it only
executes queries it receives in full. This is solved with a two-phase
flow modeled on the APQ specification:

**Phase 1 --- Cold Start (POST)**

The first time a client executes a query, it sends a standard POST
request:

-   Client sends: POST /graphql with the full query document and
    variables.

-   Server executes the query normally.

-   Server computes queryHash = SHA-256 of the normalized query
    document.

-   Server computes variablesHash = SHA-256 of the canonicalized
    variables JSON (keys sorted, no whitespace).

-   Server constructs the canonical GET URL:
    /graphql/persisted/{queryHash}/variables/{variablesHash}.

-   Server stores an index entry: URL + query document + variables + all
    cache keys from X-GraphQL-Keys.

-   Server returns the response normally. Optionally includes the
    canonical GET URL in response extensions.

**Phase 2 --- Warm Path (GET, page cache hit)**

-   Client sends: GET
    /graphql/persisted/{queryHash}/variables/{variablesHash}.

-   The host\'s page cache serves the cached response. WordPress never
    runs.

-   This is the fast path --- zero PHP execution cost.

**Phase 2 --- Warm Path (GET, page cache miss)**

-   Client sends: GET
    /graphql/persisted/{queryHash}/variables/{variablesHash}.

-   The page cache has no entry for this URL (e.g. after a purge or cold
    server).

-   WordPress handles the request. The plugin looks up queryHash +
    variablesHash in its index.

-   If found: re-executes the query using the stored document and
    variables, re-stores keys, returns response. Page cache stores it
    again.

-   If not found: returns HTTP 404. Client falls back to POST (Phase 1).

**Phase 2 --- GET with No Index Entry (404 Fallback)**

-   Client sends GET. Server returns 404 with a nonce in response
    extensions --- query is not registered.

-   Client falls back to POST /graphql with the full query document,
    nonce, and computed hashes in request extensions.

-   Server validates nonce and hashes match, then stores the index entry
    for future GET requests.

**Note**: Future versions will require nonce validation and hash matching
for security. Currently (v0.1.0-beta.1), queries are stored automatically
for authenticated requests (with opt-in for public requests).

**5.3 Cache Invalidation Flow**

When WordPress content changes, Smart Cache fires the graphql_purge
action with a cache key (e.g. post:123). This plugin hooks into that
action:

-   Query the index table for all URLs tagged with that key.

-   For each URL, send a purge request to the host\'s cache layer.

-   Delete the index rows for that key (the URL will be re-indexed on
    the next request).

The purge mechanism is host-specific and implemented via a pluggable
adapter interface. The plugin ships a default adapter for WordPress VIP
and a generic PURGE request adapter for nginx/Varnish hosts.

**5.4 Hashing & Canonicalization Convention**

Both client and server must produce identical hashes for the same query
and variables. The convention is:

-   Query hash: SHA-256 of the printed, normalized GraphQL document
    (whitespace-normalized, consistent field ordering).

-   Variables hash: SHA-256 of the variables JSON with keys sorted
    recursively and no extra whitespace.

-   Empty variables: if no variables are provided, the variables segment
    is omitted entirely from the URL: /graphql/persisted/{queryHash}

This convention is published as a standalone specification document so
that non-Apollo clients (URQL, fetch, etc.) can implement it
independently.

**6. Data Architecture**

**6.1 Normalized Database Tables**

The URL→key index is stored in normalized database tables to avoid
duplicating query documents. The same query document can be used with
different variables and cache keys, so documents are stored separately.

**Table 1: `wp_wpgraphql_pqc_documents`**

Stores unique query documents (one row per unique query hash):

> CREATE TABLE {prefix}wpgraphql_pqc_documents (
>
> query_hash varchar(64) NOT NULL PRIMARY KEY,
>
> query_document longtext NOT NULL,
>
> created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
>
> INDEX idx_query_hash (query_hash)
>
> );

**Table 2: `wp_wpgraphql_pqc_url_keys`**

Junction table representing the many-to-many relationship between URLs
and cache keys. References documents table via `query_hash`:

> CREATE TABLE {prefix}wpgraphql_pqc_url_keys (
>
> url_hash varchar(64) NOT NULL,
>
> url varchar(2083) NOT NULL,
>
> query_hash varchar(64) NOT NULL,
>
> variables_hash varchar(64) NOT NULL,
>
> variables longtext NOT NULL,
>
> cache_key varchar(255) NOT NULL,
>
> created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
>
> PRIMARY KEY (url_hash, cache_key),
>
> INDEX idx_cache_key (cache_key),
>
> INDEX idx_query_lookup (query_hash, variables_hash),
>
> INDEX idx_url_hash (url_hash),
>
> FOREIGN KEY (query_hash) REFERENCES {prefix}wpgraphql_pqc_documents(query_hash)
>
> );

Key design decisions:

-   **Normalized documents**: Query documents are stored once per
    `query_hash` in the documents table, avoiding duplication when the
    same query is used with different variables or cache keys.

-   **Variables per URL**: Variables are stored per URL (not normalized)
    because the same query with different variables produces different
    URLs and different cache key sets.

-   Each row in `url_keys` represents one URL + one cache key. A URL
    tagged with five keys produces five rows. This is the correct
    relational model for a many-to-many relationship.

-   PRIMARY KEY (url_hash, cache_key) prevents duplicate rows if the
    same URL executes twice before a purge.

-   INDEX on cache_key enables fast purge lookups: \"give me all URLs
    tagged with post:123\" without a full table scan.

-   INDEX on (query_hash, variables_hash) enables fast GET cache-miss
    lookup without a full table scan.

-   FOREIGN KEY ensures referential integrity between documents and
    url_keys tables.

-   created_at supports TTL-based garbage collection via WP-Cron.

**6.2 Store Abstraction**

The index storage is abstracted behind a PHP interface to allow
alternative backends:

> interface StoreInterface {
>
> public function store( string \$url, string \$query_hash,
>
> string \$variables_hash, string \$query_doc,
>
> string \$variables, array \$cache_keys ): void;
>
> public function get_query( string \$query_hash,
>
> string \$variables_hash ): ?array;
>
> public function get_urls_for_key( string \$cache_key ): array;
>
> public function delete_by_key( string \$cache_key ): void;
>
> }

**Current Implementation** (v0.1.0-beta.1):

-   `DBStore` --- custom table implementation using normalized database
    structure. The universal baseline, works on all hosts.

**Future Implementations** (Planned):

-   `RedisStore` --- uses Redis sets for the reverse index, with TTL
    support. Auto-selected when Redis is available via the WordPress
    object cache.

Note: WordPress VIP uses Memcached, not Redis. The DB store is the
correct implementation for VIP environments.

**6.3 Storage Backend by Host**

  ---------------------- ------------------ ------------------------------
  **Environment**        **Object Cache**   **PQC Index Store**

  WordPress VIP          Memcached          DB table (default)

  WP Engine              Redis (optional)   Redis store (if available),
                                            else DB

  Pantheon               Redis              Redis store

  Self-hosted w/ Redis   Redis              Redis store

  Generic shared hosting None / DB          DB table (default)
                         transients         
  ---------------------- ------------------ ------------------------------

**7. URL Convention Specification**

The following is the normative specification for the persisted query URL
structure. Both client and server implementations must conform to this
spec for hashes to match.

**7.1 URL Structure**

> \# With variables:
>
> /graphql/persisted/{queryHash}/variables/{variablesHash}
>
> \# Without variables (empty or null):
>
> /graphql/persisted/{queryHash}

**7.2 Query Hash**

-   Algorithm: SHA-256, output as lowercase hex string.

-   Input: the printed GraphQL document, whitespace-normalized (single
    spaces, no leading/trailing whitespace, no newlines).

-   Field order within a selection set must be preserved as authored ---
    no reordering.

-   Fragments must be included in the printed document.

**7.3 Variables Hash**

-   Algorithm: SHA-256, output as lowercase hex string.

-   Input: the variables object serialized to JSON with keys sorted
    recursively (deep sort), no extra whitespace.

-   Nested object keys must also be sorted.

-   Array order is preserved (arrays are not sorted).

-   If variables is null, undefined, or an empty object {}, the
    /variables/{hash} segment is omitted entirely.

**7.4 WordPress Rewrite Rules**

The plugin registers WordPress rewrite rules to handle these URLs:

> \# Maps to internal WP query vars:
>
> graphql/persisted/(\[a-f0-9\]{64})/?\$
>
> → index.php?graphql_persisted_query=1&graphql_query_hash=\$1
>
> graphql/persisted/(\[a-f0-9\]{64})/variables/(\[a-f0-9\]{64})/?\$
>
> → index.php?graphql_persisted_query=1
>
> &graphql_query_hash=\$1&graphql_variables_hash=\$2

**8. Host Purge Adapters**

The plugin uses an adapter pattern to issue URL purge requests to the
host\'s cache layer. The adapter is selected automatically based on
environment detection, or can be overridden via a WordPress filter.

**8.1 Adapter Interface**

> interface WPGraphQL_PQC_Purge_Adapter {
>
> public function purge_url( string \$url ): bool;
>
> public function purge_all(): bool;
>
> }

**8.2 Bundled Adapters**

  ------------------ ----------------------------------------------------
  **Adapter**        **Description**

  VIP                Calls wpcom_vip_purge_edge_cache_for_url().
                     Auto-detected on VIP platform.

  PURGE Request      Sends HTTP PURGE method request to the URL.
                     Compatible with Varnish, nginx proxy_cache_purge,
                     and similar.

  WP Engine          Uses WP Engine\'s cache purge API. Auto-detected on
                     WPE infrastructure.

  Null (No-op)       Logs purge events but takes no action. Useful for
                     development and debugging.
  ------------------ ----------------------------------------------------

Custom adapters can be registered via filter:

> add_filter( \'wpgraphql_pqc_purge_adapter\', function( \$adapter ) {
>
> return new My_Custom_Purge_Adapter();
>
> } );

**9. Client Integration**

**9.1 Apollo Link (Reference Implementation)**

The plugin ships a companion npm package:
\@wpgraphql/persisted-query-link. This is the canonical reference
implementation of the client-side URL convention and the two-phase
request flow.

> import { createWPGraphQLPersistedQueryLink }
>
> from \'@wpgraphql/persisted-query-link\';
>
> const pqLink = createWPGraphQLPersistedQueryLink({
>
> endpoint: \'https://example.com/graphql\',
>
> });
>
> const client = new ApolloClient({
>
> link: ApolloLink.from(\[ pqLink, httpLink \]),
>
> cache: new InMemoryCache(),
>
> });

The link implements the full two-phase flow:

-   Computes queryHash and variablesHash client-side using the same
    algorithm as the server.

-   Sends GET to the persisted URL first.

-   On 404, falls back to POST and then retries the GET on the next
    request.

-   Transparently handles the fallback --- application code requires no
    changes.

**9.2 Convention Document**

A standalone specification document is published separately from any
client library. This allows URQL, fetch-based clients, and
framework-specific integrations to implement the convention
independently without depending on the Apollo Link package.

The spec covers: URL structure, hashing algorithm, canonicalization
rules, the two-phase flow, 404 fallback behavior, and authenticated
request handling (which should always use POST and never be cached).

**9.3 Client Responsibilities**

-   Compute hashes client-side using the documented algorithm (SHA-256
    of normalized query document and canonicalized variables JSON).

-   Always attempt GET first for any query that has been seen before.

-   Fall back to POST on 404 --- never assume a query is registered.

-   **Authenticated requests**: Can use persisted URLs, but should
    understand that responses will have `no-store` headers and won't be
    cached. The persisted URL still provides value by avoiding document
    upload overhead.

-   **Future**: Include nonce and computed hashes in POST request
    extensions when falling back from a GET 404.

-   Treat the persisted URL as a performance optimization, not a
    functional requirement --- POST always works.

**10. Security Considerations**

**10.1 Authenticated Requests**

Authenticated GraphQL responses must never be cached at the network
layer. The plugin enforces this by:

-   Authenticated GET requests to persisted URLs execute as the
    authenticated user and receive `Cache-Control: no-store` headers.

-   Public GET requests execute as public users and receive cacheable
    headers (respecting Smart Cache settings).

-   **Storage Control**: By default, only authenticated POST requests
    can persist queries. A setting allows opt-in for public requests
    to automatically persist.

-   **Nonce Validation** (Future): POST requests must include a nonce
    from a prior GET 404 response, plus computed hashes, to prevent
    unauthorized persistence.

This ensures authenticated user data is never cached while still
allowing authenticated users to benefit from persisted query URLs
(without caching).

**10.2 Query Validation**

Queries executed via GET (cache miss path) are retrieved from the
plugin\'s own index --- they were previously validated and executed via
POST. The plugin does not execute arbitrary query documents supplied in
GET request parameters. The URL structure carries only hashes, never the
query document itself.

**Future Enhancement**: POST requests will require:
-   A valid nonce from a prior GET 404 response
-   Client-computed hashes in request extensions
-   Server validation that client hashes match server-computed hashes

This prevents unauthorized persistence and ensures hash computation
consistency.

**10.3 Cache Poisoning**

Because the query document is stored server-side on first POST and
re-executed server-side on GET cache miss, there is no mechanism for a
client to supply a different query document for an existing hash. Hash
collisions in SHA-256 are computationally infeasible.

**10.4 Storage Access Control**

By default (v0.1.0-beta.1+), only authenticated POST requests can
persist queries. This means:

-   Queries must be pre-defined via build tools using Application
    Passwords or similar authentication.

-   Public requests cannot automatically persist queries unless the
    admin explicitly enables this setting.

-   This prevents database bloat from random public requests and
    provides controlled rollout of the feature.

**11. Garbage Collection & TTL**

Index entries accumulate over time as new queries are executed. Purge
events clean up entries reactively, but entries for rarely-accessed
queries may persist indefinitely without a purge trigger.

The plugin schedules a WP-Cron job to delete index rows older than a
configurable TTL (default: 7 days). This provides a safety net for
entries that were never explicitly purged.

The Redis store implementation uses native key TTL, so entries expire
automatically without a Cron job. The DB store relies on Cron.

Site administrators can also trigger a full index flush from the
WPGraphQL Settings page.

**12. Relationship to WPGraphQL Smart Cache**

**12.1 Integration Points**

This plugin integrates with Smart Cache exclusively via documented
hooks:

-   graphql_purge action --- the primary integration point. This plugin
    hooks in to trigger URL purges when Smart Cache fires invalidation
    events.

-   X-GraphQL-Keys header --- the plugin reads this header from
    WPGraphQL responses to populate the URL→key index.

-   WPGraphQL\'s request lifecycle hooks --- used to intercept GET
    requests to persisted URLs and handle the cache-miss execution path.

**12.2 Positioning**

WPGraphQL Smart Cache remains the core cache management plugin. This
plugin is a host-compatibility layer that extends Smart Cache\'s reach
to hosts that do not support tag-based cache invalidation.

  ----------------------- ----------------------- -----------------------
  **Capability**          **Smart Cache**         **+ This Plugin**

  Object cache (any host) Yes                     Yes (unchanged)

  Network cache           Yes                     Yes (unchanged)
  (tag-based hosts)                               

  Network cache           No                      Yes (new)
  (URL-purge hosts)                               

  Persisted query URL     Partial (APQ CPT)       Yes (full, with index)
  convention                                      

  Client reference        No                      Yes (Apollo Link)
  implementation                                  
  ----------------------- ----------------------- -----------------------

**12.3 Future Consideration: Merging into Smart Cache**

This plugin is initially shipped as a standalone experimental plugin to
allow rapid iteration without affecting Smart Cache\'s stability. If the
approach proves successful, the relevant components could be merged into
Smart Cache as an opt-in feature or a host adapter system within Smart
Cache itself.

**13. Phased Delivery**

**Phase 1 --- Core Infrastructure** ✅ (Completed)

-   ✅ WordPress rewrite rules for /graphql/persisted/{queryHash} and
    /graphql/persisted/{queryHash}/variables/{variablesHash}.

-   ✅ Custom database tables (normalized: documents + url_keys) and DB
    store implementation.

-   ✅ POST request hook: compute hashes, store index entries after
    successful query execution.

-   ✅ GET request handler: cache-miss path --- look up query from index,
    re-execute, return response.

-   ✅ GET request handler: 404 path --- return 404 when query hash not in
    index.

-   ✅ graphql_purge hook: look up URLs by key, issue purge requests, clean
    up index rows.

-   ✅ Null and VIP purge adapters.

-   ✅ Basic WP-Cron garbage collection.

-   ✅ Authentication-aware execution and cache headers.

**Phase 1.5 --- Database Normalization** 🔄 (In Progress)

-   Normalize database structure to separate documents from URL-key
    mappings.

-   Create `wp_wpgraphql_pqc_documents` table for unique query documents.

-   Migrate existing data to normalized structure.

-   Update store implementation to use JOINs.

**Phase 1.6 --- PQC Flow with Nonce** 📋 (Planned)

-   Generate nonce on GET 404 responses.

-   Require nonce + hashes in POST request extensions.

-   Validate nonce and hash matching before storing.

**Phase 1.7 --- Authentication Requirements** 📋 (Planned)

-   Default: Only authenticated requests can persist queries.

-   Admin setting to allow public requests to auto-persist.

-   Filter for custom persistence logic.

**Phase 2 --- Host Adapters & Redis**

-   WordPress VIP purge adapter.

-   Generic PURGE request adapter (nginx / Varnish).

-   WP Engine purge adapter.

-   Redis store implementation.

-   Auto-detection logic for store and adapter selection.

-   Admin settings page: enable/disable plugin, configure TTL, flush
    index, select adapter override.

**Phase 3 --- Client & Documentation**

-   \@wpgraphql/persisted-query-link npm package (Apollo Link reference
    implementation).

-   Standalone URL convention specification document.

-   Developer documentation and integration guide.

-   URQL exchange implementation (stretch goal).

**14. Open Questions**

-   ✅ **RESOLVED**: The plugin returns the canonical GET URL in POST
    response extensions (`persistedQueryUrl`). This allows clients to
    discover the URL without computing it client-side.

-   ✅ **RESOLVED**: Variables use hash (SHA-256), not base64. Variables are
    stored server-side in the normalized database structure.

-   ✅ **RESOLVED**: Queries with `skipped:$type_name` keys are handled the
    same as regular keys. The purge handler treats them identically.

-   **Future Consideration**: Should the plugin support pre-registering
    known queries (similar to trusted document stores) so the GET path
    works on first request without a prior POST? This would suit SSG/ISR
    use cases where the build process can register queries ahead of time.

-   **Future Consideration**: WordPress VIP\'s URL purge API purges all
    query parameter variants simultaneously. Since this plugin uses clean
    permalink paths with no query parameters, each URL is independent and
    purges are surgical. This should be confirmed with VIP infrastructure
    documentation before shipping.

-   **Future Consideration**: Client Application Registry (see [GitHub
    Issue #2654](https://github.com/wp-graphql/wp-graphql/issues/2654))
    for per-client settings and identification.

*WPGraphQL Persisted Query Cache --- Product Requirements Document ---
March 2026*