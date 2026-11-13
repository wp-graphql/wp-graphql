<?php
/**
 * Plugin Name: WPGraphQL Email Updates Spec
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: Used only by end-to-end (e2e) tests. Answers email updates subscribe requests in place of the telemetry service, so tests never send real data, records each request and prints them on admin pages. The `wpgraphql_e2e_email_updates` query argument clears the email updates state (`reset`), or makes the fake service accept every plugin (`accept`) or report that none takes email signups yet (`unavailable`). It lives in the settings-page-spec folder because that folder is already mounted on the tests site.
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

// The option the recorded subscribe requests are kept in.
const WPGRAPHQL_E2E_EMAIL_UPDATES_LOG = 'wpgraphql_e2e_email_updates_requests';

// The option that holds how the fake service answers: `accept` or `unavailable`.
const WPGRAPHQL_E2E_EMAIL_UPDATES_MODE = 'wpgraphql_e2e_email_updates_mode';

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'telemetry.wpgraphql.com/api/v1/email/subscribe' ) ) {
			return $preempt;
		}

		$sent   = json_decode( isset( $args['body'] ) && is_string( $args['body'] ) ? $args['body'] : '', true );
		$sent   = is_array( $sent ) ? $sent : [];
		$hashes = isset( $sent['hashes'] ) && is_array( $sent['hashes'] ) ? array_values( $sent['hashes'] ) : [];

		$log   = get_option( WPGRAPHQL_E2E_EMAIL_UPDATES_LOG, [] );
		$log   = is_array( $log ) ? $log : [];
		$log[] = [
			'email'  => isset( $sent['email'] ) ? (string) $sent['email'] : '',
			'hashes' => $hashes,
		];
		update_option( WPGRAPHQL_E2E_EMAIL_UPDATES_LOG, $log );

		$unavailable = 'unavailable' === get_option( WPGRAPHQL_E2E_EMAIL_UPDATES_MODE, 'accept' );

		return [
			'headers'  => [],
			'body'     => (string) wp_json_encode(
				[
					'success'  => ! $unavailable,
					'code'     => $unavailable ? 'email_unavailable' : null,
					'products' => [
						'accepted'    => $unavailable ? [] : $hashes,
						'unavailable' => $unavailable ? $hashes : [],
						'invalid'     => [],
					],
				]
			),
			'response' => [
				'code'    => $unavailable ? 422 : 202,
				'message' => '',
			],
			'cookies'  => [],
		];
	},
	// Before the usage tracking spec plugin, which answers every other telemetry request with an empty 200
	// and leaves a request that's already answered alone.
	0,
	3
);

add_action(
	'admin_init',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test-only plugin reading a flag from the URL.
		$action = isset( $_GET['wpgraphql_e2e_email_updates'] ) ? sanitize_key( $_GET['wpgraphql_e2e_email_updates'] ) : '';

		if ( in_array( $action, [ 'accept', 'unavailable' ], true ) ) {
			update_option( WPGRAPHQL_E2E_EMAIL_UPDATES_MODE, $action );
			return;
		}

		if ( 'reset' !== $action ) {
			return;
		}

		delete_option( WPGRAPHQL_E2E_EMAIL_UPDATES_LOG );
		delete_option( WPGRAPHQL_E2E_EMAIL_UPDATES_MODE );
		delete_option( 'graphql_email_updates_requests' );

		$settings = get_option( 'graphql_data_sharing_settings' );
		if ( is_array( $settings ) ) {
			foreach ( array_keys( $settings ) as $key ) {
				if ( 0 === strpos( (string) $key, 'email_updates' ) ) {
					unset( $settings[ $key ] );
				}
			}
			update_option( 'graphql_data_sharing_settings', $settings );
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
