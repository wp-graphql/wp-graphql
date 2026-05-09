<?php
/**
 * Optional public IDE at the GraphQL endpoint URL.
 *
 * When the `graphql_ide_public_endpoint` setting is enabled, browser
 * GETs to the GraphQL endpoint URL render the IDE shell instead of
 * the JSON API. API clients (POST, `Accept: application/json`,
 * query-param requests) still fall through to WPGraphQL's normal
 * handler — only Accept-text/html browser GETs are intercepted.
 *
 * IDE-capable visitors (`manage_graphql_ide`) get the full IDE here.
 * Anyone else gets "endpoint mode": editor + variables/headers +
 * execute + docs explorer. Save / saved queries / history / document
 * settings / share / topbar actions / the auth toggle (when anonymous)
 * are all hidden.
 *
 * Default: off. Site admins must explicitly opt in via WPGraphQL →
 * IDE Settings — exposing a clickable schema browser to the public web
 * shouldn't happen on a plugin update.
 *
 * @package WPGraphQLIDE
 */

namespace WPGraphQLIDE;

const SETTINGS_SECTION = 'graphql_ide_settings';
const SETTING_NAME     = 'graphql_ide_public_endpoint';
const BODY_CLASS       = 'wpgraphql-ide-public-endpoint';

/**
 * Whether a `graphql_ide_settings` checkbox is checked.
 *
 * WPGraphQL stores checkbox fields as the string `"on"` (checked) or
 * `"off"` (unchecked). PHP's `(bool) "off"` is *true* — non-empty
 * strings are truthy — so a `(bool)` cast turns "unchecked" into
 * "enabled" and the toggle never disables anything. This wrapper
 * compares against `"on"` explicitly.
 *
 * @param string $name    Field name, without the section prefix.
 * @param string $section Settings section. Defaults to the IDE section.
 */
function ide_setting_is_on( string $name, string $section = SETTINGS_SECTION ): bool {
	if ( ! function_exists( 'get_graphql_setting' ) ) {
		return false;
	}
	return 'on' === get_graphql_setting( $name, 'off', $section );
}

/**
 * Whether the public-endpoint IDE setting is enabled. Cached statically
 * within a request because both the route check and the bootstrap
 * injection consult it.
 */
function public_endpoint_is_enabled(): bool {
	static $enabled = null;
	if ( null === $enabled ) {
		$enabled = ide_setting_is_on( SETTING_NAME );
	}
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
	return false !== stripos( $accept, 'text/html' );
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
	add_filter( 'wpgraphql_ide_localized_data', __NAMESPACE__ . '\\inject_public_endpoint_data' );

	// No admin bar — the page IS the IDE. The admin-bar trigger would
	// also try to mount its own IDE instance into a sibling div.
	add_filter( 'show_admin_bar', '__return_false' );

	// Reuse the dedicated-page enqueue function so script handles,
	// dependency graph, and `WPGRAPHQL_IDE_DATA` bootstrap are
	// identical. The `bypass_dedicated_page_gate` argument tells it to
	// skip the capability + admin-bar checks that protect every other
	// call site.
	add_action(
		'wp_enqueue_scripts',
		static function () {
			enqueue_react_app_with_styles( true );
		}
	);

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );

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
	 * Inline style emitted *after* `wp_head()` so theme / WP styles
	 * can't win the cascade and collapse the IDE to its content
	 * height. Selectors are scoped to our body class so they don't
	 * leak to other pages.
	 */
	?>
	<style>
		html, body.<?php echo esc_attr( BODY_CLASS ); ?> {
			height: 100%; margin: 0; padding: 0; overflow: hidden;
		}
		body.<?php echo esc_attr( BODY_CLASS ); ?> #<?php echo esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ); ?>,
		body.<?php echo esc_attr( BODY_CLASS ); ?> #wpgraphql-ide-app {
			height: 100vh; width: 100vw;
		}
	</style>
</head>
<body class="<?php echo esc_attr( BODY_CLASS ); ?>">
	<div id="<?php echo esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ); ?>"></div>
	<?php wp_footer(); ?>
</body>
</html>
	<?php
}

/**
 * Build the bootstrap-data delta for the public-endpoint render.
 * Filtered into `WPGRAPHQL_IDE_DATA` via `wpgraphql_ide_localized_data`.
 *
 * Always sets:
 *   - `renderStandalone`: true. Signed-in or not, the public-endpoint
 *     surface is full-page (no slide-up drawer).
 *
 * IDE-capable visitors (`manage_graphql_ide`) get the full IDE — only
 * the `isUserLoggedIn` flag is added for them. Everyone else gets
 * endpoint mode + the sign-in URL with a redirect-back so they can
 * sign in and re-land on the same shell as a full-IDE user.
 *
 * @param array<string,mixed> $data
 *
 * @return array<string,mixed>
 */
function inject_public_endpoint_data( array $data ): array {
	$data['renderStandalone'] = true;

	if ( current_user_can( 'manage_graphql_ide' ) ) {
		$data['isUserLoggedIn'] = true;
		return $data;
	}

	$data['endpointMode']   = true;
	$data['isUserLoggedIn'] = is_user_logged_in();
	$data['loginUrl']       = wp_login_url( current_request_url() );
	return $data;
}

/**
 * Reconstruct the full URL of the current request so we can pass it to
 * `wp_login_url()` as the post-login redirect target. The user lands
 * back on the same `/?graphql` shell after login.
 */
function current_request_url(): string {
	$scheme = is_ssl() ? 'https://' : 'http://';
	$host   = (string) ( $_SERVER['HTTP_HOST'] ?? '' );
	$path   = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	return $scheme . $host . $path;
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
		SETTINGS_SECTION,
		[
			'name'    => SETTING_NAME,
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
 * IDE endpoint is enabled but public introspection isn't — anonymous
 * visitors will see an empty Docs Explorer in that combination.
 *
 * Soft notice; admins can still keep introspection gated for legitimate
 * reasons. Just makes the silent gap loud.
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

	// Debug mode forces introspection regardless of the toggle.
	$introspection_on = ( class_exists( '\\WPGraphQL' ) && \WPGraphQL::debug() )
		|| ide_setting_is_on( 'public_introspection_enabled', 'graphql_general_settings' );

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
