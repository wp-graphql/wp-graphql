<?php
/**
 * Plugin Name: WP GraphQL
 * Plugin URI: https://github.com/dfmedia/wp-graphql
 * Description: GraphQL Endpoint for WordPress
 * Author: Jason Bahl, Digital First Media
 * Author URI: http://www.wpgraphql.com
 * Version: 0.0.1
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 4.5.0
 * Tested up to: 4.5.3
 *
 * @package WPGraphQL
 * @category Core
 * @author Jason Bahl
 * @version 0.0.1
 */
namespace DFM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'DFM\WPGraphQL' ) ) :

final class WPGraphQL{

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

			// Initialize the classes
			self::$instance->router = new \DFM\WPGraphQL\Router();

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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'easy-digital-downloads' ), '1.6' );

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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'easy-digital-downloads' ), '1.6' );

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
			define( 'WPGRAPHQL_VERSION', '0.0.1' );
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
	 * @access private
	 * @since 0.0.1
	 * @return void
	 */
	private function includes() {

		// Autoload Required Classes
		require_once( WPGRAPHQL_PLUGIN_DIR . 'vendor/autoload.php');

	}

}

endif;

function WPGraphQL() {
	return \DFM\WPGraphQL::instance();
}

WPGraphQL();
