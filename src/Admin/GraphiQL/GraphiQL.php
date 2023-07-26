<?php

namespace WPGraphQL\Admin\GraphiQL;

use WP_Admin_Bar;

/**
 * Class GraphiQL
 *
 * Sets up GraphiQL in the WordPress Admin
 *
 * @package WPGraphQL\Admin\GraphiQL
 */
class GraphiQL {

	/**
	 * @var bool Whether GraphiQL is enabled
	 */
	protected $is_enabled = false;

	/**
	 * Initialize Admin functionality for WPGraphQL
	 *
	 * @return void
	 */
	public function init() {

		$this->is_enabled = get_graphql_setting( 'graphiql_enabled' ) === 'off' ? false : true;

		/**
		 * If GraphiQL is disabled, don't set it up in the Admin
		 */
		if ( ! $this->is_enabled ) {
			return;
		}

		// Register the admin page
		add_action( 'admin_menu', [ $this, 'register_admin_page' ], 9 );
		add_action( 'admin_bar_menu', [ $this, 'register_admin_bar_menu' ], 100 );
		// Enqueue GraphiQL React App
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_graphiql' ] );

		/**
		 * Enqueue extension styles and scripts
		 *
		 * These extensions are part of WPGraphiQL core, but were built in a way
		 * to showcase how extension APIs can be used to extend WPGraphiQL
		 */
		add_action( 'enqueue_graphiql_extension', [ $this, 'graphiql_enqueue_query_composer' ] );
		add_action( 'enqueue_graphiql_extension', [ $this, 'graphiql_enqueue_auth_switch' ] );
		add_action( 'enqueue_graphiql_extension', [ $this, 'graphiql_enqueue_fullscreen_toggle' ] );

	}

	/**
	 * Registers admin bar menu
	 *
	 * @param \WP_Admin_Bar $admin_bar The Admin Bar Instance
	 *
	 * @return void
	 */
	public function register_admin_bar_menu( WP_Admin_Bar $admin_bar ) {

		if ( ! current_user_can( 'manage_options' ) || 'off' === get_graphql_setting( 'show_graphiql_link_in_admin_bar' ) ) {
			return;
		}

		$icon_url = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTcuNDY4IDMwMi42NmwtMTQuMzc2LTguMyAxNjAuMTUtMjc3LjM4IDE0LjM3NiA4LjN6Ii8+PHBhdGggZmlsbD0iI0UxMDA5OCIgZD0iTTM5LjggMjcyLjJoMzIwLjN2MTYuNkgzOS44eiIvPjxwYXRoIGZpbGw9IiNFMTAwOTgiIGQ9Ik0yMDYuMzQ4IDM3NC4wMjZsLTE2MC4yMS05Mi41IDguMy0xNC4zNzYgMTYwLjIxIDkyLjV6TTM0NS41MjIgMTMyLjk0N2wtMTYwLjIxLTkyLjUgOC4zLTE0LjM3NiAxNjAuMjEgOTIuNXoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTQuNDgyIDEzMi44ODNsLTguMy0xNC4zNzUgMTYwLjIxLTkyLjUgOC4zIDE0LjM3NnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzQyLjU2OCAzMDIuNjYzbC0xNjAuMTUtMjc3LjM4IDE0LjM3Ni04LjMgMTYwLjE1IDI3Ny4zOHpNNTIuNSAxMDcuNWgxNi42djE4NUg1Mi41ek0zMzAuOSAxMDcuNWgxNi42djE4NWgtMTYuNnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMjAzLjUyMiAzNjdsLTcuMjUtMTIuNTU4IDEzOS4zNC04MC40NSA3LjI1IDEyLjU1N3oiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzY5LjUgMjk3LjljLTkuNiAxNi43LTMxIDIyLjQtNDcuNyAxMi44LTE2LjctOS42LTIyLjQtMzEtMTIuOC00Ny43IDkuNi0xNi43IDMxLTIyLjQgNDcuNy0xMi44IDE2LjggOS43IDIyLjUgMzEgMTIuOCA0Ny43TTkwLjkgMTM3Yy05LjYgMTYuNy0zMSAyMi40LTQ3LjcgMTIuOC0xNi43LTkuNi0yMi40LTMxLTEyLjgtNDcuNyA5LjYtMTYuNyAzMS0yMi40IDQ3LjctMTIuOCAxNi43IDkuNyAyMi40IDMxIDEyLjggNDcuN00zMC41IDI5Ny45Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi44IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMzA5LjEgMTM3Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi43IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMjAwIDM5NS44Yy0xOS4zIDAtMzQuOS0xNS42LTM0LjktMzQuOSAwLTE5LjMgMTUuNi0zNC45IDM0LjktMzQuOSAxOS4zIDAgMzQuOSAxNS42IDM0LjkgMzQuOSAwIDE5LjItMTUuNiAzNC45LTM0LjkgMzQuOU0yMDAgNzRjLTE5LjMgMC0zNC45LTE1LjYtMzQuOS0zNC45IDAtMTkuMyAxNS42LTM0LjkgMzQuOS0zNC45IDE5LjMgMCAzNC45IDE1LjYgMzQuOSAzNC45IDAgMTkuMy0xNS42IDM0LjktMzQuOSAzNC45Ii8+PC9zdmc+';

		$icon = sprintf( '<span class="custom-icon" style="
    background-image:url(\'%s\'); float:left; width:22px !important; height:22px !important;
    margin-left: 5px !important; margin-top: 5px !important; margin-right: 5px !important;
    "></span>', $icon_url );

		$admin_bar->add_menu( [
			'id'    => 'graphiql-ide',
			'title' => $icon . __( 'GraphiQL IDE', 'wp-graphql' ),
			'href'  => trailingslashit( admin_url() ) . 'admin.php?page=graphiql-ide',
		] );

	}

	/**
	 * Register the admin page as a subpage
	 *
	 * @return void
	 */
	public function register_admin_page() {

		// Top level menu page should be labeled GraphQL
		add_menu_page(
			__( 'GraphQL', 'wp-graphql' ),
			__( 'GraphQL', 'wp-graphql' ),
			'manage_options',
			'graphiql-ide',
			[ $this, 'render_graphiql_admin_page' ],
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTcuNDY4IDMwMi42NmwtMTQuMzc2LTguMyAxNjAuMTUtMjc3LjM4IDE0LjM3NiA4LjN6Ii8+PHBhdGggZmlsbD0iI0UxMDA5OCIgZD0iTTM5LjggMjcyLjJoMzIwLjN2MTYuNkgzOS44eiIvPjxwYXRoIGZpbGw9IiNFMTAwOTgiIGQ9Ik0yMDYuMzQ4IDM3NC4wMjZsLTE2MC4yMS05Mi41IDguMy0xNC4zNzYgMTYwLjIxIDkyLjV6TTM0NS41MjIgMTMyLjk0N2wtMTYwLjIxLTkyLjUgOC4zLTE0LjM3NiAxNjAuMjEgOTIuNXoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTQuNDgyIDEzMi44ODNsLTguMy0xNC4zNzUgMTYwLjIxLTkyLjUgOC4zIDE0LjM3NnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzQyLjU2OCAzMDIuNjYzbC0xNjAuMTUtMjc3LjM4IDE0LjM3Ni04LjMgMTYwLjE1IDI3Ny4zOHpNNTIuNSAxMDcuNWgxNi42djE4NUg1Mi41ek0zMzAuOSAxMDcuNWgxNi42djE4NWgtMTYuNnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMjAzLjUyMiAzNjdsLTcuMjUtMTIuNTU4IDEzOS4zNC04MC40NSA3LjI1IDEyLjU1N3oiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzY5LjUgMjk3LjljLTkuNiAxNi43LTMxIDIyLjQtNDcuNyAxMi44LTE2LjctOS42LTIyLjQtMzEtMTIuOC00Ny43IDkuNi0xNi43IDMxLTIyLjQgNDcuNy0xMi44IDE2LjggOS43IDIyLjUgMzEgMTIuOCA0Ny43TTkwLjkgMTM3Yy05LjYgMTYuNy0zMSAyMi40LTQ3LjcgMTIuOC0xNi43LTkuNi0yMi40LTMxLTEyLjgtNDcuNyA5LjYtMTYuNyAzMS0yMi40IDQ3LjctMTIuOCAxNi43IDkuNyAyMi40IDMxIDEyLjggNDcuN00zMC41IDI5Ny45Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi44IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMzA5LjEgMTM3Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi43IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMjAwIDM5NS44Yy0xOS4zIDAtMzQuOS0xNS42LTM0LjktMzQuOSAwLTE5LjMgMTUuNi0zNC45IDM0LjktMzQuOSAxOS4zIDAgMzQuOSAxNS42IDM0LjkgMzQuOSAwIDE5LjItMTUuNiAzNC45LTM0LjkgMzQuOU0yMDAgNzRjLTE5LjMgMC0zNC45LTE1LjYtMzQuOS0zNC45IDAtMTkuMyAxNS42LTM0LjkgMzQuOS0zNC45IDE5LjMgMCAzNC45IDE1LjYgMzQuOSAzNC45IDAgMTkuMy0xNS42IDM0LjktMzQuOSAzNC45Ii8+PC9zdmc+'
		);

		// Sub menu  should be labeled GraphiQL IDE
		add_submenu_page(
			'graphiql-ide',
			__( 'GraphiQL IDE', 'wp-graphql' ),
			__( 'GraphiQL IDE', 'wp-graphql' ),
			'manage_options',
			'graphiql-ide',
			[ $this, 'render_graphiql_admin_page' ]
		);
	}

	/**
	 * Render the markup to load GraphiQL to.
	 *
	 * @return void
	 */
	public function render_graphiql_admin_page() {
		$rendered = apply_filters( 'graphql_render_admin_page', '<div class="wrap" dir="ltr"><div id="graphiql" class="graphiql-container">Loading ...</div></div>' );

		echo wp_kses_post( $rendered );
	}

	/**
	 * Enqueues the stylesheet and js for the WPGraphiQL app
	 *
	 * @return void
	 */
	public function enqueue_graphiql() {

		if ( null === get_current_screen() || ! strpos( get_current_screen()->id, 'graphiql' ) ) {
			return;
		}

		$asset_file = include WPGRAPHQL_PLUGIN_DIR . 'build/index.asset.php';

		// Setup some globals that can be used by GraphiQL
		// and extending scripts
		wp_enqueue_script(
			'wp-graphiql', // Handle.
			WPGRAPHQL_PLUGIN_URL . 'build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		$app_asset_file = include WPGRAPHQL_PLUGIN_DIR . 'build/app.asset.php';

		wp_enqueue_script(
			'wp-graphiql-app', // Handle.
			WPGRAPHQL_PLUGIN_URL . 'build/app.js',
			array_merge( [ 'wp-graphiql' ], $app_asset_file['dependencies'] ),
			$app_asset_file['version'],
			true
		);

		wp_enqueue_style(
			'wp-graphiql-app',
			WPGRAPHQL_PLUGIN_URL . 'build/app.css',
			[ 'wp-components' ],
			$app_asset_file['version']
		);

		wp_localize_script(
			'wp-graphiql',
			'wpGraphiQLSettings',
			[
				'nonce'             => wp_create_nonce( 'wp_rest' ),
				'graphqlEndpoint'   => trailingslashit( site_url() ) . 'index.php?' . graphql_get_endpoint(),
				'avatarUrl'         => 0 !== get_current_user_id() ? get_avatar_url( get_current_user_id() ) : null,
				'externalFragments' => apply_filters( 'graphiql_external_fragments', [] ),
			]
		);

		// Extensions looking to extend GraphiQL can hook in here,
		// after the window object is established, but before the App renders
		do_action( 'enqueue_graphiql_extension' );

	}

	/**
	 * Enqueue the GraphiQL Auth Switch extension, which adds a button to the GraphiQL toolbar
	 * that allows the user to switch between the logged in user and the current user
	 *
	 * @return void
	 */
	public function graphiql_enqueue_auth_switch() {

		$auth_switch_asset_file = include WPGRAPHQL_PLUGIN_DIR . 'build/graphiqlAuthSwitch.asset.php';

		wp_enqueue_script(
			'wp-graphiql-auth-switch', // Handle.
			WPGRAPHQL_PLUGIN_URL . 'build/graphiqlAuthSwitch.js',
			array_merge( [ 'wp-graphiql', 'wp-graphiql-app' ], $auth_switch_asset_file['dependencies'] ),
			$auth_switch_asset_file['version'],
			true
		);
	}

	/**
	 * Enqueue the Query Composer extension, which adds a button to the GraphiQL toolbar
	 * that allows the user to open the Query Composer and compose a query with a form-based UI
	 *
	 * @return void
	 */
	public function graphiql_enqueue_query_composer() {

		// Enqueue the assets for the Explorer before enqueueing the app,
		// so that the JS in the exporter that hooks into the app will be available
		// by time the app is enqueued
		$composer_asset_file = include WPGRAPHQL_PLUGIN_DIR . 'build/graphiqlQueryComposer.asset.php';

		wp_enqueue_script(
			'wp-graphiql-query-composer', // Handle.
			WPGRAPHQL_PLUGIN_URL . 'build/graphiqlQueryComposer.js',
			array_merge( [ 'wp-graphiql', 'wp-graphiql-app' ], $composer_asset_file['dependencies'] ),
			$composer_asset_file['version'],
			true
		);

		wp_enqueue_style(
			'wp-graphiql-query-composer',
			WPGRAPHQL_PLUGIN_URL . 'build/graphiqlQueryComposer.css',
			[ 'wp-components' ],
			$composer_asset_file['version']
		);

	}

	/**
	 * Enqueue the GraphiQL Fullscreen Toggle extension, which adds a button to the GraphiQL toolbar
	 * that allows the user to toggle the fullscreen mode
	 *
	 * @return void
	 */
	public function graphiql_enqueue_fullscreen_toggle() {

		$fullscreen_toggle_asset_file = include WPGRAPHQL_PLUGIN_DIR . 'build/graphiqlFullscreenToggle.asset.php';

		wp_enqueue_script(
			'wp-graphiql-fullscreen-toggle', // Handle.
			WPGRAPHQL_PLUGIN_URL . 'build/graphiqlFullscreenToggle.js',
			array_merge( [ 'wp-graphiql', 'wp-graphiql-app' ], $fullscreen_toggle_asset_file['dependencies'] ),
			$fullscreen_toggle_asset_file['version'],
			true
		);

		wp_enqueue_style(
			'wp-graphiql-fullscreen-toggle',
			WPGRAPHQL_PLUGIN_URL . 'build/graphiqlFullscreenToggle.css',
			[ 'wp-components' ],
			$fullscreen_toggle_asset_file['version']
		);

	}

}
