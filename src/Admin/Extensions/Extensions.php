<?php

namespace WPGraphQL\Admin\Extensions;

/**
 * Class Extensions
 *
 * @package WPGraphQL\Admin\Extensions
 */
class Extensions {

	/**
	 * Initialize Extensions functionality for WPGraphQL
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Register the admin page for extensions
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_submenu_page(
			'graphiql-ide',
			__( 'WPGraphQL Extensions', 'wp-graphql' ),
			__( 'Extensions', 'wp-graphql' ),
			'manage_options',
			'wpgraphql-extensions',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Render the admin page content
	 *
	 * @return void
	 */
	public function render_admin_page() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<div id="wpgraphql-extensions"></div>';
		echo '</div>';
	}

	/**
	 * Enqueue the necessary scripts and styles for the extensions page
	 *
	 * @param string $hook_suffix The current admin page.
	 *
	 * @return void
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'graphql_page_wpgraphql-extensions' !== $hook_suffix ) {
			return;
		}

		$asset_file = include WPGRAPHQL_PLUGIN_DIR . 'build/extensions.asset.php';

		wp_enqueue_script(
			'wpgraphql-extensions',
			WPGRAPHQL_PLUGIN_URL . 'build/extensions.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_enqueue_style(
			'wpgraphql-extensions',
			WPGRAPHQL_PLUGIN_URL . 'build/extensions.css',
			[ 'wp-components' ],
			$asset_file['version']
		);

		wp_localize_script(
			'wpgraphql-extensions',
			'wpgraphqlExtensions',
			[
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'graphqlEndpoint' => trailingslashit( site_url() ) . 'index.php?' . graphql_get_endpoint(),
				'extensions'      => $this->get_extensions(),
			]
		);
	}

	/**
	 * Define the default WPGraphQL extensions
	 *
	 * @return array<mixed,string> The modified list of extensions.
	 */
	public function get_extensions() {
		$extensions = [
			[
				'slug'         => 'wpgraphql-acf',
				'name'         => 'WPGraphQL for ACF',
				'description'  => 'Adds WPGraphQL support for Advanced Custom Fields.',
                'support_link' => 'https://acf.wpgraphql.com/support/',
                'plugin_uri'   => 'https://wordpress.org/support/plugin/wpgraphql-acf/',
			],
			[
				'slug'         => 'wpgraphql-smart-cache',
				'name'         => 'WPGraphQL Smart Cache',
				'description'  => 'Smart Caching & Cache Invalidation for WPGraphQL',
                'support_link' => 'https://github.com/wp-graphql/wp-graphql-smart-cache/issues/new',
                'plugin_uri'   => 'https://wordpress.org/support/plugin/wpgraphql-acf/',
			],
		];

        // Validate plugin has correct fields

        // Handle install/activate by checking if plugin uri is .org

        // Handle if plugin states
        // installed and active
        // installed and not active
        // not installed and not active

        // Maybe have language indicate "install and activate"

		return apply_filters( 'wpgraphql_extensions', $extensions );
	}
}
