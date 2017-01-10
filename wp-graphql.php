<?php
/**
 * Plugin Name: WP GraphQL
 * Plugin URI: https://github.com/dfmedia/wp-graphql
 * Description: GraphQL Endpoint for WordPress
 * Author: Jason Bahl, Digital First Media
 * Author URI: http://www.wpgraphql.com
 * Version: 0.0.2
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 4.5.0
 * Tested up to: 4.5.3
 *
 * @package WPGraphQL
 * @category Core
 * @author Digital First Media, Jason Bahl, Ryan Kanner
 * @version 0.0.1
 */
namespace DFM;

// Exit if accessed directly.
use DFM\WPGraphQL\Router;
use DFM\WPGraphQL\Schema;
use DFM\WPGraphQL\Setup\PostEntities;
use DFM\WPGraphQL\Setup\Shortcodes;
use DFM\WPGraphQL\Setup\TermEntities;
use Youshido\GraphQL\Execution\Processor;

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'DFM\WPGraphQL' ) ) :

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
	 * @return object|WPGraphQL - The one true WPGraphQL
	 * @since 0.0.1
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPGraphQL ) ) {

			self::$instance = new WPGraphQL;
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->setup();

			/**
			 * This action allows for other classes to be instantiated
			 * prior to the router being called
			 * @since 0.0.2
			 */
			do_action( 'wpgraphql_before_initialize_router' );

			// Initialize the router (sets up the /graphql enpoint)
			self::$instance->router = new Router();

		}

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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wp-graphql' ), '1.6' );

	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since 0.0.1
	 * @access protected
	 * @return void
	 */
	public function __wakeup() {

		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wp-graphql' ), '1.6' );

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
			define( 'WPGRAPHQL_VERSION', '0.0.2' );
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
		require_once( WPGRAPHQL_PLUGIN_DIR . 'vendor/autoload.php');

	}

	/**
	 * setup_root_queries
	 *
	 * This sets up the RootQueryType for the GraphQL Schema
	 *
	 * @access private
	 * @since 0.0.2
	 * @return void
	 */
	private function setup() {

		// Initialize PostEntities
		$post_entities = new PostEntities();
		$post_entities->init();

		// Initialize TermEntites
		$term_entities = new TermEntities();
		$term_entities->init();

		$shortcodes = new Shortcodes();
		$shortcodes->init();

	}

	/**
	 * @param $payload
	 * @param $variables
	 *
	 * @return array
	 */
	public function query( $payload, $variables ) {

		/**
		 * Instantiate the DFM\GraphQL\Schema
		 */
		$schema = new Schema();

		// Instantiate the GraphQL Processor
		$processor = new Processor( $schema );

		/**
		 * Add the current_user to the execution context
		 */
		$processor->getExecutionContext()->current_user = wp_get_current_user();

		/**
		 * Process the payload
		 */
		$processor->processPayload( $payload, $variables );

		// Get the response from the processor
		$result = $processor->getResponseData();

		return $result;

	}

}

endif;

// Function that instantiates the plugin
function WPGraphQL() {
	return \DFM\WPGraphQL::instance();
}

// Instantiate the plugin
WPGraphQL();