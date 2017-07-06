<?php
/**
 * Plugin Name: WP GraphQL
 * Plugin URI: https://github.com/dfmedia/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Version: 0.0.15
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 4.7.0
 * Tested up to: 4.7.1
 *
 * @package  WPGraphQL
 * @category Core
 * @author   WPGraphQL
 * @version  0.0.15
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This plugin brings the power of GraphQL (http://graphql.org/) to WordPress.
 *
 * This plugin is based on the hard work of Edwin Cromley of BE-Webdesign (https://github.com/BE-Webdesign), and
 * Jason Bahl and Ryan Kanner of Digital First Media (https://github.com/dfmedia).
 *
 * The plugin is built on top of the graphql-php library by Webonyx (https://github.com/webonyx/graphql-php) and makes
 * use of the graphql-relay-php library by Ivome (https://github.com/ivome/graphql-relay-php/)
 *
 * Special thanks to Digital First Media (http://digitalfirstmedia.com) for allocating development resources to push
 * the project forward.
 *
 * Some of the concepts and code are based on the WordPress Rest API.
 * Much love to the folks (https://github.com/orgs/WP-API/people) that put their blood, sweat and tears into the
 * WP-API project, as it's been huge in moving WordPress forward as a platform and helped inspire and direct the
 * development of WPGraphQL.
 *
 * Much love to Facebook® for open sourcing the GraphQL spec (https://facebook.github.io/graphql/) and maintaining the
 * JS reference implementation (https://github.com/graphql/graphql-js)
 *
 * Much love to Apollo (Meteor Development Group) for their work on driving GraphQL forward and providing a
 * lot of insight into how to design GraphQL schemas, etc. Check them out: http://www.apollodata.com/
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
		 * @access private
		 */
		private static $instance;

		/**
		 * Stores an array of allowed post types
		 *
		 * @var array allowed_post_types
		 * @since  0.0.5
		 * @access public
		 */
		public static $allowed_post_types;

		/**
		 * Stores an array of allowed taxonomies
		 *
		 * @var array allowed_taxonomies
		 * @since  0.0.5
		 * @access public
		 */
		public static $allowed_taxonomies;

		/**
		 * The instance of the WPGraphQL object
		 *
		 * @return object|WPGraphQL - The one true WPGraphQL
		 * @since  0.0.1
		 * @access public
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPGraphQL ) ) {
				self::$instance = new WPGraphQL;
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->actions();
				self::$instance->filters();
			}

			new \WPGraphQL\Data\Config();
			new \WPGraphQL\Router();

			/**
			 * Fire off init action
			 *
			 * @param WPGraphQL $instance The instance of the WPGraphQL class
			 */
			do_action( 'graphql_init', self::$instance );

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
		 * @access public
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
		 * @access protected
		 * @return void
		 */
		public function __wakeup() {

			// De-serializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'De-serializing instances of the WPGraphQL class is not allowed', 'wp-graphql' ), '0.0.1' );

		}

		/**
		 * Setup plugin constants.
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
				define( 'WPGRAPHQL_VERSION', '0.0.15' );
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

		}

		/**
		 * Include required files.
		 * Uses composer's autoload
		 *
		 * @access private
		 * @since  0.0.1
		 * @return void
		 */
		private function includes() {

			// Autoload Required Classes
			require_once( WPGRAPHQL_PLUGIN_DIR . 'vendor/autoload.php' );

			// Required non-autoloaded classes
			require_once( WPGRAPHQL_PLUGIN_DIR . 'access-functions.php' );

		}

		/**
		 * Sets up actions to run at certain spots throughout WordPress and the WPGraphQL execution cycle
		 */
		private function actions() {
			// @placeholder where actions can be added throughout. This will be useful for mutations
		}

		/**
		 * Setup filters
		 */
		private function filters() {

			/**
			 * mediaItems are the attachment postObject, but they have a different schema shape
			 * than postObjects out of the box, so this filter adjusts the core mediaItem
			 * shape of data
			 */
			add_filter( 'graphql_mediaItem_fields', [ '\WPGraphQL\Type\MediaItem\MediaItemType', 'fields' ], 10, 1 );

		}

		/**
		 * This sets up built-in post_types and taxonomies to show in the GraphQL Schema
		 *
		 * @since  0.0.2
		 * @access public
		 * @return void
		 */
		public static function show_in_graphql() {

			global $wp_post_types, $wp_taxonomies;

			// Adds GraphQL support for attachments
			if ( isset( $wp_post_types['attachment'] ) ) {
				$wp_post_types['attachment']->show_in_graphql     = true;
				$wp_post_types['attachment']->graphql_single_name = 'mediaItem';
				$wp_post_types['attachment']->graphql_plural_name = 'mediaItems';
			}

			// Adds GraphQL support for pages
			if ( isset( $wp_post_types['page'] ) ) {
				$wp_post_types['page']->show_in_graphql     = true;
				$wp_post_types['page']->graphql_single_name = 'page';
				$wp_post_types['page']->graphql_plural_name = 'pages';
			}

			// Adds GraphQL support for posts
			if ( isset( $wp_post_types['post'] ) ) {
				$wp_post_types['post']->show_in_graphql     = true;
				$wp_post_types['post']->graphql_single_name = 'post';
				$wp_post_types['post']->graphql_plural_name = 'posts';
			}

			// Adds GraphQL support for categories
			if ( isset( $wp_taxonomies['category'] ) ) {
				$wp_taxonomies['category']->show_in_graphql     = true;
				$wp_taxonomies['category']->graphql_single_name = 'category';
				$wp_taxonomies['category']->graphql_plural_name = 'categories';
			}

			// Adds GraphQL support for tags
			if ( isset( $wp_taxonomies['post_tag'] ) ) {
				$wp_taxonomies['post_tag']->show_in_graphql     = true;
				$wp_taxonomies['post_tag']->graphql_single_name = 'tag';
				$wp_taxonomies['post_tag']->graphql_plural_name = 'tags';
			}
		}

		/**
		 * Get the post types that are allowed to be used in GraphQL. This gets all post_types that
		 * are set to show_in_graphql, but allows for external code (plugins/theme) to filter the
		 * list of allowed_post_types to add/remove additional post_types
		 *
		 * @return array
		 * @since  0.0.4
		 * @access public
		 */
		public static function get_allowed_post_types() {

			/**
			 * Get all post_types that have been registered to "show_in_graphql"
			 */
			$post_types = get_post_types(
				[
					'show_in_graphql' => true,
				]
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
			self::$allowed_post_types = apply_filters( 'graphql_post_entities_allowed_post_types', $post_types );

			/**
			 * Returns the array of allowed_post_types
			 */
			return self::$allowed_post_types;
		}

		/**
		 * Get the taxonomies that are allowed to be used in GraphQL/This gets all taxonomies that
		 * are set to "show_in_graphql" but allows for external code (plugins/themes) to filter
		 * the list of allowed_taxonomies to add/remove additional taxonomies
		 *
		 * @since  0.0.4
		 * @access public
		 * @return array
		 */
		public static function get_allowed_taxonomies() {

			/**
			 * Get all taxonomies that have been registered to "show_in_graphql"
			 */
			 $taxonomies = get_taxonomies(
				[
					'show_in_graphql' => true,
				]
			);

			/**
			 * Define the $allowed_taxonomies to be exposed by GraphQL Queries Pass through a filter
			 * to allow the taxonomies to be modified (for example if a certain taxonomy should not
			 * be exposed to the GraphQL API)
			 *
			 * @since 0.0.2
			 * @return array
			 *
			 * @param array $taxonomies Array of taxonomy objects
			 */
			self::$allowed_taxonomies = apply_filters( 'graphql_term_entities_allowed_taxonomies', $taxonomies );

			/**
			 * Returns the array of $allowed_taxonomies
			 */
			return self::$allowed_taxonomies;

		}

		/**
		 * This processes a GraphQL request, given a $request and optional $variables
		 *
		 * This function is used to resolve the HTTP requests for the GraphQL API, but can also be
		 * used internally to run GraphQL queries inside WordPress via PHP.
		 *
		 * @since 0.0.5
		 *
		 * @param string $request        The GraphQL request to be run
		 * @param string $operation_name The name of the operation
		 * @param string $variables      Variables to be passed to your GraphQL request
		 *
		 * @return array $result The results of your request
		 */
		public static function do_graphql_request( $request, $operation_name = '', $variables = '' ) {

			/**
			 * Whether it's a GraphQL Request (http or internal)
			 *
			 * @since 0.0.5
			 */
			if ( ! defined( 'GRAPHQL_REQUEST' ) ) {
				define( 'GRAPHQL_REQUEST', true );
			}

			/**
			 * Run an action as soon when do_graphql_request begins.
			 *
			 * @param string $request        The GraphQL request to be run
			 * @param string $operation_name The name of the operation
			 * @param string $variables      Variables to be passed to your GraphQL request
			 */
			do_action( 'do_graphql_request', $request, $operation_name, $variables );

			/**
			 * Get the Schema Dependencies
			 *
			 * @since 0.0.5
			 */
			\WPGraphQL::show_in_graphql();
			\WPGraphQL::get_allowed_post_types();
			\WPGraphQL::get_allowed_taxonomies();

			/**
			 * Configure the app_context which gets passed down to all the resolvers.
			 *
			 * @since 0.0.4
			 */
			$app_context           = new \WPGraphQL\AppContext();
			$app_context->viewer   = wp_get_current_user();
			$app_context->root_url = get_bloginfo( 'url' );
			$app_context->request  = ! empty( $_REQUEST ) ? $_REQUEST : null;

			/**
			 * Run an action before generating the schema
			 * This is a great spot for plugins/themes to hook in to customize the schema.
			 *
			 * @since 0.0.5
			 *
			 * @param string     $request        The request to be executed by GraphQL
			 * @param string     $operation_name The name of the operation
			 * @param array      $variables      Variables to be passed to your GraphQL request
			 * @param            AppContext      object The AppContext object containing all of the
			 *                                   information about the context we know at this point
			 */
			if ( ! is_array( $variables ) ) {
				$variables = (string) $variables;
				$variables = (array) json_decode( $variables );
			}

			do_action( 'graphql_generate_schema', $request, $operation_name, $variables, $app_context );

			$executable_schema = [
				'query'    => \WPGraphQL\Types::root_query(),
				'mutation' => \WPGraphQL\Types::root_mutation(),
			];

			/**
			 * Generate the Schema
			 */
			$schema = new \WPGraphQL\WPSchema( $executable_schema );

			/**
			 * Generate & Filter the schema.
			 *
			 * @since 0.0.5
			 *
			 * @param array      $schema         The executable Schema that GraphQL executes against
			 * @param string     $request        The request to be executed by GraphQL
			 * @param string     $operation_name The name of the operation
			 * @param array|null $variables      Variables to be passed to the GraphQL query
			 * @param            object          AppContext  Object The AppContext object containing all of the
			 *                                   information about the context we know at this point
			 */
			$schema = apply_filters( 'graphql_schema', $schema, $request, $operation_name, $variables, $app_context );

			/**
			 * Sanitize the Schema as late as possible before execution
			 */
			$sanitized_schema = \WPGraphQL\WPSchema::sanitize_schema( $schema );

			/**
			 * Executes the request and captures the result
			 */
			$result = \GraphQL\GraphQL::execute(
				$sanitized_schema,
				$request,
				null,
				$app_context,
				$variables,
				$operation_name
			);

			/**
			 * Filter the $result of the GraphQL execution. This allows for the response to be filtered before
			 * it's returned, allowing granular control over the response at the latest point.
			 *
			 * POSSIBLE USAGE EXAMPLES:
			 * This could be used to ensure that certain fields never make it to the response if they match
			 * certain criteria, etc. For example, this filter could be used to check if a current user is
			 * allowed to see certain things, and if they are not, the $result could be filtered to remove
			 * the data they should not be allowed to see.
			 *
			 * Or, perhaps some systems want the result to always include some additional piece of data in
			 * every response, regardless of the request that was sent to it, this could allow for that
			 * to be hooked in and included in the $result
			 *
			 * @since 0.0.5
			 *
			 * @param array      $result         The result of your GraphQL query
			 * @param            Schema          object $schema The schema object for the root query
			 * @param string     $operation_name The name of the operation
			 * @param string     $request        The request that GraphQL executed
			 * @param array|null $variables      Variables to passed to your GraphQL request
			 */
			$result = apply_filters( 'graphql_request_results', $result, $schema, $operation_name, $request, $variables );

			/**
			 * Run an action. This is a good place for debug tools to hook in to log things, etc.
			 *
			 * @since 0.0.4
			 *
			 * @param array      $result         The result of your GraphQL request
			 * @param            Schema          object $schema The schema object for the root request
			 * @param string     $operation_name The name of the operation
			 * @param string     $request        The request that GraphQL executed
			 * @param array|null $variables      Variables to passed to your GraphQL query
			 */
			do_action( 'graphql_execute', $result, $schema, $operation_name, $request, $variables );

			/**
			 * Return the result of the request
			 */
			return $result;

		}
	}
endif;

/**
 * Function that instantiates the plugins main class
 *
 * @since 0.0.1
 */
function graphql_init() {

	/**
	 * Return an instance of the action
	 */
	return \WPGraphQL::instance();
}

/**
 * Instantiate the plugin
 *
 * @since 0.0.2
 */
add_action( 'after_setup_theme', 'graphql_init', 10 );
