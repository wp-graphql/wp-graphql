# Methodology & how to run

> **TL;DR:** Real WordPress 7.0 (the Abilities API ships in core 6.9+). Two stand-in read abilities (`wpgraphql/get-post`, `wpgraphql/get-posts`) plus a mode switch that routes WPGraphQL's `PostObjectLoader` / `Post` model through them in different ways. A counter shim reports `dbQueries`, `wpQueryRuns`, `theContent`, and `abilityExec` in every response under `extensions.abilitiesPrototype`. **One gotcha that matters: measure each mode in its own PHP process** — block-theme `wp_template` lookups are statically memoized per process and will otherwise contaminate query counts (it briefly fooled us into thinking abilities ran *fewer* queries).

## Environment

- WordPress **7.0** via `wp-env` (the Abilities API — `wp_register_ability()`, `wp_get_ability()`, `WP_Ability` with separate `check_permissions()` and `execute()` — is in core as of 6.9).
- The experiment loads with WPGraphQL through a single `require_once __DIR__ . '/prototype/abilities-under-the-hood/load.php';` in `wp-graphql.php`. No mu-plugin, no `.wp-env.json` changes.
- Disable entirely: `define( 'WPGRAPHQL_DISABLE_ABILITIES_PROTOTYPE', true );`

## The stand-in abilities (`data-abilities.php`)

These simulate the kind of read abilities we assume core will eventually ship. They're shaped around the recommendations we shared on the PR:

- **`wpgraphql/get-post`** — a *single* post by ID, returns a single object. Single-entity, so it can gate cleanly in `permission_callback`.
- **`wpgraphql/get-posts`** — a *list*; supports `include` (batch-by-IDs → `post__in`), opt-in `include_total` (skips `found_posts` otherwise), and a lean default field set. Per-row visibility runs inside `execute_callback` (a list ability can't gate per-row in `permission_callback`).
- Visibility is **ported from `Post::is_private()` / `is_post_private()`** — a naive `read_post` gate would return nothing to anonymous users on published posts, because that cap maps to `read`, which logged-out users lack. (Finding in itself: a public-facing read ability must encode "published + public = world-readable" rather than lean on `read_post`.)
- Heavy fields (`content`, `excerpt`) and raw fields are only produced when explicitly named in `fields`; raw additionally requires `edit_post`.

## The mode switch (`resolve.php`)

| Mode | Constant / filter | What it does | Experiment |
|---|---|---|---|
| `off` | (default) | Native WP_Query + Model | baseline |
| `permission` | `WPGRAPHQL_ABILITIES_RESOLVE` / `add_filter('graphql_abilities_resolve','__return_true')` | Loader does fetch + per-row permission via `get-posts`; Model still resolves fields | A |
| `output` | `WPGRAPHQL_ABILITIES_RESOLVE_OUTPUT` | Loader requests a fixed payload from `get-posts` (ability is the data source) | B |
| `field` | `WPGRAPHQL_ABILITIES_RESOLVE_FIELD` | Native loader; the `content` Model field delegates to `get-post` | C |

You can also set any of them per-request with `add_filter('graphql_abilities_resolve_mode', fn() => 'output')`.

## The counters (`counters.php`)

Reset on `do_graphql_request`, surfaced on the response via `graphql_request_results`:

- `dbQueries` — total SQL for the request (`$wpdb->num_queries` delta)
- `wpQueryRuns` — `WP_Query` DB hits (`posts_request`)
- `theContent` — `the_content` filter invocations (the over-process signal)
- `abilityExec` — `execute()` calls per ability name (`wp_after_execute_ability`)
- `resolveMode` — which mode produced the response

Example:

```bash
wp eval '
wp_set_current_user(0);
add_filter("graphql_abilities_resolve_mode", fn() => "output");
wp_cache_flush();
$r = graphql(["query" => "{ posts(first:5){ nodes { id title date } } }"]);
echo wp_json_encode($r["extensions"]["abilitiesPrototype"]);
'
```

## Measurement gotcha (read this before trusting any numbers)

Run **one mode per `wp eval` process**, and `wp_cache_flush()` before the single measured query. Comparing two modes in the *same* process is contaminated: WordPress block-theme `wp_template` resolution is memoized in static state that survives `wp_cache_flush()`, so those ~4 queries land in whichever query runs first. An early same-process A/B made abilities look ~33% cheaper on queries; isolating processes showed the real delta is about **+1 query** of overhead. All numbers in these docs are from isolated processes.
