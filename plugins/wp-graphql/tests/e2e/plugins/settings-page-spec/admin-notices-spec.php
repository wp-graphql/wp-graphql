<?php
/**
 * Plugin Name: WPGraphQL Admin Notices Spec
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: Used only by end-to-end (e2e) tests. Registers WPGraphQL admin notices when the `wpgraphql_e2e_admin_notices` query argument is set, so tests can check how notices of different lengths are laid out on WPGraphQL admin pages. It lives in the settings-page-spec folder because that folder is already mounted on the tests site.
 * Version: 1.0.0
 * Author: WPGraphQL Team
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: admin-notices-spec
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register after all plugins load, since this file can load before WPGraphQL.
add_action(
	'plugins_loaded',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test-only plugin reading a flag from the URL.
		$count = isset( $_GET['wpgraphql_e2e_admin_notices'] ) ? min( 10, absint( $_GET['wpgraphql_e2e_admin_notices'] ) ) : 0;

		if ( 0 === $count || ! function_exists( 'register_graphql_admin_notice' ) ) {
			return;
		}

		for ( $i = 1; $i <= $count; $i++ ) {
			// Make the second notice long enough to wrap onto several lines.
			$message = 2 === $i
				? str_repeat( 'This e2e test notice is long enough to wrap onto more than one line, so the IDE layout has to make room for notices of any height. ', 4 )
				: sprintf( 'E2E test notice %d.', $i );

			register_graphql_admin_notice(
				'wpgraphql-e2e-notice-' . $i,
				[
					'type'           => 'info',
					'message'        => $message,
					'is_dismissable' => false,
				]
			);
		}
	}
);
