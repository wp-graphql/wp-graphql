<?php

namespace WPGraphQL\Admin\Extensions;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Extensions
 *
 * @package WPGraphQL\Admin\Extensions
 */
class Extensions {

	/**
	 * The list of default WPGraphQL extensions.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $extensions = [
		[
			'name'        => 'WPGraphQL IDE',
			'description' => 'A next-gen query editor for WPGraphQL.',
			'plugin_url'  => 'https://github.com/wp-graphql/wpgraphql-ide/',
			'support_url' => 'https://github.com/wp-graphql/wpgraphql-ide/issues/new/choose',
		],
		[
			'name'        => 'WPGraphQL for ACF',
			'description' => 'Adds ACF Fields and Field Groups to the WPGraphQL Schema.',
			'plugin_url'  => 'https://wordpress.org/plugins/wpgraphql-acf/',
			'support_url' => 'https://acf.wpgraphql.com/support/',
		],
		[
			'name'        => 'WPGraphQL Smart Cache',
			'description' => 'Smart Caching & Cache Invalidation for WPGraphQL.',
			'plugin_url'  => 'https://wordpress.org/plugins/wpgraphql-smart-cache/',
			'support_url' => 'https://github.com/wp-graphql/wp-graphql-smart-cache/issues/new/choose',
			'plugin_path' => 'wpgraphql-smart-cache/wp-graphql-smart-cache.php',
		],
		[
			'name'          => 'Faust.js',
			'description'   => 'WordPress plugin for working with Faust.js, the Headless WordPress Framework.',
			'plugin_url'    => 'https://wordpress.org/plugins/faustwp/',
			'support_url'   => 'https://github.com/wpengine/faustjs/issues/new/choose',
			'settings_path' => 'options-general.php?page=faustwp-settings',
		],
		[
			'name'        => 'WPGraphQL Content Blocks',
			'description' => 'Content Blocks for WPGraphQL.',
			'plugin_url'  => 'https://github.com/wpengine/wp-graphql-content-blocks',
			'support_url' => 'https://github.com/wpengine/wp-graphql-content-blocks/issues/new/choose',
		],
		[
			'name'        => 'WPGraphQL WooCommerce (WooGraphQL)',
			'description' => 'Add WooCommerce support and functionality to your WPGraphQL server.',
			'plugin_url'  => 'https://github.com/wp-graphql/wp-graphql-woocommerce',
			'support_url' => 'https://github.com/wp-graphql/wp-graphql-woocommerce/issues/new/choose',
		],
		[
			'name'        => 'WPGraphQL for Gravity Forms',
			'description' => 'GraphQL API for interacting with Gravity Forms.',
			'plugin_url'  => 'https://github.com/AxeWP/wp-graphql-gravity-forms',
			'support_url' => 'https://github.com/AxeWP/wp-graphql-gravity-forms/issues/new/choose',
		],
		[
			'name'        => 'Headless Login for WPGraphQL',
			'description' => 'A WordPress plugin that provides Headless login and authentication for WPGraphQL, supporting traditional passwords, OAuth2/OpenID Connect, JWT, and more.',
			'plugin_url'  => 'https://github.com/AxeWP/wp-graphql-headless-login',
			'support_url' => 'https://github.com/AxeWP/wp-graphql-headless-login/issues/new/choose',
		],
	];

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

		wp_enqueue_style(
			'wpgraphql-extensions',
			WPGRAPHQL_PLUGIN_URL . 'build/extensions.css',
			[ 'wp-components' ],
			$asset_file['version']
		);

		wp_enqueue_script(
			'wpgraphql-extensions',
			WPGRAPHQL_PLUGIN_URL . 'build/extensions.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
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
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response The REST response.
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
	 * @return array<int, array<string, mixed>> List of installed plugins.
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
	 * Get the list of WPGraphQL extensions.
	 *
	 * @return array<string, array<string, mixed>> List of extensions.
	 */
	public function get_extensions(): array {
		$installed_plugins = $this->get_installed_plugins();

		foreach ( $this->extensions as &$extension ) {
			$slug = basename( rtrim( $extension['plugin_url'], '/' ) );
			if ( isset( $installed_plugins[ $slug ] ) ) {
				// Merge only specific data from installed plugins, avoiding override of descriptions, etc.
				$extension['installed'] = true;
				$extension['active']    = $installed_plugins[ $slug ]['is_active'];
				$extension['author']    = $installed_plugins[ $slug ]['author'];
			} else {
				$extension['installed'] = false;
				$extension['active']    = false;
			}

			if ( isset( $extension['settings_path'] ) && true === $extension['active'] ) {
				if ( is_multisite() && is_network_admin() ) {
					$extension['settings_url'] = network_admin_url( $extension['settings_path'] );
				} else {
					$extension['settings_url'] = admin_url( $extension['settings_path'] );
				}
			}
		}

		usort(
			$this->extensions,
			static function ( $a, $b ) {
				if ( strpos( $a['plugin_url'], 'wordpress.org' ) !== false && strpos( $b['plugin_url'], 'wordpress.org' ) === false ) {
					return -1;
				}
				if ( strpos( $a['plugin_url'], 'wordpress.org' ) === false && strpos( $b['plugin_url'], 'wordpress.org' ) !== false ) {
					return 1;
				}
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return apply_filters( 'wpgraphql_extensions', $this->extensions );
	}
}
