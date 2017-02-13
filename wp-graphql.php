<?php
/**
 * Plugin Name: WP GraphQL
 * Plugin URI: https://github.com/dfmedia/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: Jason Bahl, Digital First Media
 * Author URI: http://www.wpgraphql.com
 * Version: 0.0.5
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 4.7.0
 * Tested up to: 4.7.1
 *
 * @package WPGraphQL
 * @category Core
 * @author Digital First Media, Jason Bahl, Ryan Kanner
 * @version 0.0.4
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPGraphQL' ) ) :
	/**
	 * This is the one true WPGraphQL class
	 */
	final class WPGraphQL {

		/**
		 * @var WPGraphQL The one true WPGraphQL
		 * @since 0.0.1
		 */
		private static $instance;

		/**
		 * @var array allowed_post_types
		 */
		public static $allowed_post_types;

		/**
		 * @var array allowed_taxonomies
		 */
		public static $allowed_taxonomies;

		/**
		 * @return object|WPGraphQL - The one true WPGraphQL
		 * @since 0.0.1
		 */
		public static function instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPGraphQL ) ) {
				self::$instance = new WPGraphQL;
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->setup();
				self::$instance->router = new \WPGraphQL\Router();
			}

			/**
			 * Fire off init action
			 */
			do_action( 'graphql_init', self::$instance );

			/**
			 * Return the WPGraphQL Instance
			 */
			return self::$instance;
		}

		/**
		 * Throw error on object clone.
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object therefore, we don't want the object to be cloned.
		 *
		 * @since 0.0.1
		 * @access protected
		 * @return void
		 */
		public function __clone() {

			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, esc_html__( 'The WPGraphQL class should not be cloned.', 'wp-graphql' ), '0.0.1' );

		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @since 0.0.1
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
		 * @since 0.0.1
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
				define( 'WPGRAPHQL_VERSION', '0.0.3' );
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
		 *
		 * Uses composer's autoload
		 *
		 * @access private
		 * @since 0.0.1
		 * @return void
		 */
		private function includes() {

			// Autoload Required Classes
			require_once( WPGRAPHQL_PLUGIN_DIR . 'vendor/autoload.php' );

			// This required here as it is not an autoload class
			require_once( WPGRAPHQL_PLUGIN_DIR . 'access-functions.php' );

		}

		/**
		 * setup
		 *
		 * This sets up the various types and other
		 * plugin functions
		 *
		 * @access private
		 * @since 0.0.2
		 * @return void
		 */
		private function setup() {

			add_action( 'graphql_process_http_request', [ $this, 'show_in_graphql' ] );
			add_action( 'graphql_process_http_request', [ $this, 'get_allowed_post_types' ], 20 );
			add_action( 'graphql_process_http_request', [ $this, 'get_allowed_taxonomies' ], 20 );

		}

		/**
		 * show_in_graphql
		 *
		 * This sets up built-in post_types and taxonomies to show
		 * in the GraphQL Schema
		 *
		 * @since 0.0.2
		 */
		public function show_in_graphql() {

			global $wp_post_types, $wp_taxonomies;

			if ( isset( $wp_post_types['attachment'] ) ) {
				$wp_post_types['attachment']->show_in_graphql     = true;
				$wp_post_types['attachment']->graphql_single_name = 'mediaItem';
				$wp_post_types['attachment']->graphql_plural_name = 'mediaItems';
			}

			if ( isset( $wp_post_types['page'] ) ) {
				$wp_post_types['page']->show_in_graphql     = true;
				$wp_post_types['page']->graphql_single_name = 'page';
				$wp_post_types['page']->graphql_plural_name = 'pages';
			}

			if ( isset( $wp_post_types['post'] ) ) {
				$wp_post_types['post']->show_in_graphql     = true;
				$wp_post_types['post']->graphql_single_name = 'post';
				$wp_post_types['post']->graphql_plural_name = 'posts';
			}

			if ( isset( $wp_taxonomies['category'] ) ) {
				$wp_taxonomies['category']->show_in_graphql     = true;
				$wp_taxonomies['category']->graphql_single_name = 'category';
				$wp_taxonomies['category']->graphql_plural_name = 'categories';
			}

			if ( isset( $wp_taxonomies['post_tag'] ) ) {
				$wp_taxonomies['post_tag']->show_in_graphql     = true;
				$wp_taxonomies['post_tag']->graphql_single_name = 'postTag';
				$wp_taxonomies['post_tag']->graphql_plural_name = 'postTags';
			}
		}

		/**
		 * get_allowed_post_types
		 *
		 * Get the post types that are allowed to be used in GraphQL.
		 * This gets all post_types that are set to show_in_graphql, but allows
		 * for external code (plugins/theme) to filter the list of allowed_post_types
		 * to add/remove additional post_types
		 *
		 * @return array
		 * @since 0.0.4
		 */
		public static function get_allowed_post_types() {

			/**
			 * Get all post_types that have been registered to "show_in_graphql"
			 */
			$post_types = get_post_types( [ 'show_in_graphql' => true ] );

			/**
			 * Define the $allowed_post_types to be exposed by GraphQL Queries
			 * Pass through a filter to allow the post_types to be modified (for example if
			 * a certain post_type should not be exposed to the GraphQL API)
			 *
			 * @since 0.0.2
			 */
			self::$allowed_post_types = apply_filters( 'graphql_post_entities_allowed_post_types', $post_types );

			/**
			 * Returns the array of allowed_post_types
			 */
			self::$allowed_post_types;
		}

		/**
		 * get_allowed_taxonomies
		 *
		 * Get the taxonomies that are allowed to be used in GraphQL/
		 * This gets all taxonomies that are set to "show_in_graphql" but allows
		 * for external code (plugins/themes) to filter the list of allowed_taxonomies
		 * to add/remove additional taxonomies
		 *
		 * @since 0.0.4
		 */
		public static function get_allowed_taxonomies() {

			/**
			 * Get all taxonomies that have been registered to "show_in_graphql"
			 */
			$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ] );

			/**
			 * Define the $allowed_taxonomies to be exposed by GraphQL Queries
			 * Pass through a filter to allow the taxonomies to be modified (for example if
			 * a certain taxonomy should not be exposed to the GraphQL API)
			 *
			 * @since 0.0.2
			 */
			self::$allowed_taxonomies = apply_filters( 'graphql_term_entities_allowed_taxonomies', $taxonomies );

			/**
			 * Returns the array of $allowed_taxonomies
			 */
			return self::$allowed_taxonomies;

		}
	}
endif;


/**
 * Function that instantiates the plugins main class
 * @since 0.0.1
 */
function graphql_init() {

	/**
	 * Return an instance of the action
	 */
	return \WPGraphQL::instance();
}

/**
 * Instantiate the plugin, after themes have loaded so that
 * themes have a chance to filter things as well.
 * @since 0.0.2
 */
add_action( 'after_setup_theme', 'graphql_init', 10 );