<?php

namespace WPGraphQL\Admin\Extensions;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Extensions
 *
 * @package WPGraphQL\Admin\Extensions
 *
 * phpcs:disable -- For phpstan type hinting
 * @phpstan-import-type ExtensionAuthor from \WPGraphQL\Admin\Extensions\Registry
 * @phpstan-import-type Extension from \WPGraphQL\Admin\Extensions\Registry
 *
 * @phpstan-type PopulatedExtension array{
 *   name: non-empty-string,
 *   description: non-empty-string,
 *   plugin_url: non-empty-string,
 *   support_url: non-empty-string,
 *   documentation_url: non-empty-string,
 *   repo_url?: string,
 *   author: ExtensionAuthor,
 *   installed: bool,
 *   active: bool,
 *   settings_path?: string,
 *   settings_url?: string,
 * }
 * phpcs:enable
 */
final class Extensions {
	/**
	 * The list of extensions.
	 *
	 * Filtered by `graphql_get_extensions`.
	 *
	 * @var PopulatedExtension[]
	 */
	private array $extensions;

	/**
	 * Initialize Extensions functionality for WPGraphQL.
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register the admin page for extensions.
	 */
	public function register_admin_page(): void {
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
	 */
	public function render_admin_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<div style="margin-top: 20px;" id="wpgraphql-extensions"></div>';
		echo '</div>';
	}

	/**
	 * Enqueue the necessary scripts and styles for the extensions page.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ): void {
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
	 */
	public function register_rest_routes(): void {
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
	 * @param \WP_REST_Request<array{plugin:string}> $request The REST request.
	 *
	 * @return \WP_REST_Response The REST response.
	 */
	public function activate_plugin( WP_REST_Request $request ): WP_REST_Response {
		$plugin = (string) $request->get_param( 'plugin' );
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
	 * Get the list of installed plugins
	 *
	 * @return array<string,array{
	 *  is_active: bool,
	 *  name: string,
	 *  description: string,
	 *  author: string,
	 * }> List of installed plugins, keyed by the plugin slug.
	 */
	private function get_installed_plugins(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			// @phpstan-ignore requireOnce.fileNotFound
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
	 * Sanitizes extension values before they are used.
	 *
	 * @param array<string,mixed> $extension The extension to sanitize.
	 * @return array{
	 *  name: string|null,
	 *  description: string|null,
	 *  plugin_url: string|null,
	 *  support_url: string|null,
	 *  documentation_url: string|null,
	 *  repo_url: string|null,
	 *  author: array{
	 *    name: string|null,
	 *    homepage: string|null,
	 *  },
	 * }
	 */
	private function sanitize_extension( array $extension ): array {
		return [
			'name'              => ! empty( $extension['name'] ) ? sanitize_text_field( $extension['name'] ) : null,
			'description'       => ! empty( $extension['description'] ) ? sanitize_text_field( $extension['description'] ) : null,
			'plugin_url'        => ! empty( $extension['plugin_url'] ) ? esc_url_raw( $extension['plugin_url'] ) : null,
			'support_url'       => ! empty( $extension['support_url'] ) ? esc_url_raw( $extension['support_url'] ) : null,
			'documentation_url' => ! empty( $extension['documentation_url'] ) ? esc_url_raw( $extension['documentation_url'] ) : null,
			'repo_url'          => ! empty( $extension['repo_url'] ) ? esc_url_raw( $extension['repo_url'] ) : null,
			'author'            => [
				'name'     => ! empty( $extension['author']['name'] ) ? sanitize_text_field( $extension['author']['name'] ) : null,
				'homepage' => ! empty( $extension['author']['homepage'] ) ? esc_url_raw( $extension['author']['homepage'] ) : null,
			],
		];
	}

	/**
	 * Validate an extension.
	 *
	 * Sanitization ensures that the values are correctly types, so we just need to check if the required fields are present.
	 *
	 * @param array<string,mixed> $extension The extension to validate.
	 *
	 * @return true|\WP_Error True if the extension is valid, otherwise an error.
	 *
	 * @phpstan-assert-if-true Extension $extension
	 */
	public function is_valid_extension( array $extension ) {
		$error_code = 'invalid_extension';
		// translators: First placeholder is the extension name. Second placeholder is the property that is missing from the extension.
		$error_message = __( 'Invalid extension %1$s is missing a valid value for %2$s.', 'wp-graphql' );

		// First handle the name field, since we'll use it in other error messages.
		if ( empty( $extension['name'] ) ) {
			return new \WP_Error( $error_code, esc_html__( 'Invalid extension. All extensions must have a `name`.', 'wp-graphql' ) );
		}

		// Handle the Top-Level fields.
		$required_fields = [
			'description',
			'plugin_url',
			'support_url',
			'documentation_url',
		];
		foreach ( $required_fields as $property ) {
			if ( empty( $extension[ $property ] ) ) {
				return new \WP_Error(
					$error_code,
					sprintf( $error_message, $extension['name'], $property )
				);
			}
		}

		// Ensure Author has the required name field.
		if ( empty( $extension['author']['name'] ) ) {
			return new \WP_Error(
				$error_code,
				sprintf( $error_message, $extension['name'], 'author.name' )
			);
		}

		return true;
	}

	/**
	 * Populate the extensions list with installation data.
	 *
	 * @param Extension[] $extensions The extensions to populate.
	 *
	 * @return PopulatedExtension[] The populated extensions.
	 */
	private function populate_installation_data( $extensions ): array {
		$installed_plugins = $this->get_installed_plugins();

		$populated_extensions = [];

		foreach ( $extensions as $extension ) {
			$slug                   = basename( rtrim( $extension['plugin_url'], '/' ) );
			$extension['installed'] = false;
			$extension['active']    = false;

			// If the plugin is installed, populate the installation data.
			if ( isset( $installed_plugins[ $slug ] ) ) {
				$extension['installed'] = true;
				$extension['active']    = $installed_plugins[ $slug ]['is_active'];

				if ( ! empty( $installed_plugins[ $slug ]['author'] ) ) {
					$extension['author']['name'] = $installed_plugins[ $slug ]['author'];
				}
			}

			// @todo Where does this come from?
			if ( isset( $extension['settings_path'] ) && true === $extension['active'] ) {
				$extension['settings_url'] = is_multisite() && is_network_admin()
					? network_admin_url( $extension['settings_path'] )
					: admin_url( $extension['settings_path'] );
			}

			$populated_extensions[] = $extension;
		}

		/**
		 * Sort the extensions by the following criteria:
		 * 1. Plugins grouped by WordPress.org plugins first, non WordPress.org plugins after
		 * 2. Sort by plugin name in alphabetical order within the above groups, prioritizing "WPGraphQL" authored plugins
		 */
		usort(
			$populated_extensions,
			static function ( $a, $b ) {
				if ( false !== strpos( $a['plugin_url'], 'wordpress.org' ) && false === strpos( $b['plugin_url'], 'wordpress.org' ) ) {
					return -1;
				}
				if ( false === strpos( $a['plugin_url'], 'wordpress.org' ) && false !== strpos( $b['plugin_url'], 'wordpress.org' ) ) {
					return 1;
				}
				if ( ! empty( $a['author']['name'] ) && ( 'WPGraphQL' === $a['author']['name'] && ( ! empty( $b['author']['name'] ) && 'WPGraphQL' !== $b['author']['name'] ) ) ) {
					return -1;
				}
				if ( ! empty( $a['author']['name'] ) && 'WPGraphQL' !== $a['author']['name'] && ( ! empty( $b['author']['name'] ) && 'WPGraphQL' === $b['author']['name'] ) ) {
					return 1;
				}
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $populated_extensions;
	}

	/**
	 * Get the list of WPGraphQL extensions.
	 *
	 * @return PopulatedExtension[] The list of extensions.
	 */
	public function get_extensions(): array {
		if ( ! isset( $this->extensions ) ) {
			// @todo Replace with a call to the WPGraphQL server.
			$extensions = Registry::get_extensions();

			/**
			 * Filter the list of extensions, allowing other plugins to add or remove extensions.
			 *
			 * @see Admin\Extensions\Registry::get_extensions() for the correct format of the extensions.
			 *
			 * @param array<string,Extension> $extensions The list of extensions.
			 */
			$extensions = apply_filters( 'graphql_get_extensions', $extensions );

			$valid_extensions = [];
			foreach ( $extensions as $extension ) {
				$sanitized = $this->sanitize_extension( $extension );

				if ( true === $this->is_valid_extension( $sanitized ) ) {
					$valid_extensions[] = $sanitized;
				}
			}

			// If we have valid extensions, populate the installation data.
			if ( ! empty( $valid_extensions ) ) {
				$valid_extensions = $this->populate_installation_data( $valid_extensions );
			}

			$this->extensions = $valid_extensions;
		}

		return $this->extensions;
	}
}
