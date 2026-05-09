/**
 * Typed accessors for the boolean flags + `loginUrl` injected by PHP
 * via `WPGRAPHQL_IDE_DATA`.
 *
 * `wp_localize_script` serializes PHP `true` as the string `"1"` and
 * PHP `false` as `""`, so a strict `=== true` check on a checkbox flag
 * never matches. Reading the raw object inline meant the same gotcha
 * could be (and was) re-introduced anywhere a flag was read. Funnel
 * every read through this module so the coercion lives in one place.
 *
 * Non-boolean fields (`personalCollections`, `panelOrder`, `context`,
 * `nonce`, etc.) don't share the coercion problem and are still read
 * inline at their consumers.
 *
 * Server-side fields are documented in
 * `wpgraphql-ide.php::enqueue_react_app_with_styles()` and
 * `includes/public-endpoint.php::inject_public_endpoint_data()`.
 */

const data = (typeof window !== 'undefined' && window.WPGRAPHQL_IDE_DATA) || {};

/** Whether the IDE is on the dedicated `?page=graphql-ide` admin page. */
export const isDedicatedIdePage = !!data.isDedicatedIdePage;

/**
 * Whether the IDE should render full-page (no slide-up drawer). True
 * for both the dedicated admin page and the public `/?graphql` shell.
 */
export const renderStandalone = !!data.renderStandalone;

/**
 * Whether the IDE is in endpoint mode — rendered at the public
 * `/?graphql` URL for visitors who lack `manage_graphql_ide`. Hides
 * Save / Saved Queries / History / Document Settings / Share /
 * topbar actions / (when anonymous) the auth toggle. IDE-capable
 * admins at the same URL have this flag *false*.
 */
export const endpointMode = !!data.endpointMode;

/** Whether the visitor is logged in to WordPress. */
export const isUserLoggedIn = !!data.isUserLoggedIn;

/**
 * Sign-in URL with `redirect_to` set to the current page, or empty
 * when no sign-in affordance should render (visitor is already
 * logged in, or the field wasn't injected).
 */
export const loginUrl = typeof data.loginUrl === 'string' ? data.loginUrl : '';
