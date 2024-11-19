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
	 * Path to the JSON file with the extensions' data.
	 *
	 * @var string
	 */
	private $extensions_file_path;

	/**
	 * The schema for the extensions data defined in ./schemas/extensions.json.
	 *
	 * @var object
	 */
	private $schema;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	protected $extensions;

	/**
	 * Constructor.
	 *
	 * @param string $file_path Path to the JSON file.
	 */
	public function __construct( string $file_path = __DIR__ . '/extensions.json' ) {
		$this->extensions_file_path = $file_path;
	}

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
	 * @param \WP_REST_Request<array<string, mixed>> $request The REST request.
	 * @return \WP_REST_Response The REST response.
	 */
	public function activate_plugin( WP_REST_Request $request ): WP_REST_Response {
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
	 * @return array<string, array<string, mixed>> List of installed plugins.
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
	 * Load the list of WPGraphQL extensions from a JSON file.
	 *
	 * @return array<string, array<string, mixed>>|null The list of extensions, or null if the file can't be read.
	 */
	private function load_extensions_from_file(): ?array {
		// Check if the file exists and is readable
		if ( ! file_exists( $this->extensions_file_path ) || ! is_readable( $this->extensions_file_path ) ) {
			return null;
		}

		// check if the extensions exist in cache, using the file's last modified time as the cache key
		$cache_group          = 'wpgraphql_extensions';
		$extensions_cache_key = 'wpgraphql_extensions_' . filemtime( $this->extensions_file_path );
		$cached_extensions    = wp_cache_get( $extensions_cache_key, $cache_group );

		if ( false !== $cached_extensions ) {
			return $cached_extensions;
		}

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$contents = file_get_contents( $this->extensions_file_path );

		if ( false === $contents ) {
			return null; // Handle case where file_get_contents fails
		}

		$data = json_decode( $contents, true );

		// Check for JSON decoding errors
		if ( empty( $data['extensions'] ) || JSON_ERROR_NONE !== json_last_error() ) {
			return null;
		}

		// Cache the extensions data
		wp_cache_set( $extensions_cache_key, $data['extensions'], $cache_group );

		return $data['extensions'];
	}

	/**
	 * Validate an extension based on the schemas/extensions.json schema.
	 *
	 * @param array<string, array<string, mixed>> $extension The extension to validate.
	 *
	 * @return mixed|bool|string True if the extension is valid, otherwise an error message.
	 */
	public function is_valid_extension( array $extension ) {

		// convert the extension to an object for validation
		$extension         = (object) $extension;
		$extension->author = isset( $extension->author ) ? (object) $extension->author : null;

		// Load the schema if it hasn't been loaded yet
		if ( null === $this->schema ) {
			$schema_file = file_get_contents( WPGRAPHQL_PLUGIN_DIR . 'schemas/single-extension.json' );
			if ( ! $schema_file ) {
				return false;
			}
			$this->schema = json_decode( $schema_file );
		}

		// Validate the extension data based on the loaded schema
		$validator = new \JsonSchema\Validator();
		$validator->validate( $extension, $this->schema );

		if ( ! $validator->isValid() ) {
			return $validator->getErrors()[0]['message'] ?? 'Unknown error';
		}

		return true;
	}

	/**
	 * Get the list of WPGraphQL extensions.
	 *
	 * @return array<int, array<string, mixed>> List of extensions.
	 */
	public function get_extensions(): array {

		$valid_extensions = [];

		if ( null === $this->extensions ) {

			// Allow filtering the extensions before they are loaded
			$pre_filtered_extensions = apply_filters( 'graphql_pre_get_extensions', null );
			if ( null !== $pre_filtered_extensions ) {
				$extensions = $pre_filtered_extensions;
			} else {
				$extensions = $this->load_extensions_from_file() ?? [];
			}

			$installed_plugins = $this->get_installed_plugins();

			// Filter the list of extensions, allowing other plugins to add their own extensions
			$extensions = apply_filters( 'graphql_get_extensions', $extensions );

			foreach ( $extensions as $extension ) {
				if ( true === $this->is_valid_extension( $extension ) ) {
					$valid_extensions[] = $extension;
				}
			}

			// Validate and remove invalid extensions
			$this->extensions = ! empty( $valid_extensions ) ? $valid_extensions : [];
		}

		if ( empty( $this->extensions ) ) {
			return [];
		}

		foreach ( $this->extensions as $extension ) {
			$slug = basename( rtrim( $extension['plugin_url'], '/' ) );
			if ( isset( $installed_plugins[ $slug ] ) ) {
				$extension['installed'] = true;
				$extension['active']    = $installed_plugins[ $slug ]['is_active'];
				$extension['author']    = $installed_plugins[ $slug ]['author'];
			} else {
				$extension['installed'] = false;
				$extension['active']    = false;
			}

			if ( isset( $extension['settings_path'] ) && true === $extension['active'] ) {
				$extension['settings_url'] = is_multisite() && is_network_admin()
					? network_admin_url( $extension['settings_path'] )
					: admin_url( $extension['settings_path'] );
			}
		}

		// Sort criteria is currently as follows:
		// 1. Plugins grouped by WordPress.org plugins first, non WordPress.org plugins after
		// 2. Sort by plugin name in alphabetical order within the above groups, prioritizing "WPGraphQL" authored plugins
		usort(
			$this->extensions,
			static function ( $a, $b ) {
				if ( false !== strpos( $a['plugin_url'], 'wordpress.org' ) && false === strpos( $b['plugin_url'], 'wordpress.org' ) ) {
					return -1;
				}
				if ( false === strpos( $a['plugin_url'], 'wordpress.org' ) && false !== strpos( $b['plugin_url'], 'wordpress.org' ) ) {
					return 1;
				}
				if ( 'WPGraphQL' === $a['author']['name'] && 'WPGraphQL' !== $b['author']['name'] ) {
					return -1;
				}
				if ( 'WPGraphQL' !== $a['author']['name'] && 'WPGraphQL' === $b['author']['name'] ) {
					return 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $this->extensions;
	}
}
