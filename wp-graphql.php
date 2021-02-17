<?php
/**
 * Plugin Name: WP GraphQL
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Version: 1.1.3
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
 * @version  1.1.3
 */

use GraphQL\Error\UserError;
use WPGraphQL\Admin\Admin;
use WPGraphQL\AppContext;
use WPGraphQL\Registry\SchemaRegistry;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\WPSchema;

// Exit if accessed directly.
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

if ( ! class_exists( 'WPGraphQL' ) ) {
	require_once __DIR__ . '/src/WPGraphQL.php';
}

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
