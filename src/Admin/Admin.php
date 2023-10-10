<?php

namespace WPGraphQL\Admin;

use WPGraphQL\Admin\GraphiQL\GraphiQL;
use WPGraphQL\Admin\Settings\Settings;

/**
 * Class Admin
 *
 * @package WPGraphQL\Admin
 */
class Admin {

	/**
	 * Whether Admin Pages are enabled or not
	 *
	 * @var boolean
	 */
	protected $admin_enabled;

	/**
	 * Whether GraphiQL is enabled or not
	 *
	 * @var boolean
	 */
	protected $graphiql_enabled;

	/**
	 * @var \WPGraphQL\Admin\Settings\Settings
	 */
	protected $settings;

	/**
	 * Initialize Admin functionality for WPGraphQL
	 *
	 * @return void
	 */
	public function init() {

		// Determine whether the admin pages should show or not.
		// Default is enabled.
		$this->admin_enabled    = apply_filters( 'graphql_show_admin', true );
		$this->graphiql_enabled = apply_filters( 'graphql_enable_graphiql', get_graphql_setting( 'graphiql_enabled', true ) );

		// This removes the menu page for WPGraphiQL as it's now built into WPGraphQL
		if ( $this->graphiql_enabled ) {
			add_action(
				'admin_menu',
				static function () {
					remove_menu_page( 'wp-graphiql/wp-graphiql.php' );
				}
			);
		}

		// If the admin is disabled, prevent admin from being scaffolded.
		if ( false === $this->admin_enabled ) {
			return;
		}

		$this->settings = new Settings();
		$this->settings->init();

		if ( 'on' === $this->graphiql_enabled || true === $this->graphiql_enabled ) {
			global $graphiql;
			$graphiql = new GraphiQL();
			$graphiql->init();
		}
	}
}
