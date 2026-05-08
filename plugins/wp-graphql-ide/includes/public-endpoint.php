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
	$enabled = (bool) get_graphql_setting(
		'graphql_ide_public_endpoint',
		false,
		'graphql_ide_settings'
	);
	return $enabled;
}

/**
 * Detect a browser GET request to the GraphQL endpoint URL. Returns
 * false for POST requests, JSON-Accept clients, or query-param API
 * calls — those keep the existing JSON handling.
 *
 * Intentionally tolerant of the Accept header: many browsers send
 * `text/html,application/xhtml+xml,...` so we look for `text/html`
 * anywhere rather than requiring it to be the only / first entry.
 */
function is_browser_html_request_to_endpoint(): bool {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return false;
	}
	if ( ! \WPGraphQL\Router::is_graphql_http_request() ) {
		return false;
	}

	$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
	if ( false === strpos( $accept, 'text/html' ) ) {
		return false;
	}

	// `?query=...` or `?variables=...` is an API call; let WPGraphQL handle it.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['query'] ) || isset( $_GET['variables'] ) ) {
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
	// Tell the IDE bootstrap to enter lite mode. The same bundle handles
	// both modes; the flag rides through `WPGRAPHQL_IDE_DATA`.
	add_filter(
		'wpgraphql_ide_localized_data',
		__NAMESPACE__ . '\\inject_lite_mode_flag',
		10,
		1
	);

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
		: 'wpgraphql-ide-app';

	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php esc_html_e( 'GraphQL IDE', 'wpgraphql-ide' ); ?></title>
	<?php wp_head(); ?>
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
 * Add `liteMode: true` to the bootstrap data. The IDE reads it on mount
 * and conditionally hides Save buttons, Saved Queries, History,
 * Document Settings, share, and topbar actions.
 *
 * @param array<string,mixed> $data
 *
 * @return array<string,mixed>
 */
function inject_lite_mode_flag( array $data ): array {
	$data['liteMode'] = true;
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
				'When enabled, visiting the GraphQL endpoint URL in a browser renders the IDE in read-only "lite" mode (no save, no saved queries, no history). Anonymous visitors see only what your public schema exposes; logged-in users get authenticated access via their session. Combine with WPGraphQL\'s existing introspection controls — and consider rate-limiting at the web-server / CDN layer — before enabling on a public site.',
				'wpgraphql-ide'
			),
			'type'    => 'checkbox',
			'default' => false,
		]
	);
}
add_action( 'graphql_register_settings', __NAMESPACE__ . '\\register_public_endpoint_setting' );
