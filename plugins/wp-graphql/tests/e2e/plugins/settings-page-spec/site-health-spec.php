<?php
/**
 * Plugin Name: WPGraphQL Site Health Spec
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: Used only by end-to-end (e2e) tests. WPGraphQL only adds its Site Health recommendations on production sites, and the tests site is a local one, so while this plugin is active the recommendations are added as if the site were in production. It lives in the settings-page-spec folder because that folder is already mounted on the tests site.
 * Version: 1.0.0
 * Author: WPGraphQL Team
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: site-health-spec
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Register after all plugins load, since this file can load before WPGraphQL.
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( \WPGraphQL\Admin\SiteHealth\SiteHealth::class ) ) {
			return;
		}

		// After WPGraphQL's own filter, which adds nothing on a local site.
		add_filter(
			'site_status_tests',
			static function ( $tests ) {
				$site_health = new class() extends \WPGraphQL\Admin\SiteHealth\SiteHealth {
					protected function get_environment_type(): string {
						return 'production';
					}
				};

				return $site_health->register_tests( $tests );
			},
			20
		);
	}
);
