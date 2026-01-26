<?php
/**
 * Plugin Name: WPGraphQL Smart Cache
 * Plugin URI: https://github.com/wp-graphql/wp-graphql-smart-cache
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql-smart-cache
 * Description: Smart Caching and Cache Invalidation for WPGraphQL
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires WPGraphQL: 2.0.0
 * WPGraphQL Tested Up To: 2.0.0
 * Text Domain: wp-graphql-smart-cache
 * Domain Path: /languages
 * Version: 2.0.1
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Persisted Queries and Caching for WPGraphQL
 */

namespace WPGraphQL\SmartCache;

use Appsero\Client;
use WPGraphQL\SmartCache\Cache\Collection;
use WPGraphQL\SmartCache\Cache\Invalidation;
use WPGraphQL\SmartCache\Cache\Results;
use WPGraphQL\SmartCache\Admin\Editor;
use WPGraphQL\SmartCache\Admin\Settings;
use WPGraphQL\SmartCache\Document\Description;
use WPGraphQL\SmartCache\Document\Grant;
use WPGraphQL\SmartCache\Document\Group;
use WPGraphQL\SmartCache\Document\MaxAge;
use WPGraphQL\SmartCache\Document\Loader;
use WPGraphQL\SmartCache\Document\GarbageCollection;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the autoload file exists, require it.
// If the plugin was installed from composer, the autoload
// would be required elsewhere in the project
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_VERSION' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_VERSION', '2.0.1' );
}

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION', '1.12.0' );
}

if ( ! defined( 'WPGRAPHQL_SMART_CACHE_PLUGIN_DIR' ) ) {
	define( 'WPGRAPHQL_SMART_CACHE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Check whether WPGraphQL is active, and whether the minimum version requirement has been met,
 * and whether the autoloader is working as expected
 *
 * @return bool
 * @since 0.3
 */
function can_load_plugin() {

	// Is WPGraphQL active?
	if ( ! class_exists( 'WPGraphQL' ) ) {
		return false;
	}

	// Do we have a WPGraphQL version to check against?
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		return false;
	}

	// Have we met the minimum version requirement?
	// @phpstan-ignore-next-line
	if ( version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION, 'lt' ) ) {
		return false;
	}

	// If the Document class doesn't exist, then the autoloader failed to load.
	// This likely means that the plugin was installed via composer and the parent
	// project doesn't have the autoloader setup properly
	if ( ! class_exists( Document::class ) ) {
		return false;
	}

	return true;
}

/**
 * Set the graphql-php server persistent query loader during server config setup.
 * When a queryId is found on the request, the call back is invoked to look up the query string.
 */
add_action(
	'graphql_server_config',
	function ( \GraphQL\Server\ServerConfig $config ) {
		$config->setPersistedQueryLoader(
			function ( string $queryId, \GraphQL\Server\OperationParams $params ) {
				return Loader::by_query_id( $queryId, (array) $params );
			}
		);
	},
	10,
	1
);

add_action(
	'init',
	function () {

		/**
		 * If WPGraphQL is not active, or is an incompatible version, show the admin notice and bail
		 */
		if ( false === can_load_plugin() ) {
			// Show the admin notice
			add_action( 'admin_init', __NAMESPACE__ . '\show_admin_notice' );

			// Bail
			return;
		}

		$document = new Document();
		$document->init();

		$description = new Description();
		$description->init();

		$grant = new Grant();
		$grant->init();

		$max_age = new MaxAge();
		$max_age->init();

		$doc_group = new Group();
		$doc_group->init();

		$errors = new AdminErrors();
		$errors->init();

		$settings = new Settings();
		$settings->init();
	}
);

/**
 * Show admin notice to admins if this plugin is active but WPGraphQL
 * is not active, or doesn't meet version requirements
 *
 * @return void
 */
function show_admin_notice() {

	/**
	 * For users with lower capabilities, don't show the notice
	 */
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	add_action(
		'admin_notices',
		function () {
			?>
			<div class="error notice">
				<p>
					<?php
					// translators: placeholder is the version number of the WPGraphQL Plugin that this plugin depends on
					$text = sprintf( 'WPGraphQL (v%s+) must be active for "wp-graphql-smart-cache" to work', WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION );

					// phpcs:ignore
					esc_html_e( $text, 'wp-graphql-smart-cache' );
					?>
				</p>
			</div>
			<?php
		}
	);
}

add_action(
	'admin_init',
	function () {
		if ( false === can_load_plugin() ) {
			return;
		}

		$editor = new Editor();
		$editor->admin_init();
	},
	10
);

add_action(
	'wp_loaded',
	function () {
		if ( false === can_load_plugin() ) {
			return;
		}

		// override the query execution with cached results, if present
		$results = new Results();
		$results->init();

		// Start collecting queries for cache
		$collection = new Collection();
		$collection->init();

		// start listening to events that should invalidate caches
		$invalidation = new Invalidation( $collection );
		$invalidation->init();
	}
);

/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function appsero_init_tracker_wpgraphql_smart_cache() {

	// If the class doesn't exist, or code is being scanned by PHPSTAN, move on.
	if ( ! class_exists( 'Appsero\Client' ) || defined( 'PHPSTAN' ) ) {
		return;
	}

	$client = new Client( '66f03878-3df1-40d7-8be9-0069994480d4', 'WPGraphQL Smart Cache', __FILE__ );

	$insights = $client->insights();

	// If the Appsero client has the add_plugin_data method, use it
	if ( method_exists( $insights, 'add_plugin_data' ) ) {
		$insights->add_plugin_data();
	}

	$insights->init();
}

appsero_init_tracker_wpgraphql_smart_cache();

/**
 * The callback function for saved query garbage collection event.
 * Look for saved queries to cleanup and schedule a job to do small batches of deletes.
 */
add_action(
	'wpgraphql_smart_cache_query_garbage_collect',
	function () {
		// Check that the clean up toggle is still enabled.
		$garbage_toggle = get_graphql_setting( 'query_garbage_collect', null, 'graphql_persisted_queries_section' );
		if ( 'on' !== $garbage_toggle ) {
			// Remove the scheduled cron job from firing again if the toggle is not on.
			wp_clear_scheduled_hook( 'wpgraphql_smart_cache_query_garbage_collect' );
			return;
		}

		// If more posts exist to remove, schedule the removal event
		$posts = GarbageCollection::get_documents_by_age( 1 );
		if ( $posts ) {
			wp_schedule_single_event( time() + 1, 'wpgraphql_smart_cache_query_garbage_collect_deletes' );
		}
	},
	10
);

/**
 * The callback function to do the actual deletes of posts that are aged out and need garbage collection.
 * This job will run, load a 'batch' number of posts, instead of loading ALL posts to delete.
 * After processing the deletes, if more remain, this job is scheduled again to process another batch.
 * Do these 'batch' runs of deletes in hope of reducing server load, timeouts, large numbers of deletes in one loop.
 */
add_action(
	'wpgraphql_smart_cache_query_garbage_collect_deletes',
	function () {
		// If posts exist to remove, schedule the removal event
		$batch_size = apply_filters( 'wpgraphql_document_garbage_collect_batch_size', 1000 );
		$posts      = GarbageCollection::get_documents_by_age( $batch_size );
		foreach ( $posts as $post_id ) {
			// Check if the post is selected to skip garbage collection
			wp_delete_post( $post_id );
		}

		// If more posts exist to remove, schedule the removal event
		$posts = GarbageCollection::get_documents_by_age( 1 );
		if ( ! empty( $posts ) ) {
			wp_schedule_single_event( time() + 1, 'wpgraphql_smart_cache_query_garbage_collect_deletes' );
		}
	},
	10
);
