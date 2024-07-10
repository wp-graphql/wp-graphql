<?php

namespace WPGraphQL\Admin\Extensions;

use WP_REST_Response;
use WP_REST_Request;

/**
 * Class Extensions
 *
 * @package WPGraphQL\Admin\Extensions
 */
class Extensions {

	/**
	 * Initialize Extensions functionality for WPGraphQL.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register the admin page for extensions.
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
	 * Render the admin page content.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<div style="margin-top: 20px;" id="wpgraphql-extensions"></div>';
		echo '</div>';
	}

	/**
	 * Enqueue the necessary scripts and styles for the extensions page.
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
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'graphqlEndpoint'  => trailingslashit( site_url() ) . 'index.php?' . graphql_get_endpoint(),
				'extensions'       => $this->get_extensions(),
				'pluginsInstalled' => $this->get_installed_plugins(),
			]
		);
	}

	/**
	 * Register custom REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'wp/v2',
			'/plugins/(?P<plugin>.+)',
			[
				'methods'             => 'PUT',
				'callback'            => [ $this, 'activate_plugin' ],
				'permission_callback' => static function () {
					return current_user_can( 'activate_plugins' );
				},
				'args'                => [
					'plugin' => [
						'required'          => true,
						'validate_callback' => static function ( $param, $request, $key ) {
							return is_string( $param );
						},
					],
				],
			]
		);
	}

	/**
	 * Activate a plugin.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function activate_plugin( WP_REST_Request $request ) {
		$plugin = $request->get_param( 'plugin' );
		$result = activate_plugin( $plugin );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				[
					'status'  => 'error',
					'message' => $result->get_error_message(),
				],
				500
			);
		}

		return new WP_REST_Response(
			[
				'status' => 'active',
				'plugin' => $plugin,
			],
			200
		);
	}

	/**
	 * Get the list of installed plugins.
	 *
	 * @return array List of installed plugin slugs.
	 */
	private function get_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins           = get_plugins();
		$active_plugins    = get_option( 'active_plugins' );
		$installed_plugins = [];

		foreach ( $plugins as $plugin_path => $plugin_info ) {
			$slug = dirname( $plugin_path );

			$installed_plugins[ $slug ] = [
				'is_active'   => in_array( $plugin_path, $active_plugins, true ),
				'name'        => $plugin_info['Name'],
				'description' => $plugin_info['Description'],
				'author'      => $plugin_info['Author'],
			];
		}

		return $installed_plugins;
	}

	/**
	 * Fetch plugin information from WordPress.org.
	 *
	 * @param string $plugin_url The plugin URL.
	 *
	 * @return array The plugin information.
	 */
	private function fetch_plugin_info( $plugin_url ) {
		$slug     = basename( rtrim( $plugin_url, '/' ) );
		$response = wp_remote_get( "https://api.wordpress.org/plugins/info/1.0/{$slug}.json" );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		return json_decode( $body, true );
	}

	/**
	 * Define the default WPGraphQL extensions.
	 *
	 * @return array<mixed,string> The modified list of extensions.
	 */
	public function get_extensions() {
		$extensions = [
			[
				'plugin_url'   => 'https://github.com/wp-graphql/wpgraphql-ide/',
				'support_link' => 'https://github.com/wp-graphql/wpgraphql-ide/issues/new',
				'plugin_path'  => 'wpgraphql-ide/wpgraphql-ide.php'
			],
			[
				'plugin_url'   => 'https://wordpress.org/plugins/wpgraphql-acf/',
				'support_link' => 'https://github.com/wp-graphql/wpgraphql-acf/issues/new',
				'plugin_path'  => 'wpgraphql-acf/wpgraphql-acf.php'
			],
			[
				'plugin_url'   => 'https://wordpress.org/plugins/wpgraphql-smart-cache/',
				'support_link' => 'https://github.com/wp-graphql/wp-graphql-smart-cache/issues/new',
				'plugin_path'  => 'wpgraphql-smart-cache/wp-graphql-smart-cache.php'
			],
			[
				'plugin_url'   => 'https://github.com/wpengine/wp-graphql-content-blocks',
				'support_link' => 'https://github.com/wpengine/wp-graphql-content-blocks/issues',
				'name'         => 'WPGraphQL Content Blocks',
				'description'  => 'Content Blocks for WPGraphQL.',
				'author'       => 'WP Engine',
				'plugin_path'  => 'wp-graphql-content-blocks/wp-graphql-content-blocks.php'
			],
		];

		$installed_plugins = $this->get_installed_plugins();

		foreach ( $extensions as &$extension ) {
			$host = wp_parse_url( $extension['plugin_url'], PHP_URL_HOST );

			if ( 'wordpress.org' === $host ) {
				$plugin_info = $this->fetch_plugin_info( $extension['plugin_url'] );
				$extension   = array_merge(
					$extension,
					[
						'name'        => $plugin_info['name'] ?? '',
						'description' => $plugin_info['short_description'] ?? '',
						'author'      => $plugin_info['author'] ?? '',
						'installed'   => false,
						'active'      => false,
					]
				);
			}

			$slug = basename( rtrim( $extension['plugin_url'], '/' ) );
			if ( isset( $installed_plugins[ $slug ] ) ) {
				$extension = array_merge(
					$extension,
					$installed_plugins[ $slug ],
					[
						'installed' => true,
						'active'    => $installed_plugins[ $slug ]['is_active'],
					]
				);
			}
		}

		usort($extensions, function ($a, $b) {
			if (strpos($a['plugin_url'], 'wordpress.org') !== false && strpos($b['plugin_url'], 'wordpress.org') === false) {
				return -1;
			}
			if (strpos($a['plugin_url'], 'wordpress.org') === false && strpos($b['plugin_url'], 'wordpress.org') !== false) {
				return 1;
			}
			return strcmp($a['name'], $b['name']);
		});

		return apply_filters( 'wpgraphql_extensions', $extensions );
	}
}
