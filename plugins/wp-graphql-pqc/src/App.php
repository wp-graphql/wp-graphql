<?php
/**
 * Main plugin class
 *
 * @package WPGraphQL\PQC
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC;

use WPGraphQL\PQC\Database\Schema;

/**
 * Class App
 *
 * @package WPGraphQL\PQC
 */
class App {

	/**
	 * Stores the instance of the App class
	 *
	 * @var App|null
	 */
	private static ?App $instance = null;

	/**
	 * Returns the instance of the App class
	 *
	 * @return App
	 */
	public static function instance(): App {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init(): void {
		// Check if plugin can load (dependencies available).
		if ( ! $this->can_load_plugin() ) {
			add_action( 'admin_init', [ $this, 'show_admin_notice' ] );
			add_action( 'graphql_init', [ $this, 'show_graphql_debug_messages' ] );
			return;
		}

		// Initialize components.
		$this->init_components();
	}

	/**
	 * Whether the plugin can load. Uses only boolean checks so it is safe to call before the init action.
	 *
	 * @return bool
	 */
	public function can_load_plugin(): bool {
		if ( ! class_exists( 'WPGraphQL' ) ) {
			return false;
		}

		if ( ! defined( 'WPGRAPHQL_SMART_CACHE_VERSION' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get plugin load error messages
	 *
	 * @return array<string>
	 */
	private function get_plugin_load_error_messages(): array {
		$messages = [];

		if ( ! class_exists( 'WPGraphQL' ) ) {
			$messages[] = __( 'WPGraphQL must be installed and activated', 'wp-graphql-pqc' );
		}

		if ( ! defined( 'WPGRAPHQL_SMART_CACHE_VERSION' ) ) {
			$messages[] = __( 'WPGraphQL Smart Cache must be installed and activated', 'wp-graphql-pqc' );
		}

		return $messages;
	}

	/**
	 * Show admin notice if plugin cannot load
	 *
	 * @return void
	 */
	public function show_admin_notice(): void {
		if ( $this->can_load_plugin() ) {
			return;
		}

		$messages = $this->get_plugin_load_error_messages();

		if ( empty( $messages ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $messages ) {
				?>
				<div class="error notice">
					<h3>
						<?php
						// translators: %s is the version of the plugin
						echo esc_html( sprintf( __( 'WPGraphQL Persisted Queries Cache v%s cannot load', 'wp-graphql-pqc' ), WPGRAPHQL_PQC_VERSION ) );
						?>
					</h3>
					<ol>
						<?php foreach ( $messages as $message ) : ?>
							<li><?php echo esc_html( $message ); ?></li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php
			}
		);
	}

	/**
	 * Output GraphQL debug messages if the plugin cannot load properly
	 *
	 * @return void
	 */
	public function show_graphql_debug_messages(): void {
		if ( $this->can_load_plugin() ) {
			return;
		}

		$messages = $this->get_plugin_load_error_messages();

		if ( empty( $messages ) ) {
			return;
		}

		$prefix = sprintf( 'WPGraphQL Persisted Queries Cache v%s cannot load', WPGRAPHQL_PQC_VERSION );
		foreach ( $messages as $message ) {
			graphql_debug( $prefix . ' because ' . $message );
		}
	}

	/**
	 * Initialize plugin components
	 *
	 * @return void
	 */
	private function init_components(): void {
		Schema::ensure_schema();

		// Initialize router for rewrite rules.
		$router = new Router();
		$router->init();

		// Initialize POST handler.
		$post_handler = new Request\PostHandler();
		$post_handler->init();

		// Initialize purge handler.
		$purge_handler = new Invalidation\PurgeHandler();
		$purge_handler->init();

		// Initialize garbage collection.
		$garbage_collection = new Cron\GarbageCollection();
		$garbage_collection->init();
	}

	/**
	 * Plugin activation callback
	 *
	 * @return void
	 */
	public function activate(): void {
		// Create database table (doesn't require dependencies).
		Schema::create_table();

		// Update database version.
		Schema::update_db_version( WPGRAPHQL_PQC_VERSION );

		// Flush rewrite rules to register new routes.
		flush_rewrite_rules();

		// Schedule garbage collection cron job.
		$this->schedule_garbage_collection();
	}

	/**
	 * Plugin deactivation callback
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Clear scheduled cron jobs.
		wp_clear_scheduled_hook( 'wpgraphql_pqc_garbage_collection' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Schedule garbage collection cron job
	 *
	 * @return void
	 */
	private function schedule_garbage_collection(): void {
		if ( ! wp_next_scheduled( 'wpgraphql_pqc_garbage_collection' ) ) {
			wp_schedule_event( time(), 'daily', 'wpgraphql_pqc_garbage_collection' );
		}
	}
}
