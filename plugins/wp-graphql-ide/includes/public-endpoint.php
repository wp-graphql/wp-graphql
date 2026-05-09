<?php
/**
 * Optional public IDE at the GraphQL endpoint URL.
 *
 * When the `graphql_ide_public_endpoint` setting is enabled, browser
 * GETs to the GraphQL endpoint URL (e.g. `/graphql` or `/?graphql`)
 * render the IDE shell instead of the JSON API. API clients (POST,
 * `Accept: application/json`, query-param requests) still fall through
 * to WPGraphQL's normal handler — only Accept-text/html browser GETs
 * are intercepted.
 *
 * The IDE renders in "lite" mode for these visits: query / variables /
 * headers / response viewer / docs explorer only. Per-user features —
 * Save buttons, Saved Queries, History, Document Settings, share, the
 * topbar's registered actions — are gated client-side via the
 * `WPGRAPHQL_IDE_DATA.liteMode` bootstrap flag. Logged-in users get
 * authenticated requests via their nonce / cookies; anonymous visitors
 * get whatever the public schema exposes (already controlled by
 * WPGraphQL's existing introspection / capability gates).
 *
 * Default: off. Site admins must explicitly opt in via WPGraphQL →
 * IDE Settings — exposing a clickable schema browser to the public web
 * shouldn't happen on a plugin update.
 *
 * @package WPGraphQL\IDE
 */

namespace WPGraphQLIDE;

/**
 * Read-only getter / setter for the in-flight "we're rendering the
 * public endpoint right now" flag. The enqueue function consults this
 * to bypass its capability + admin-bar gates only for this code path,
 * leaving all other call sites unaffected.
 *
 * @param bool|null $set When non-null, sets the flag; when null, gets it.
 */
function public_endpoint_render_is_active( ?bool $set = null ): bool {
	static $active = false;
	if ( null !== $set ) {
		$active = $set;
	}
	return $active;
}

/**
 * Whether the public-endpoint IDE setting is enabled. Cached statically
 * within a request because both the route check and the bootstrap
 * injection consult it.
 *
 * WPGraphQL stores checkbox fields as the string `"on"` (checked) or
 * `"off"` (unchecked). PHP's `(bool) "off"` evaluates to *true* —
 * non-empty strings are truthy — so a `(bool)` cast here would treat
 * "unchecked" as "enabled" and the toggle would never disable anything.
 * Compare against `"on"` explicitly.
 */
function public_endpoint_is_enabled(): bool {
	static $enabled = null;
	if ( null !== $enabled ) {
		return $enabled;
	}
	if ( ! function_exists( 'get_graphql_setting' ) ) {
		$enabled = false;
		return false;
	}
	$enabled = 'on' === get_graphql_setting(
		'graphql_ide_public_endpoint',
		'off',
		'graphql_ide_settings'
	);
	return $enabled;
}

/**
 * Detect a *browser* GET request to the GraphQL endpoint URL. Returns
 * false for POST requests, JSON-Accept clients, or query-param API
 * calls — those keep the existing JSON handling.
 *
 * Browser address-bar visits send `Accept: text/html,application/xhtml+xml,...`.
 * GraphQL clients (Apollo, urql, fetch with `Content-Type: application/json`,
 * curl with explicit headers, etc.) send `Accept: application/json`. We
 * positively match the first and negatively exclude the second so a
 * client that sends `application/json, text/html` (rare but possible)
 * still gets JSON, not HTML — silently swapping a JSON response for a
 * 100KB IDE payload would be a much worse failure mode than the
 * occasional "this URL needs to be visited in a browser" edge case.
 */
function is_browser_html_request_to_endpoint(): bool {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return false;
	}
	if ( ! \WPGraphQL\Router::is_graphql_http_request() ) {
		return false;
	}

	// `?query=...` or `?variables=...` is an API call; let WPGraphQL handle it.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['query'] ) || isset( $_GET['variables'] ) ) {
		return false;
	}

	$accept = (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' );

	// Any GraphQL client that explicitly asks for JSON — even alongside
	// other types — wants JSON. Don't intercept.
	if ( false !== stripos( $accept, 'application/json' ) ) {
		return false;
	}

	// Browser GET — must positively prefer HTML.
	if ( false === stripos( $accept, 'text/html' ) ) {
		return false;
	}

	return true;
}

/**
 * Intercept browser GETs to the GraphQL endpoint URL when the feature
 * is enabled and render the IDE shell. Hooked at parse_request priority
 * 9 so it fires before WPGraphQL's `resolve_http_request` (priority 10).
 *
 * Exits when it serves a response — WordPress should not continue to
 * the standard template loader.
 */
function maybe_render_public_ide(): void {
	if ( ! public_endpoint_is_enabled() ) {
		return;
	}
	if ( ! is_browser_html_request_to_endpoint() ) {
		return;
	}

	render_public_ide_shell();
	exit;
}
add_action( 'parse_request', __NAMESPACE__ . '\\maybe_render_public_ide', 9 );

/**
 * Render the IDE shell page. Mirrors the dedicated-page enqueue path so
 * the same script/style handles are loaded — just without the wp-admin
 * chrome.
 */
function render_public_ide_shell(): void {
	// Tell the IDE bootstrap to enter endpoint mode. The same bundle
	// handles both modes; the flag rides through `WPGRAPHQL_IDE_DATA`.
	add_filter(
		'wpgraphql_ide_localized_data',
		__NAMESPACE__ . '\\inject_endpoint_mode_flag',
		10,
		1
	);

	// No admin bar on the public render — the page is the IDE; the
	// admin-bar trigger would also try to mount its own IDE instance
	// into a sibling div, double-mounting the React root.
	add_filter( 'show_admin_bar', '__return_false' );

	// Defer to WP's enqueue / head pipeline by emitting a minimal HTML
	// document. The IDE's existing `wp_enqueue_script` registration
	// (gated on `is_dedicated_ide_page` today) needs to run here too —
	// see enqueue_for_public_endpoint() below, which hooks the same
	// enqueue function we use on the dedicated page.
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_for_public_endpoint' );

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );

	$root_id = defined( 'WPGRAPHQL_IDE_ROOT_ELEMENT_ID' )
		? WPGRAPHQL_IDE_ROOT_ELEMENT_ID
		: 'wpgraphql-ide-root';

	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php esc_html_e( 'GraphQL IDE', 'wpgraphql-ide' ); ?></title>
	<?php wp_head(); ?>
	<?php
	/*
	 * Inline style emitted *after* `wp_head()` so theme / WP styles can't
	 * win the cascade and collapse the IDE to its content height.
	 *
	 * The dedicated admin page gets full-height from wp-admin's own
	 * styles. On the front-end public render there's no equivalent —
	 * we set it explicitly with selectors specific enough to beat any
	 * theme's `body { height: auto }`.
	 */
	?>
	<style>
		html, body.wpgraphql-ide-public-endpoint {
			height: 100%;
			margin: 0;
			padding: 0;
			overflow: hidden;
		}
		body.wpgraphql-ide-public-endpoint #<?php echo esc_attr( $root_id ); ?>,
		body.wpgraphql-ide-public-endpoint #wpgraphql-ide-app {
			height: 100vh;
			width: 100vw;
		}
	</style>
</head>
<body class="wpgraphql-ide-public-endpoint">
	<div id="<?php echo esc_attr( $root_id ); ?>"></div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
}

/**
 * Force the IDE script bundle to enqueue on the public endpoint render
 * even though we're not on the dedicated admin page. Calls the same
 * function the dedicated page uses so the script handles, dependency
 * graph, and `WPGRAPHQL_IDE_DATA` bootstrap are identical.
 */
function enqueue_for_public_endpoint(): void {
	if ( ! function_exists( __NAMESPACE__ . '\\enqueue_react_app_with_styles' ) ) {
		return;
	}
	public_endpoint_render_is_active( true );
	enqueue_react_app_with_styles();
	public_endpoint_render_is_active( false );
}

/**
 * Add `endpointMode: true` and the current login state to the bootstrap.
 *
 * The IDE reads `endpointMode` on mount and conditionally hides Save
 * buttons, Saved Queries, History, Document Settings, share, topbar
 * actions, and (when the visitor is anonymous) the auth toggle. The
 * `isUserLoggedIn` flag seeds the app store's initial auth state so
 * anonymous visitors don't start with the toggle in the "send my
 * nonce" position.
 *
 * IDE-capable admins (`manage_graphql_ide`) hitting the public endpoint
 * URL get the full IDE — endpoint mode is not flagged. They're already
 * authenticated via cookies, so the same REST routes the dedicated
 * page uses succeed for them here too. Anyone without that capability
 * (including logged-in non-admins) gets endpoint mode.
 *
 * @param array<string,mixed> $data
 *
 * @return array<string,mixed>
 */
function inject_endpoint_mode_flag( array $data ): array {
	if ( current_user_can( 'manage_graphql_ide' ) ) {
		$data['isUserLoggedIn'] = true;
		return $data;
	}
	$data['endpointMode']   = true;
	$data['isUserLoggedIn'] = is_user_logged_in();
	// Sign-in URL with a redirect back to the current page so the user
	// lands on the same IDE shell after login (now in full-IDE mode if
	// they have `manage_graphql_ide`).
	$current_url        = ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' );
	$data['loginUrl']   = wp_login_url( $current_url );
	return $data;
}

/**
 * Register the settings field on the IDE Settings page. Default off so
 * a clickable schema browser doesn't appear publicly on plugin update —
 * site admins must explicitly opt in.
 */
function register_public_endpoint_setting(): void {
	if ( ! function_exists( 'register_graphql_settings_field' ) ) {
		return;
	}
	register_graphql_settings_field(
		'graphql_ide_settings',
		[
			'name'    => 'graphql_ide_public_endpoint',
			'label'   => __( 'Public IDE at GraphQL endpoint', 'wpgraphql-ide' ),
			'desc'    => __(
				'When enabled, visiting the GraphQL endpoint URL in a browser renders the IDE in read-only "endpoint" mode (no save, no saved queries, no history, no document settings). Anonymous visitors see only what your public schema exposes; logged-in users get authenticated access via their session. Requires public introspection to be enabled in WPGraphQL settings — without it, the Docs Explorer will be empty for anonymous visitors. Consider rate-limiting at the web-server / CDN layer before enabling on a public site.',
				'wpgraphql-ide'
			),
			'type'    => 'checkbox',
			'default' => false,
		]
	);
}
add_action( 'graphql_register_settings', __NAMESPACE__ . '\\register_public_endpoint_setting' );

/**
 * Show an admin notice on the WPGraphQL settings page when the public
 * IDE endpoint is enabled but public introspection isn't, since the
 * Docs Explorer will be silently empty for anonymous visitors in
 * that combination.
 *
 * Soft notice — not a hard requirement; admins might have legitimate
 * reasons to keep introspection gated. Just makes the silent gap
 * loud.
 */
function maybe_render_introspection_notice(): void {
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || false === strpos( (string) $screen->id, 'graphql' ) ) {
		return;
	}
	if ( ! public_endpoint_is_enabled() ) {
		return;
	}

	// `public_introspection_enabled` lives on `graphql_general_settings`.
	// Same string-coercion gotcha as `graphql_ide_public_endpoint` —
	// compare against `"on"` explicitly. Debug mode also enables
	// introspection regardless of the toggle.
	$introspection_on =
		( class_exists( '\\WPGraphQL' ) && \WPGraphQL::debug() )
		|| 'on' === get_graphql_setting( 'public_introspection_enabled', 'off' );

	if ( $introspection_on ) {
		return;
	}

	?>
	<div class="notice notice-warning">
		<p>
			<strong><?php esc_html_e( 'WPGraphQL IDE — public endpoint enabled', 'wpgraphql-ide' ); ?></strong>
		</p>
		<p>
			<?php
			esc_html_e(
				'You\'ve enabled the public IDE at the GraphQL endpoint URL, but public schema introspection is disabled. Anonymous visitors will see an empty Docs Explorer and won\'t be able to discover what queries your schema accepts. Enable "Public Introspection Enabled" under General Settings if you want the IDE to be useful for unauthenticated visitors.',
				'wpgraphql-ide'
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', __NAMESPACE__ . '\\maybe_render_introspection_notice' );
