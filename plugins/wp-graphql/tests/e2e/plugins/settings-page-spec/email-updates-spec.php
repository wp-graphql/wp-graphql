<?php
/**
 * Plugin Name: WPGraphQL Email Updates Spec
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: Used only by end-to-end (e2e) tests. Blocks requests to the telemetry service so tests never send real data, records the address of each email updates subscribe request and prints them on admin pages, and clears the email updates state when the `wpgraphql_e2e_email_updates` query argument is `reset`. It lives in the settings-page-spec folder because that folder is already mounted on the tests site.
 * Version: 1.0.0
 * Author: WPGraphQL Team
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: email-updates-spec
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The option the addresses of the recorded subscribe requests are kept in.
const WPGRAPHQL_E2E_EMAIL_UPDATES_LOG = 'wpgraphql_e2e_email_updates_requests';

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'api.appsero.com' ) && false === strpos( $url, 'telemetry.wpgraphql.com' ) ) {
			return $preempt;
		}

		if ( false !== strpos( $url, '/api/v1/email/subscribe' ) ) {
			$log   = get_option( WPGRAPHQL_E2E_EMAIL_UPDATES_LOG, [] );
			$log   = is_array( $log ) ? $log : [];
			$log[] = isset( $args['body']['email'] ) ? (string) $args['body']['email'] : '';
			update_option( WPGRAPHQL_E2E_EMAIL_UPDATES_LOG, $log );
		}

		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => 202,
				'message' => 'Accepted',
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
		if ( ! isset( $_GET['wpgraphql_e2e_email_updates'] ) || 'reset' !== sanitize_key( $_GET['wpgraphql_e2e_email_updates'] ) ) {
			return;
		}

		delete_option( WPGRAPHQL_E2E_EMAIL_UPDATES_LOG );

		// The Appsero client slug is the plugin's folder name.
		delete_option( 'wp-graphql_email_updates' );

		$settings = get_option( 'graphql_general_settings' );
		if ( is_array( $settings ) ) {
			unset( $settings['email_updates'], $settings['email_updates_address'] );
			update_option( 'graphql_general_settings', $settings );
		}
	}
);

add_action(
	'admin_notices',
	static function () {
		printf(
			'<div id="wpgraphql-e2e-email-updates-requests" hidden>%s</div>',
			esc_html( (string) wp_json_encode( array_values( (array) get_option( WPGRAPHQL_E2E_EMAIL_UPDATES_LOG, [] ) ) ) )
		);
	}
);
