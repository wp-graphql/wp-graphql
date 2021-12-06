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
	 * @var bool Whether GraphiQL is enabled or disabled
	 */
	protected $is_disabled = false;

	/**
	 * Initialize Admin functionality for WPGraphQL
	 *
	 * @return void
	 */
	public function init() {

		$this->is_disabled = get_option( '_graphql_disable_graphiql', false );

		/**
		 * If GraphiQL is disabled, don't set it up in the Admin
		 */
		if ( 'yes' === $this->is_disabled ) {
			return;
		}

		// Register the admin page
		add_action( 'admin_menu', [ $this, 'register_admin_page' ], 11 );
		add_action( 'admin_bar_menu', [ $this, 'register_admin_bar_menu' ], 100 );
		// Enqueue GraphiQL React App
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_graphiql' ] );

	}

	/**
	 * Registers admin bar menu
	 *
	 * @param WP_Admin_Bar $admin_bar The Admin Bar Instance
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
		add_submenu_page(
			'graphql',
			__( 'GraphiQL IDE', 'wp-graphql' ),
			__( 'GraphiQL IDE', 'wp-graphql' ),
			'manage_options',
			'graphiql-ide',
			[ $this, 'render_graphiql_admin_page' ]
		);
	}

	/**
	 * Render the markup to load GraphiQL to
	 *
	 * @return void
	 */
	public function render_graphiql_admin_page() {
		$rendered = apply_filters( 'graphql_render_admin_page', '<div class="wrap"><div id="graphiql" class="graphiql-container">Loading ...</div></div>' );
		echo $rendered;
	}

	/**
	 * Get the helpers JS
	 *
	 * @return string
	 */
	public function get_app_script_helpers() {
		return WPGRAPHQL_PLUGIN_URL . 'src/Admin/GraphiQL/js/graphiql-helpers.js';
	}

	/**
	 * Enqueues the stylesheet and js for the WPGraphiQL app
	 *
	 * @return void
	 */
	public function enqueue_graphiql() {

		/**
		 * Only enqueue the assets on the proper admin page, and only if WPGraphQL is also active
		 */
		if ( ! empty( get_current_screen() ) && strpos( get_current_screen()->id, 'graphiql' ) ) {

			$this->load_app();
			wp_enqueue_script( 'graphiql-helpers', $this->get_app_script_helpers(), [ 'jquery' ], false, true );

			/**
			 * Create a nonce
			 */
			wp_localize_script(
				'graphiql',
				'wpGraphiQLSettings',
				[
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'graphqlEndpoint' => trailingslashit( site_url() ) . 'index.php?' . \WPGraphQL\Router::$route,
				]
			);

		}
	}

	/**
	 * Loads the React App from the manifest.json
	 *
	 * @return void
	 */
	public function load_app() {

		$app_path     = WPGRAPHQL_PLUGIN_URL . 'src/Admin/GraphiQL/app/';
		$build_path   = $app_path . 'build/';
		$manifest_url = __DIR__ . '/app/build/asset-manifest.json';

		$manifest = file_get_contents( $manifest_url );

		if ( ! $manifest ) {
			return;
		}

		$files = json_decode( $manifest );

		if ( ! $files ) {
			return;
		}

		if ( ! property_exists( $files, 'entrypoints' ) ) {
			return;
		}

		$asset_files = $files->entrypoints;

		$js_files = array_filter( $asset_files, static function ( $file ) {
			return pathinfo( $file, PATHINFO_EXTENSION ) === 'js';
		} );

		$css_files = array_filter( $asset_files, static function ( $file ) {
			return pathinfo( $file, PATHINFO_EXTENSION ) === 'css';
		} );

		foreach ( $css_files as $index => $css_file ) {
			wp_enqueue_style( 'graphiql-' . $index, $build_path . $css_file, [], '1' );
		}

		// Load js files.
		foreach ( $js_files as $index => $js_file ) {
			$handle = $index > 0 ? 'graphiql-' . $index : 'graphiql';
			$src    = $build_path . $js_file;
			wp_enqueue_script( $handle, $src, [], '1', true );
		}

	}

}
