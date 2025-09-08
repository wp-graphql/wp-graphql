<?php
/**
 * The global WPGraphQL class.
 *
 * @package WPGraphQL
 */

use WPGraphQL\Admin\Admin;
use WPGraphQL\AppContext;
use WPGraphQL\Registry\SchemaRegistry;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Router;
use WPGraphQL\Utils\InstrumentSchema;
use WPGraphQL\Utils\Preview;

/**
 * Class WPGraphQL
 *
 * This is the one true WPGraphQL class
 */
final class WPGraphQL {

	/**
	 * Stores the instance of the WPGraphQL class
	 *
	 * @var \WPGraphQL The one true WPGraphQL
	 * @since  0.0.1
	 */
	private static self $instance;

	/**
	 * Holds the Schema def
	 *
	 * @var ?\WPGraphQL\WPSchema $schema The Schema used for the GraphQL API
	 */
	protected static $schema;

	/**
	 * Holds the TypeRegistry instance
	 *
	 * @var ?\WPGraphQL\Registry\TypeRegistry $type_registry The registry that holds all GraphQL Types
	 */
	protected static $type_registry;

	/**
	 * Stores an array of allowed post types
	 *
	 * @var ?\WP_Post_Type[] allowed_post_types
	 * @since  0.0.5
	 */
	protected static ?array $allowed_post_types;

	/**
	 * Stores an array of allowed taxonomies
	 *
	 * @var ?\WP_Taxonomy[] allowed_taxonomies
	 * @since  0.0.5
	 */
	protected static ?array $allowed_taxonomies;

	/**
	 * Whether a GraphQL request is currently being processed.
	 */
	protected static bool $is_graphql_request = false;

	/**
	 * Whether an Introspection query is currently being processed.
	 */
	protected static bool $is_introspection_query = false;

	/**
	 * The instance of the WPGraphQL object
	 *
	 * @return \WPGraphQL - The one true WPGraphQL
	 * @since  0.0.1
	 */
	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->actions();
			self::$instance->filters();
			self::$instance->upgrade();
			self::$instance->deprecated();
		}

		/**
		 * Return the WPGraphQL Instance
		 */
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 * The whole idea of the singleton design pattern is that there is a single object
	 * therefore, we don't want the object to be cloned.
	 *
	 * @return void
	 * @since  0.0.1
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'The WPGraphQL class should not be cloned.', 'wp-graphql' ), '0.0.1' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @return void
	 * @since  0.0.1
	 */
	public function __wakeup() {
		// De-serializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'De-serializing instances of the WPGraphQL class is not allowed', 'wp-graphql' ), '0.0.1' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @since  0.0.1
	 */
	private function setup_constants(): void {
		graphql_setup_constants();
	}

	/**
	 * Include required files.
	 * Uses composer's autoload
	 *
	 * @since  0.0.1
	 */
	private function includes(): void {
	}

	/**
	 * Set whether the request is a GraphQL request or not
	 *
	 * @param bool $is_graphql_request
	 */
	public static function set_is_graphql_request( $is_graphql_request = false ): void {
		self::$is_graphql_request = $is_graphql_request;
	}

	/**
	 * Whether the request is a graphql request or not
	 */
	public static function is_graphql_request(): bool {
		return self::$is_graphql_request;
	}

	/**
	 * Set whether the request is an introspection query or not
	 *
	 * @param bool $is_introspection_query
	 *
	 * @since 1.28.0
	 */
	public static function set_is_introspection_query( bool $is_introspection_query = false ): void {
		self::$is_introspection_query = $is_introspection_query;
	}

	/**
	 * Whether the request is an introspection query or not (query for __type or __schema)
	 *
	 * @since 1.28.0
	 */
	public static function is_introspection_query(): bool {
		return (bool) self::$is_introspection_query;
	}

	/**
	 * Sets up actions to run at certain spots throughout WordPress and the WPGraphQL execution
	 * cycle
	 */
	private function actions(): void {
		/**
		 * Init WPGraphQL after themes have been set up,
		 * allowing for both plugins and themes to register
		 * things before graphql_init
		 */
		add_action(
			'after_setup_theme',
			static function () {
				new \WPGraphQL\Data\Config();
				$router = new Router();
				$router->init();
				$instance = self::instance();

				/**
				 * Fire off init action
				 *
				 * @param \WPGraphQL $instance The instance of the WPGraphQL class
				 */
				do_action( 'graphql_init', $instance );
			}
		);

		// Initialize the plugin url constant
		// see: https://developer.wordpress.org/reference/functions/plugins_url/#more-information
		add_action( 'init', [ $this, 'setup_plugin_url' ] );

		// Prevent WPGraphQL Insights from running
		remove_action( 'init', '\WPGraphQL\Extensions\graphql_insights_init' );

		/**
		 * Flush permalinks if the registered GraphQL endpoint has not yet been registered.
		 */
		add_action( 'wp_loaded', [ $this, 'maybe_flush_permalinks' ] );

		/**
		 * Hook in before fields resolve to check field permissions
		 */
		add_action(
			'graphql_before_resolve_field',
			[
				'\WPGraphQL\Utils\InstrumentSchema',
				'check_field_permissions',
			],
			10,
			8
		);

		// Determine what to show in graphql
		add_action( 'init_graphql_request', 'register_initial_settings', 10 );

		// Throw an exception
		add_action( 'do_graphql_request', [ $this, 'min_php_version_check' ] );
		add_action( 'do_graphql_request', [ $this, 'introspection_check' ], 10, 4 );

		// Initialize Admin functionality
		add_action( 'after_setup_theme', [ $this, 'init_admin' ] );

		add_action(
			'init_graphql_request',
			static function () {
				$tracing = new \WPGraphQL\Utils\Tracing();
				$tracing->init();

				$query_log = new \WPGraphQL\Utils\QueryLog();
				$query_log->init();
			}
		);

		// Initialize Update functionality.
		( new \WPGraphQL\Admin\Updates\Updates() )->init();
	}

	/**
	 * @param ?string                         $query     The GraphQL query
	 * @param ?string                         $operation The name of the operation
	 * @param ?array<mixed>                   $variables Variables to be passed to your GraphQL
	 *                                                   request
	 * @param \GraphQL\Server\OperationParams $params    The Operation Params. This includes any
	 *                                                   extra params,
	 *
	 * @throws \GraphQL\Error\SyntaxError
	 * @throws \Exception
	 */
	public function introspection_check( ?string $query, ?string $operation, ?array $variables, \GraphQL\Server\OperationParams $params ): void {

		if ( empty( $query ) ) {
			return;
		}

		$ast              = \GraphQL\Language\Parser::parse( $query );
		$is_introspection = false;

		\GraphQL\Language\Visitor::visit(
			$ast,
			[
				'Field' => static function ( \GraphQL\Language\AST\Node $node ) use ( &$is_introspection ) {
					if ( $node instanceof \GraphQL\Language\AST\FieldNode && ( '__schema' === $node->name->value || '__type' === $node->name->value ) ) {
						$is_introspection = true;
						return \GraphQL\Language\Visitor::stop();
					}
				},
			]
		);

		self::set_is_introspection_query( $is_introspection );
	}

	/**
	 * Check if the minimum PHP version requirement is met before execution begins.
	 *
	 * If the server is running a lower version than required, throw an exception and prevent
	 * further execution.
	 *
	 * @throws \Exception
	 */
	public function min_php_version_check(): void {
		if ( defined( 'GRAPHQL_MIN_PHP_VERSION' ) && version_compare( PHP_VERSION, GRAPHQL_MIN_PHP_VERSION, '<' ) ) {
			throw new \Exception(
				esc_html(
					sprintf(
					// translators: %1$s is the current PHP version, %2$s is the minimum required PHP version.
						__( 'The server\'s current PHP version %1$s is lower than the WPGraphQL minimum required version: %2$s', 'wp-graphql' ),
						PHP_VERSION,
						GRAPHQL_MIN_PHP_VERSION
					)
				)
			);
		}
	}

	/**
	 * Sets up the plugin url
	 */
	public function setup_plugin_url(): void {
		// Plugin Folder URL.
		if ( ! defined( 'WPGRAPHQL_PLUGIN_URL' ) ) {
			define( 'WPGRAPHQL_PLUGIN_URL', plugin_dir_url( dirname( __DIR__ ) . '/wp-graphql.php' ) );
		}
	}

	/**
	 * Determine the post_types and taxonomies, etc that should show in GraphQL.
	 */
	public function setup_types(): void {
		/**
		 * Set up the settings, post_types and taxonomies to show_in_graphql
		 */
		self::show_in_graphql();
	}

	/**
	 * Flush permalinks if the GraphQL Endpoint route isn't yet registered.
	 */
	public function maybe_flush_permalinks(): void {
		$rules = get_option( 'rewrite_rules' );
		if ( ! isset( $rules[ graphql_get_endpoint() . '/?$' ] ) ) {
			flush_rewrite_rules(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
		}
	}

	/**
	 * Setup filters
	 */
	private function filters(): void {
		// Filter the post_types and taxonomies to show in the GraphQL Schema
		$this->setup_types();

		/**
		 * Instrument the Schema to provide Resolve Hooks and sanitize Schema output
		 */
		add_filter(
			'graphql_get_type',
			[
				InstrumentSchema::class,
				'instrument_resolvers',
			],
			10,
			2
		);

		// Filter how metadata is retrieved during GraphQL requests
		add_filter(
			'get_post_metadata',
			[
				Preview::class,
				'filter_post_meta_for_previews',
			],
			10,
			4
		);

		/**
		 * Prevent WPML from redirecting within WPGraphQL requests
		 *
		 * @see https://github.com/wp-graphql/wp-graphql/issues/1626#issue-769089073
		 * @since 1.27.0
		 */
		add_filter(
			'wpml_is_redirected',
			static function ( bool $is_redirect ) {
				if ( is_graphql_request() ) {
					return false;
				}
				return $is_redirect;
			},
			10,
			1
		);
	}

	/**
	 * Private function to register deprecated functionality.
	 */
	private function deprecated(): void {
		$deprecated = new WPGraphQL\Deprecated();
		$deprecated->register();
	}

	/**
	 * Upgrade routine.
	 */
	public function upgrade(): void {
		$version = get_option( 'wp_graphql_version', null );

		// If the version is not set, this is a fresh install, not an update.
		// set the version and return.
		if ( ! $version ) {
			update_option( 'wp_graphql_version', WPGRAPHQL_VERSION );
			return;
		}

		// If the version is less than the current version, run the update routine
		if ( version_compare( $version, WPGRAPHQL_VERSION, '<' ) ) {
			$this->run_update_routines( $version );
			update_option( 'wp_graphql_version', WPGRAPHQL_VERSION );
		}
	}

	/**
	 * Executes update routines based on the previously stored version.
	 *
	 * This triggers an action that passes the previous version and new version and allows for specific actions or
	 * modifications needed to bring installations up-to-date with the current plugin version.
	 *
	 * Each update routine (callback that hooks into "graphql_do_update_routine") should handle backward compatibility as gracefully as possible.
	 *
	 * @since 1.2.3
	 * @param string|null $stored_version The version number currently stored in the database.
	 *                                    Null if no version has been previously stored.
	 */
	public function run_update_routines( ?string $stored_version = null ): void {

		// bail if the stored version is empty, or the WPGRAPHQL_VERSION constant is not set
		if ( ! defined( 'WPGRAPHQL_VERSION' ) || ! $stored_version ) {
			return;
		}

		// If the stored version is less than the current version, run the upgrade routine
		if ( version_compare( $stored_version, WPGRAPHQL_VERSION, '<' ) ) {

			// Clear the extensions cache
			$this->clear_extensions_cache();

			/**
			 * Fires the update routine.
			 *
			 * @param string $stored_version The version number currently stored in the database.
			 * @param string $new_version    The version number of the current plugin.
			 */
			do_action( 'graphql_do_update_routine', $stored_version, WPGRAPHQL_VERSION );
		}
	}

	/**
	 * Clear all caches in the "wpgraphql_extensions" cache group.
	 */
	public function clear_extensions_cache(): void {
		global $wp_object_cache;

		if ( isset( $wp_object_cache->cache['wpgraphql_extensions'] ) ) {
			foreach ( $wp_object_cache->cache['wpgraphql_extensions'] as $key => $value ) {
				wp_cache_delete( $key, 'wpgraphql_extensions' );
			}
		}
	}

	/**
	 * Initialize admin functionality.
	 */
	public function init_admin(): void {
		$admin = new Admin();
		$admin->init();
	}

	/**
	 * This sets up built-in post_types and taxonomies to show in the GraphQL Schema.
	 *
	 * @since  0.0.2
	 */
	public static function show_in_graphql(): void {
		add_filter( 'register_post_type_args', [ self::class, 'setup_default_post_types' ], 10, 2 );
		add_filter( 'register_taxonomy_args', [ self::class, 'setup_default_taxonomies' ], 10, 2 );

		// Run late so the user can filter the args themselves.
		add_filter( 'register_post_type_args', [ self::class, 'register_graphql_post_type_args' ], 99, 2 );
		add_filter( 'register_taxonomy_args', [ self::class, 'register_graphql_taxonomy_args' ], 99, 2 );
	}

	/**
	 * Sets up the default post types to show_in_graphql.
	 *
	 * @param array<string,mixed> $args      Array of arguments for registering a post type.
	 * @param string              $post_type Post type key.
	 *
	 * @return array<string,mixed>
	 */
	public static function setup_default_post_types( $args, $post_type ) {
		// Adds GraphQL support for attachments.
		if ( 'attachment' === $post_type ) {
			$args['show_in_graphql']     = true;
			$args['graphql_single_name'] = 'mediaItem';
			$args['graphql_plural_name'] = 'mediaItems';
			$args['graphql_description'] = __( 'Represents uploaded media, including images, videos, documents, and audio files.', 'wp-graphql' );
		} elseif ( 'page' === $post_type ) { // Adds GraphQL support for pages.
			$args['show_in_graphql']     = true;
			$args['graphql_single_name'] = 'page';
			$args['graphql_plural_name'] = 'pages';
			$args['graphql_description'] = __( 'A standalone content entry generally used for static, non-chronological content such as "About Us" or "Contact" pages.', 'wp-graphql' );
		} elseif ( 'post' === $post_type ) { // Adds GraphQL support for posts.
			$args['show_in_graphql']     = true;
			$args['graphql_single_name'] = 'post';
			$args['graphql_plural_name'] = 'posts';
			$args['graphql_description'] = __( 'A chronological content entry typically used for blog posts, news articles, or similar date-based content.', 'wp-graphql' );
		}

		return $args;
	}

	/**
	 * Sets up the default taxonomies to show_in_graphql.
	 *
	 * @param array<string,mixed> $args     Array of arguments for registering a taxonomy.
	 * @param string              $taxonomy Taxonomy key.
	 *
	 * @return array<string,mixed>
	 * @since 1.12.0
	 */
	public static function setup_default_taxonomies( $args, $taxonomy ) {
		// Adds GraphQL support for categories.
		if ( 'category' === $taxonomy ) {
			$args['show_in_graphql']     = true;
			$args['graphql_single_name'] = 'category';
			$args['graphql_plural_name'] = 'categories';
			$args['graphql_description'] = __( 'A taxonomy term that classifies content. Categories support hierarchy and can be used to create a nested structure.', 'wp-graphql' );
		} elseif ( 'post_tag' === $taxonomy ) { // Adds GraphQL support for tags.
			$args['show_in_graphql']     = true;
			$args['graphql_single_name'] = 'tag';
			$args['graphql_plural_name'] = 'tags';
			$args['graphql_description'] = __( 'A taxonomy term used to organize and classify content. Tags do not have a hierarchy and are generally used for more specific classifications.', 'wp-graphql' );
		} elseif ( 'post_format' === $taxonomy ) { // Adds GraphQL support for post formats.
			$args['show_in_graphql']     = true;
			$args['graphql_single_name'] = 'postFormat';
			$args['graphql_plural_name'] = 'postFormats';
			$args['graphql_description'] = __( 'A standardized classification system for content presentation styles. These formats can be used to display content differently based on type, such as "standard", "gallery", "video", etc.', 'wp-graphql' );
		}

		return $args;
	}

	/**
	 * Set the GraphQL Post Type Args and pass them through a filter.
	 *
	 * @param array<string,mixed> $args           The graphql specific args for the post type
	 * @param string              $post_type_name The name of the post type being registered
	 *
	 * @return array<string,mixed>
	 * @throws \Exception
	 * @since 1.12.0
	 */
	public static function register_graphql_post_type_args( array $args, string $post_type_name ): array {
		// Bail early if the post type is hidden from the WPGraphQL schema.
		if ( empty( $args['show_in_graphql'] ) ) {
			return $args;
		}

		$graphql_args = self::get_default_graphql_type_args();

		/**
		 * Filters the graphql args set on a post type
		 *
		 * @param array<string,mixed> $args           The graphql specific args for the post type
		 * @param string              $post_type_name The name of the post type being registered
		 */
		$graphql_args = apply_filters( 'register_graphql_post_type_args', $graphql_args, $post_type_name );

		return wp_parse_args( $args, $graphql_args );
	}

	/**
	 * Set the GraphQL Taxonomy Args and pass them through a filter.
	 *
	 * @param array<string,mixed> $args          The graphql specific args for the taxonomy
	 * @param string              $taxonomy_name The name of the taxonomy being registered
	 *
	 * @return array<string,mixed>
	 * @throws \Exception
	 * @since 1.12.0
	 */
	public static function register_graphql_taxonomy_args( array $args, string $taxonomy_name ): array {
		// Bail early if the taxonomy  is hidden from the WPGraphQL schema.
		if ( empty( $args['show_in_graphql'] ) ) {
			return $args;
		}

		$graphql_args = self::get_default_graphql_type_args();

		/**
		 * Filters the graphql args set on a taxonomy
		 *
		 * @param array<string,mixed> $args          The graphql specific args for the taxonomy
		 * @param string              $taxonomy_name The name of the taxonomy being registered
		 */
		$graphql_args = apply_filters( 'register_graphql_taxonomy_args', $graphql_args, $taxonomy_name );

		return wp_parse_args( $args, $graphql_args );
	}

	/**
	 * This sets the post type /taxonomy GraphQL properties.
	 *
	 * @since 1.12.0
	 *
	 * @return array{
	 *   graphql_kind: 'interface'|'object'|'union',
	 *   graphql_resolve_type: ?callable,
	 *   graphql_interfaces: string[],
	 *   graphql_connections: string[],
	 *   graphql_union_types: string[],
	 *   graphql_register_root_field: bool,
	 *   graphql_register_root_connection: bool,
	 * }
	 */
	public static function get_default_graphql_type_args(): array {
		return [
			// The "kind" of GraphQL type to register. Can be `interface`, `object`, or `union`.
			'graphql_kind'                     => 'object',
			// The callback used to resolve the type. Only used if `graphql_kind` is an `interface` or `union`.
			'graphql_resolve_type'             => null,
			// An array of custom interfaces the type should implement.
			'graphql_interfaces'               => [],
			// An array of default interfaces the type should exclude.
			'graphql_exclude_interfaces'       => [],
			// An array of custom connections the type should implement.
			'graphql_connections'              => [],
			// An array of default connection field names the type should exclude.
			'graphql_exclude_connections'      => [],
			// An array of possible type the union can resolve to. Only used if `graphql_kind` is a `union`.
			'graphql_union_types'              => [],
			// Whether to register default connections to the schema.
			'graphql_register_root_field'      => true,
			'graphql_register_root_connection' => true,
		];
	}

	/**
	 * Get the post types that are allowed to be used in GraphQL.
	 *
	 * This gets all post_types that are set to show_in_graphql, but allows for external code
	 * (plugins/theme) to filter the list of allowed_post_types to add/remove additional post_types
	 *
	 * @param 'names'|'objects'   $output Optional. The type of output to return. Accepts post type 'names' or 'objects'. Default 'names'.
	 * @param array<string,mixed> $args   Optional. Arguments to filter allowed post types
	 *
	 * @return string[]|\WP_Post_Type[]
	 * @phpstan-return ( $output is 'objects' ? \WP_Post_Type[] : string[] )
	 *
	 * @since  0.0.4
	 * @since  1.8.1 adds $output as first param, and stores post type objects in class property.
	 */
	public static function get_allowed_post_types( $output = 'names', $args = [] ): array {
		// Support deprecated param order.
		if ( is_array( $output ) ) {
			_deprecated_argument( __METHOD__, '1.8.1', 'Passing `$args` to the first parameter will no longer be supported in the next major version of WPGraphQL.' );
			$args   = $output;
			$output = 'names';
		}

		// Initialize array of allowed post type objects.
		if ( empty( self::$allowed_post_types ) ) {
			/**
			 * Get all post types objects.
			 *
			 * @var \WP_Post_Type[] $post_type_objects
			 */
			$post_type_objects = get_post_types(
				[ 'show_in_graphql' => true ],
				'objects'
			);

			$post_type_names = wp_list_pluck( $post_type_objects, 'name' );

			/**
			 * Pass through a filter to allow the post_types to be modified.
			 * For example if a certain post_type should not be exposed to the GraphQL API.
			 *
			 * @param string[]        $post_type_names   Array of post type names.
			 * @param \WP_Post_Type[] $post_type_objects Array of post type objects.
			 *
			 * @since 1.8.1 add $post_type_objects parameter.
			 * @since 0.0.2
			 */
			$allowed_post_type_names = apply_filters( 'graphql_post_entities_allowed_post_types', $post_type_names, $post_type_objects );

			// Filter the post type objects if the list of allowed types have changed.
			$post_type_objects = array_filter(
				$post_type_objects,
				static function ( $obj ) use ( $allowed_post_type_names ) {
					if ( empty( $obj->graphql_plural_name ) && ! empty( $obj->graphql_single_name ) ) {
						$obj->graphql_plural_name = $obj->graphql_single_name;
					}

					/**
					 * Validate that the post_types have a graphql_single_name and graphql_plural_name
					 */
					if ( empty( $obj->graphql_single_name ) || empty( $obj->graphql_plural_name ) ) {
						graphql_debug(
							sprintf(
							/* translators: %s will replaced with the registered type */
								__( 'The "%s" post_type isn\'t configured properly to show in GraphQL. It needs a "graphql_single_name" and a "graphql_plural_name"', 'wp-graphql' ),
								$obj->name
							),
							[
								'invalid_post_type' => $obj,
							]
						);
						return false;
					}

					return in_array( $obj->name, $allowed_post_type_names, true );
				}
			);

			self::$allowed_post_types = $post_type_objects;
		}

		/**
		 * Filter the list of allowed post types either by the provided args or to only return an array of names.
		 */
		if ( ! empty( $args ) || 'names' === $output ) {
			$field = 'names' === $output ? 'name' : false;

			return wp_filter_object_list( self::$allowed_post_types, $args, 'and', $field );
		}

		return self::$allowed_post_types;
	}

	/**
	 * Get the taxonomies that are allowed to be used in GraphQL.
	 * This gets all taxonomies that are set to "show_in_graphql" but allows for external code
	 * (plugins/themes) to filter the list of allowed_taxonomies to add/remove additional
	 * taxonomies
	 *
	 * @param 'names'|'objects'   $output Optional. The type of output to return. Accepts taxonomy 'names' or 'objects'. Default 'names'.
	 * @param array<string,mixed> $args   Optional. Arguments to filter allowed taxonomies.
	 *
	 * @return string[]|\WP_Taxonomy[]
	 * @phpstan-return ( $output is 'objects' ? \WP_Taxonomy[] : string[] )
	 * @since  0.0.4
	 */
	public static function get_allowed_taxonomies( $output = 'names', $args = [] ): array {

		// Initialize array of allowed post type objects.
		if ( empty( self::$allowed_taxonomies ) ) {
			/**
			 * Get all post types objects.
			 *
			 * @var \WP_Taxonomy[] $tax_objects
			 */
			$tax_objects = get_taxonomies(
				[ 'show_in_graphql' => true ],
				'objects'
			);

			$tax_names = wp_list_pluck( $tax_objects, 'name' );

			/**
			 * Pass through a filter to allow the taxonomies to be modified.
			 * For example if a certain taxonomy should not be exposed to the GraphQL API.
			 *
			 * @param string[]       $tax_names   Array of taxonomy names
			 * @param \WP_Taxonomy[] $tax_objects Array of taxonomy objects.
			 *
			 * @since 1.8.1 add $tax_names and $tax_objects parameters.
			 * @since 0.0.2
			 */
			$allowed_tax_names = apply_filters( 'graphql_term_entities_allowed_taxonomies', $tax_names, $tax_objects );

			$tax_objects = array_filter(
				$tax_objects,
				static function ( $obj ) use ( $allowed_tax_names ) {
					if ( empty( $obj->graphql_plural_name ) && ! empty( $obj->graphql_single_name ) ) {
						$obj->graphql_plural_name = $obj->graphql_single_name;
					}

					/**
					 * Validate that the post_types have a graphql_single_name and graphql_plural_name
					 */
					if ( empty( $obj->graphql_single_name ) || empty( $obj->graphql_plural_name ) ) {
						graphql_debug(
							sprintf(
							/* translators: %s will replaced with the registered taxonomy */
								__( 'The "%s" taxonomy isn\'t configured properly to show in GraphQL. It needs a "graphql_single_name" and a "graphql_plural_name"', 'wp-graphql' ),
								$obj->name
							),
							[
								'invalid_taxonomy' => $obj,
							]
						);
						return false;
					}

					return in_array( $obj->name, $allowed_tax_names, true );
				}
			);

			self::$allowed_taxonomies = $tax_objects;
		}

		$taxonomies = self::$allowed_taxonomies;
		/**
		 * Filter the list of allowed taxonomies either by the provided args or to only return an array of names.
		 */
		if ( ! empty( $args ) || 'names' === $output ) {
			$field = 'names' === $output ? 'name' : false;

			$taxonomies = wp_filter_object_list( $taxonomies, $args, 'and', $field );
		}

		return $taxonomies;
	}

	/**
	 * Allow Schema to be cleared.
	 */
	public static function clear_schema(): void {
		self::$type_registry      = null;
		self::$schema             = null;
		self::$allowed_post_types = null;
		self::$allowed_taxonomies = null;
	}

	/**
	 * Returns the Schema as defined by static registrations throughout
	 * the WP Load.
	 *
	 * @return \WPGraphQL\WPSchema
	 *
	 * @throws \Exception
	 */
	public static function get_schema() {
		if ( ! isset( self::$schema ) ) {
			$schema_registry = new SchemaRegistry();
			$schema          = $schema_registry->get_schema();

			/**
			 * Generate & Filter the schema.
			 *
			 * @param \WPGraphQL\WPSchema $schema The executable Schema that GraphQL executes against
			 * @param \WPGraphQL\AppContext $app_context Object The AppContext object containing all of the
			 * information about the context we know at this point
			 *
			 * @since 0.0.5
			 */
			self::$schema = apply_filters( 'graphql_schema', $schema, self::get_app_context() );
		}

		/**
		 * Fire an action when the Schema is returned
		 */
		do_action( 'graphql_get_schema', self::$schema );

		/**
		 * Return the Schema after applying filters
		 */
		return self::$schema;
	}

	/**
	 * Whether WPGraphQL is operating in Debug mode
	 */
	public static function debug(): bool {
		if ( defined( 'GRAPHQL_DEBUG' ) ) {
			$enabled = (bool) GRAPHQL_DEBUG;
		} else {
			$enabled = get_graphql_setting( 'debug_mode_enabled', 'off' );
			$enabled = 'on' === $enabled;
		}

		/**
		 * @param bool $enabled Whether GraphQL Debug is enabled or not
		 */
		return (bool) apply_filters( 'graphql_debug_enabled', $enabled );
	}

	/**
	 * Returns the type registry, instantiating it if it doesn't exist.
	 *
	 * @return \WPGraphQL\Registry\TypeRegistry
	 *
	 * @throws \Exception
	 */
	public static function get_type_registry() {
		if ( ! isset( self::$type_registry ) ) {
			$type_registry = new TypeRegistry();

			/**
			 * Generate & Filter the schema.
			 *
			 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The TypeRegistry for the API
			 * @param \WPGraphQL\AppContext $app_context Object The AppContext object containing all of the
			 * information about the context we know at this point
			 *
			 * @since 0.0.5
			 */
			self::$type_registry = apply_filters( 'graphql_type_registry', $type_registry, self::get_app_context() );
		}

		/**
		 * Fire an action when the Type Registry is returned
		 */
		do_action( 'graphql_get_type_registry', self::$type_registry );

		/**
		 * Return the Schema after applying filters
		 */
		return self::$type_registry;
	}

	/**
	 * Return the static schema if there is one.
	 */
	public static function get_static_schema(): ?string {
		$schema_file = WPGRAPHQL_PLUGIN_DIR . 'schema.graphql';

		if ( ! file_exists( $schema_file ) ) {
			return null;
		}

		$schema = file_get_contents( WPGRAPHQL_PLUGIN_DIR . 'schema.graphql' );

		return ! empty( $schema ) ? $schema : null;
	}

	/**
	 * Get the AppContext for use in passing down the Resolve Tree
	 */
	public static function get_app_context(): AppContext {
		/**
		 * Configure the app_context which gets passed down to all the resolvers.
		 *
		 * @since 0.0.4
		 */
		$app_context           = new AppContext();
		$app_context->viewer   = wp_get_current_user();
		$app_context->root_url = get_bloginfo( 'url' );
		$app_context->request  = ! empty( $_REQUEST ) ? $_REQUEST : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return $app_context;
	}
}
