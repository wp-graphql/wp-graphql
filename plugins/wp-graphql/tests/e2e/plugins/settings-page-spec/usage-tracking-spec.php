<?php
/**
 * Plugin Name: WPGraphQL Usage Tracking Spec
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: Used only by end-to-end (e2e) tests. Blocks usage tracking requests so tests never send real data, prints the site's real opt-in state on admin pages, and sets that state when the `wpgraphql_e2e_tracking` query argument is `yes` or `no`. It lives in the settings-page-spec folder because that folder is already mounted on the tests site.
 * Version: 1.0.0
 * Author: WPGraphQL Team
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: usage-tracking-spec
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The Appsero client slug is the plugin's folder name.
const WPGRAPHQL_E2E_TRACKING_OPTION = 'wp-graphql_allow_tracking';

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'api.appsero.com' ) && false === strpos( $url, 'telemetry.wpgraphql.com' ) ) {
			return $preempt;
		}

		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
		];
	},
	1,
	3
);

add_action(
	'admin_init',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test-only plugin reading a flag from the URL.
		$state = isset( $_GET['wpgraphql_e2e_tracking'] ) ? sanitize_key( $_GET['wpgraphql_e2e_tracking'] ) : '';

		// Set the option directly, the way an opt-in through the admin notice before the toggle existed left it.
		if ( in_array( $state, [ 'yes', 'no' ], true ) ) {
			update_option( WPGRAPHQL_E2E_TRACKING_OPTION, $state );
		}
	}
);

add_action(
	'admin_notices',
	static function () {
		printf(
			'<div id="wpgraphql-e2e-tracking-state" hidden>%s</div>',
			esc_html( get_option( WPGRAPHQL_E2E_TRACKING_OPTION, 'no' ) )
		);
	}
);
