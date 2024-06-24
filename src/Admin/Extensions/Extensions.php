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
		add_action( 'graphql_register_types', [ $this, 'register_graphql_fields' ] );

		// Define the extensions through a filter
		add_filter( 'wpgraphql_extensions_list', [ $this, 'define_extensions' ] );
	}

	/**
	 * Register the admin page for extensions
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_submenu_page(
			'graphql',
			esc_html__( 'WPGraphQL Extensions', 'wp-graphql' ),
			esc_html__( 'Extensions', 'wp-graphql' ),
			'manage_options',
			'wpgraphql-extensions',
			[ $this, 'render_admin_page' ]
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
	 * Render the admin page content
	 *
	 * @return void
	 */
	public function render_admin_page() {
		echo '<div id="wpgraphql-extensions"></div>';
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

		wp_enqueue_script(
			'wpgraphql-extensions',
			plugins_url( 'build/extensions.js', __FILE__ ),
			[ 'wp-element', 'wp-components', 'wp-i18n' ],
			filemtime( plugin_dir_path( __FILE__ ) . 'build/extensions.js' ),
			true
		);

		wp_enqueue_style(
			'wpgraphql-extensions',
			plugins_url( 'build/extensions.css', __FILE__ ),
			[ 'wp-components' ],
			filemtime( plugin_dir_path( __FILE__ ) . 'build/extensions.css' )
		);

		wp_localize_script( 'wpgraphql-extensions', 'wpgraphqlExtensions', [
			'graphqlUrl' => esc_url_raw( graphql_endpoint() ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		] );
	}

	/**
	 * Register GraphQL fields for extensions
	 *
	 * @return void
	 */
	public function register_graphql_fields() {
		register_graphql_field( 'RootQuery', 'wpgraphqlExtensions', [
			'type' => 'WPGraphQLExtensions',
			'description' => __( 'Fetch WPGraphQL extensions', 'wp-graphql' ),
			'resolve' => function() {
				$extensions = apply_filters( 'wpgraphql_extensions_list', [] );
				return [ 'extensions' => $extensions ];
			}
		] );

		register_graphql_object_type( 'WPGraphQLExtensions', [
			'description' => __( 'WPGraphQL Extensions', 'wp-graphql' ),
			'fields' => [
				'extensions' => [
					'type' => [ 'list_of' => 'WPGraphQLExtension' ],
					'description' => __( 'List of WPGraphQL extensions', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_object_type( 'WPGraphQLExtension', [
			'description' => __( 'WPGraphQL Extension', 'wp-graphql' ),
			'fields' => [
				'id' => [
					'type' => 'ID',
					'description' => __( 'The ID of the extension', 'wp-graphql' ),
				],
				'name' => [
					'type' => 'String',
					'description' => __( 'The name of the extension', 'wp-graphql' ),
				],
				'description' => [
					'type' => 'String',
					'description' => __( 'The description of the extension', 'wp-graphql' ),
				],
			],
		] );
	}

	/**
	 * Define the default WPGraphQL extensions
	 *
	 * @param array $extensions The existing list of extensions.
	 * @return array The modified list of extensions.
	 */
	public function define_extensions( $extensions ) {
		$default_extensions = [
			[
				'id'          => 1,
				'name'        => 'WPGraphQL for ACF',
				'description' => 'Adds WPGraphQL support for Advanced Custom Fields.',
			],
			[
				'id'          => 2,
				'name'        => 'WPGraphQL JWT Authentication',
				'description' => 'Adds JWT Authentication support to WPGraphQL.',
			],
		];

		return array_merge( $extensions, $default_extensions );
	}

	/**
	 * Register a new WPGraphQL extension
	 *
	 * @param array $extension The extension details.
	 */
	public static function register_extension( $extension ) {
		add_filter( 'wpgraphql_extensions_list', function( $extensions ) use ( $extension ) {
			$extensions[] = $extension;
			return $extensions;
		} );
	}
}
