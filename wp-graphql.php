<?php
/**
 * Plugin Name: WP GraphQL
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Version: 1.1.2
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 5.0
 * Tested up to: 5.4
 * Requires PHP: 7.1
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package  WPGraphQL
 * @category Core
 * @author   WPGraphQL
 * @version  1.1.2
 */

// Exit if accessed directly.
use GraphQL\Error\UserError;
use WPGraphQL\Admin\Admin;
use WPGraphQL\AppContext;
use WPGraphQL\Registry\SchemaRegistry;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\WPSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * If the codeception remote coverage file exists, require it.
 *
 * This file should only exist locally or when CI bootstraps the environment for testing
 */
if ( file_exists( __DIR__ . '/c3.php' ) ) {
	require_once __DIR__ . '/c3.php';
}

/**
 * Run this function when WPGraphQL is de-activated
 */
register_deactivation_hook( __FILE__, 'graphql_deactivation_callback' );
register_activation_hook( __FILE__, 'graphql_activation_callback' );

/**
 * This plugin brings the power of GraphQL (http://graphql.org/) to WordPress.
 *
 * This plugin is based on the hard work of Jason Bahl, Ryan Kanner, Hughie Devore and Peter Pak of
 * Digital First Media
 * (https://github.com/dfmedia), and Edwin Cromley of BE-Webdesign
 * (https://github.com/BE-Webdesign).
 *
 * The plugin is built on top of the graphql-php library by Webonyx
 * (https://github.com/webonyx/graphql-php) and makes use of the graphql-relay-php library by Ivome
 * (https://github.com/ivome/graphql-relay-php/)
 *
 * Special thanks to Digital First Media (http://digitalfirstmedia.com) for allocating development
 * resources to push the project forward.
 *
 * Some of the concepts and code are based on the WordPress Rest API.
 * Much love to the folks (https://github.com/orgs/WP-API/people) that put their blood, sweat and
 * tears into the WP-API project, as it's been huge in moving WordPress forward as a platform and
 * helped inspire and direct the development of WPGraphQL.
 *
 * Much love to Facebook® for open sourcing the GraphQL spec (https://facebook.github.io/graphql/)
 * and maintaining the JS reference implementation (https://github.com/graphql/graphql-js)
 *
 * Much love to Apollo (Meteor Development Group) for their work on driving GraphQL forward and
 * providing a lot of insight into how to design GraphQL schemas, etc. Check them out:
 * http://www.apollodata.com/
 */

if ( ! class_exists( 'WPGraphQL' ) ) :

	/**
	 * This is the one true WPGraphQL class
	 */
	final class WPGraphQL {

		/**
		 * Stores the instance of the WPGraphQL class
		 *
		 * @var WPGraphQL The one true WPGraphQL
		 * @since  0.0.1
		 */
		private static $instance;

		/**
		 * Holds the Schema def
		 *
		 * @var mixed|null|WPSchema $schema The Schema used for the GraphQL API
		 */
		protected static $schema;

		/**
		 * Holds the TypeRegistry instance
		 *
		 * @var mixed|null|TypeRegistry $type_registry The registry that holds all GraphQL Types
		 */
		protected static $type_registry;

		/**
		 * Stores an array of allowed post types
		 *
		 * @var array allowed_post_types
		 * @since  0.0.5
		 */
		public static $allowed_post_types;

		/**
		 * Stores an array of allowed taxonomies
		 *
		 * @var array allowed_taxonomies
		 * @since  0.0.5
		 */
		public static $allowed_taxonomies;

		/**
		 * @var boolean
		 */
		protected static $is_graphql_request;

		/**
		 * The instance of the WPGraphQL object
		 *
		 * @return object|WPGraphQL - The one true WPGraphQL
		 * @since  0.0.1
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) || ! ( self::$instance instanceof WPGraphQL ) ) {
				self::$instance = new WPGraphQL();
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->actions();
				self::$instance->filters();
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
		 * @since  0.0.1
		 * @return void
		 */
		public function __clone() {

			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'The WPGraphQL class should not be cloned.', 'wp-graphql' ), '0.0.1' );

		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @since  0.0.1
		 * @return void
		 */
		public function __wakeup() {

			// De-serializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'De-serializing instances of the WPGraphQL class is not allowed', 'wp-graphql' ), '0.0.1' );

		}

		/**
		 * Setup plugin constants.
		 *
		 * @since  0.0.1
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
				define( 'WPGRAPHQL_VERSION', '1.1.2' );
			}

			// Plugin Folder Path.
			if ( ! defined( 'WPGRAPHQL_PLUGIN_DIR' ) ) {
				define( 'WPGRAPHQL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'WPGRAPHQL_PLUGIN_URL' ) ) {
				define( 'WPGRAPHQL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}

			// Plugin Root File.
			if ( ! defined( 'WPGRAPHQL_PLUGIN_FILE' ) ) {
				define( 'WPGRAPHQL_PLUGIN_FILE', __FILE__ );
			}

			// Whether to autoload the files or not.
			if ( ! defined( 'WPGRAPHQL_AUTOLOAD' ) ) {
				define( 'WPGRAPHQL_AUTOLOAD', true );
			}

			// The minimum version of PHP this plugin requires to work properly
			if ( ! defined( 'GRAPHQL_MIN_PHP_VERSION' ) ) {
				define( 'GRAPHQL_MIN_PHP_VERSION', '7.1' );
			}

		}

		/**
		 * Include required files.
		 * Uses composer's autoload
		 *
		 * @since  0.0.1
		 * @return void
		 */
		private function includes() {

			/**
			 * WPGRAPHQL_AUTOLOAD can be set to "false" to prevent the autoloader from running.
			 * In most cases, this is not something that should be disabled, but some environments
			 * may bootstrap their dependencies in a global autoloader that will autoload files
			 * before we get to this point, and requiring the autoloader again can trigger fatal errors.
			 *
			 * The codeception tests are an example of an environment where adding the autoloader again causes issues
			 * so this is set to false for tests.
			 */
			if ( defined( 'WPGRAPHQL_AUTOLOAD' ) && true === WPGRAPHQL_AUTOLOAD ) {

				if ( ! file_exists( WPGRAPHQL_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
					wp_die( __( 'WPGraphQL has been installed without dependencies. Try installing from WordPress.org or run "composer install" from the plugin directory to install dependencies', 'wp-graphql' ) );
				}

				// Autoload Required Classes.
				require_once WPGRAPHQL_PLUGIN_DIR . 'vendor/autoload.php';

			}

			// Required non-autoloaded classes.
			require_once WPGRAPHQL_PLUGIN_DIR . 'access-functions.php';
			require_once WPGRAPHQL_PLUGIN_DIR . 'activation.php';
			require_once WPGRAPHQL_PLUGIN_DIR . 'deactivation.php';

		}

		/**
		 * Set whether the request is a GraphQL request or not
		 *
		 * @param bool $is_graphql_request
		 *
		 * @return void
		 */
		public static function set_is_graphql_request( $is_graphql_request = false ) {
			self::$is_graphql_request = $is_graphql_request;
		}

		/**
		 * @return bool
		 */
		public static function is_graphql_request() {
			return self::$is_graphql_request;
		}

		/**
		 * Sets up actions to run at certain spots throughout WordPress and the WPGraphQL execution cycle
		 *
		 * @return void
		 */
		private function actions() {

			$tracker = new \WPGraphQL\Telemetry\Tracker( 'WPGraphQL' );
			add_action( 'plugins_loaded', [ $tracker, 'init' ] );
			add_action( 'graphql_activate', function() use ( $tracker ) {
				$tracker->track_event( 'PLUGIN_ACTIVATE' );
			} );
			add_action( 'graphql_deactivate', function() use ( $tracker ) {
				$tracker->track_event( 'PLUGIN_DEACTIVATE' );
				$tracker->delete_timestamp();
			} );

			/**
			 * Init WPGraphQL after themes have been setup,
			 * allowing for both plugins and themes to register
			 * things before graphql_init
			 */
			add_action(
				'after_setup_theme',
				function() {

					new \WPGraphQL\Data\Config();
					new \WPGraphQL\Router();

					/**
					 * Fire off init action
					 *
					 * @param WPGraphQL $instance The instance of the WPGraphQL class
					 */
					do_action( 'graphql_init', self::$instance );
				}
			);

			// Prevent WPGraphQL Insights from running
			// @phpstan-ignore-next-line
			remove_action( 'init', '\WPGraphQL\Extensions\graphql_insights_init' );

			/**
			 * Flush permalinks if the registered GraphQL endpoint has not yet been registered.
			 */
			add_action( 'wp_loaded', [ $this, 'maybe_flush_permalinks' ] );

			/**
			 * Hook in before fields resolve to check field permissions
			 */
			add_action( 'graphql_before_resolve_field', [
				'\WPGraphQL\Utils\InstrumentSchema',
				'check_field_permissions',
			], 10, 8 );

			// Determine what to show in graphql
			add_action( 'init_graphql_request', 'register_initial_settings', 10 );
			add_action( 'init', [ $this, 'setup_types' ], 10 );

			// Throw an exception
			add_action( 'do_graphql_request', [ $this, 'min_php_version_check' ] );

			// Initialize Admin functionality
			add_action( 'after_setup_theme', [ $this, 'init_admin' ] );

			$tracing = new \WPGraphQL\Utils\Tracing();
			$tracing->init();

			$query_log = new \WPGraphQL\Utils\QueryLog();
			$query_log->init();

		}

		/**
		 * Check if the minimum PHP version requirement is met before execution begins.
		 *
		 * If the server is running a lower version than required, throw an exception and prevent
		 * further execution.
		 *
		 * @throws Exception
		 *
		 * @return void
		 */
		public function min_php_version_check() {

			if ( defined( 'GRAPHQL_MIN_PHP_VERSION' ) && version_compare( PHP_VERSION, GRAPHQL_MIN_PHP_VERSION, '<' ) ) {
				throw new \Exception( sprintf( __( 'The server\'s current PHP version %1$s is lower than the WPGraphQL minimum required version: %2$s', 'wp-graphql' ), PHP_VERSION, GRAPHQL_MIN_PHP_VERSION ) );
			}

		}

		/**
		 * Determine the post_types and taxonomies, etc that should show in GraphQL
		 *
		 * @return void
		 */
		public function setup_types() {

			/**
			 * Setup the settings, post_types and taxonomies to show_in_graphql
			 */
			self::show_in_graphql();
		}

		/**
		 * Flush permalinks if the GraphQL Endpoint route isn't yet registered
		 *
		 * @return void
		 */
		public function maybe_flush_permalinks() {
			$rules = get_option( 'rewrite_rules' );
			if ( ! isset( $rules[ \WPGraphQL\Router::$route . '/?$' ] ) ) {
				flush_rewrite_rules();
			}
		}

		/**
		 * Setup filters
		 *
		 * @return void
		 */
		private function filters() {

			/**
			 * Instrument the Schema to provide Resolve Hooks and sanitize Schema output
			 */
			add_filter( 'graphql_schema', [
				'\WPGraphQL\Utils\InstrumentSchema',
				'instrument_schema',
			], 10, 1 );

			// Filter how metadata is retrieved during GraphQL requests
			add_filter( 'get_post_metadata', [ '\WPGraphQL\Utils\Preview', 'filter_post_meta_for_previews' ], 10, 4 );
		}

		/**
		 * Initialize admin functionality
		 *
		 * @return void
		 */
		public function init_admin() {
			$admin = new Admin();
			$admin->init();
		}

		/**
		 * This sets up built-in post_types and taxonomies to show in the GraphQL Schema
		 *
		 * @since  0.0.2
		 * @return void
		 */
		public static function show_in_graphql() {

			global $wp_post_types, $wp_taxonomies;

			// Adds GraphQL support for attachments.
			if ( isset( $wp_post_types['attachment'] ) ) {
				$wp_post_types['attachment']->show_in_graphql     = true;
				$wp_post_types['attachment']->graphql_single_name = 'mediaItem';
				$wp_post_types['attachment']->graphql_plural_name = 'mediaItems';
			}

			// Adds GraphQL support for pages.
			if ( isset( $wp_post_types['page'] ) ) {
				$wp_post_types['page']->show_in_graphql     = true;
				$wp_post_types['page']->graphql_single_name = 'page';
				$wp_post_types['page']->graphql_plural_name = 'pages';
			}

			// Adds GraphQL support for posts.
			if ( isset( $wp_post_types['post'] ) ) {
				$wp_post_types['post']->show_in_graphql     = true;
				$wp_post_types['post']->graphql_single_name = 'post';
				$wp_post_types['post']->graphql_plural_name = 'posts';
			}

			// Adds GraphQL support for categories.
			if ( isset( $wp_taxonomies['category'] ) ) {
				$wp_taxonomies['category']->show_in_graphql     = true;
				$wp_taxonomies['category']->graphql_single_name = 'category';
				$wp_taxonomies['category']->graphql_plural_name = 'categories';
			}

			// Adds GraphQL support for tags.
			if ( isset( $wp_taxonomies['post_tag'] ) ) {
				$wp_taxonomies['post_tag']->show_in_graphql     = true;
				$wp_taxonomies['post_tag']->graphql_single_name = 'tag';
				$wp_taxonomies['post_tag']->graphql_plural_name = 'tags';
			}

			// Adds GraphQL support for post formats.
			if ( isset( $wp_taxonomies['post_format'] ) ) {
				$wp_taxonomies['post_format']->show_in_graphql     = true;
				$wp_taxonomies['post_format']->graphql_single_name = 'postFormat';
				$wp_taxonomies['post_format']->graphql_plural_name = 'postFormats';
			}
		}

		/**
		 * Get the post types that are allowed to be used in GraphQL. This gets all post_types that
		 * are set to show_in_graphql, but allows for external code (plugins/theme) to filter the
		 * list of allowed_post_types to add/remove additional post_types
		 *
		 * @param array $args Arguments to filter allowed post types
		 *
		 * @return array
		 * @since  0.0.4
		 */
		public static function get_allowed_post_types( $args = [] ) {

			/**
			 * Get all post_types
			 */
			$post_types = get_post_types( array_merge( [ 'show_in_graphql' => true ], $args ) );

			/**
			 * Validate that the post_types have a graphql_single_name and graphql_plural_name
			 */
			array_map(
				function( $post_type ) {
					$post_type_object = get_post_type_object( $post_type );

					if ( ! $post_type_object instanceof WP_Post_Type ) {
						return;
					}

					if ( empty( $post_type_object ) || empty( $post_type_object->graphql_single_name ) || empty( $post_type_object->graphql_plural_name ) ) {
						throw new UserError(
							sprintf(
							/* translators: %s will replaced with the registered type */
								__( 'The %s post_type isn\'t configured properly to show in GraphQL. It needs a "graphql_single_name" and a "graphql_plural_name"', 'wp-graphql' ),
								$post_type_object->name
							)
						);
					}
				},
				$post_types
			);

			/**
			 * Define the $allowed_post_types to be exposed by GraphQL Queries Pass through a filter
			 * to allow the post_types to be modified (for example if a certain post_type should
			 * not be exposed to the GraphQL API)
			 *
			 * @since 0.0.2
			 *
			 * @param array $post_types Array of post types
			 *
			 * @return array
			 */
			return apply_filters( 'graphql_post_entities_allowed_post_types', $post_types );

		}

		/**
		 * Get the taxonomies that are allowed to be used in GraphQL/This gets all taxonomies that
		 * are set to "show_in_graphql" but allows for external code (plugins/themes) to filter
		 * the list of allowed_taxonomies to add/remove additional taxonomies
		 *
		 * @since  0.0.4
		 * @return array
		 */
		public static function get_allowed_taxonomies() {

			/**
			 * Get all taxonomies
			 */
			$taxonomies = get_taxonomies(
				[
					'show_in_graphql' => true,
				]
			);

			/**
			 * Validate that the taxonomies have a graphql_single_name and graphql_plural_name
			 */
			array_map(
				function( $taxonomy ) {

					$tax_object = get_taxonomy( $taxonomy );

					if ( ! $tax_object instanceof WP_Taxonomy ) {
						return;
					}

					if ( ! isset( $tax_object->graphql_single_name ) || ! isset( $tax_object->graphql_plural_name ) ) {
						throw new UserError(
							sprintf(
							/* translators: %s will replaced with the registered taxonomty */
								__( 'The %s taxonomy isn\'t configured properly to show in GraphQL. It needs a "graphql_single_name" and a "graphql_plural_name"', 'wp-graphql' ),
								$tax_object->name
							)
						);

					}
				},
				$taxonomies
			);

			/**
			 * Returns the array of $allowed_taxonomies
			 */
			return apply_filters( 'graphql_term_entities_allowed_taxonomies', $taxonomies );

		}

		/**
		 * Allow Schema to be cleared
		 *
		 * @return void
		 */
		public static function clear_schema() {
			self::$type_registry = null;
			self::$schema        = null;
		}

		/**
		 * Returns the Schema as defined by static registrations throughout
		 * the WP Load.
		 *
		 * @return WPSchema
		 *
		 * @throws Exception
		 */
		public static function get_schema() {

			if ( null === self::$schema ) {

				$schema_registry = new SchemaRegistry();
				$schema          = $schema_registry->get_schema();

				/**
				 * Generate & Filter the schema.
				 *
				 * @since 0.0.5
				 *
				 * @param array                 $schema      The executable Schema that GraphQL executes against
				 * @param AppContext $app_context Object The AppContext object containing all of the
				 *                                           information about the context we know at this point
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
			return ! empty( self::$schema ) ? self::$schema : null;
		}

		/**
		 * Whether WPGraphQL is operating in Debug mode
		 * @return bool
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
		 * Returns the Schema as defined by static registrations throughout
		 * the WP Load.
		 *
		 * @return TypeRegistry
		 *
		 * @throws Exception
		 */
		public static function get_type_registry() {

			if ( null === self::$type_registry ) {

				$type_registry = new TypeRegistry();

				/**
				 * Generate & Filter the schema.
				 *
				 * @since 0.0.5
				 *
				 * @param array                 $type_registry The TypeRegistry for the API
				 * @param AppContext $app_context   Object The AppContext object containing all of the
				 *                                             information about the context we know at this point
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
			return ! empty( self::$type_registry ) ? self::$type_registry : null;

		}

		/**
		 * Return the static schema if there is one
		 *
		 * @return null|string
		 */
		public static function get_static_schema() {
			$schema = null;
			if ( file_exists( WPGRAPHQL_PLUGIN_DIR . 'schema.graphql' ) && ! empty( file_get_contents( WPGRAPHQL_PLUGIN_DIR . 'schema.graphql' ) ) ) { // phpcs:ignore
				$schema = file_get_contents( WPGRAPHQL_PLUGIN_DIR . 'schema.graphql' ); // phpcs:ignore
			}

			return $schema;
		}

		/**
		 * Get the AppContext for use in passing down the Resolve Tree
		 *
		 * @return AppContext
		 */
		public static function get_app_context() {

			/**
			 * Configure the app_context which gets passed down to all the resolvers.
			 *
			 * @since 0.0.4
			 */
			$app_context           = new AppContext();
			$app_context->viewer   = wp_get_current_user();
			$app_context->root_url = get_bloginfo( 'url' );
			$app_context->request  = ! empty( $_REQUEST ) ? $_REQUEST : null; // phpcs:ignore

			return $app_context;
		}
	}
endif;

if ( ! function_exists( 'graphql_init' ) ) {
	/**
	 * Function that instantiates the plugins main class
	 *
	 * @since 0.0.1
	 *
	 * @return object
	 */
	function graphql_init() {
		/**
		 * Return an instance of the action
		 */
		return \WPGraphQL::instance();
	}
}
graphql_init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once 'cli/wp-cli.php';
}
