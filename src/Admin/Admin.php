<?php

namespace WPGraphQL\Admin;

use WPGraphQL\Admin\GraphiQL\GraphiQL;
use WPGraphQL\Admin\Settings\Settings;

/**
 * Class Admin
 *
 * @package WPGraphQL\Admin
 */
class Admin {

	/**
	 * Whether Admin Pages are enabled or not
	 *
	 * @var bool
	 */
	protected $admin_enabled;

	/**
	 * Whether GraphiQL is enabled or not
	 *
	 * @var bool
	 */
	protected $graphiql_enabled;

	/**
	 * @var \WPGraphQL\Admin\Settings\Settings
	 */
	protected $settings;

	/**
	 * Initialize Admin functionality for WPGraphQL
	 *
	 * @return void
	 */
	public function init() {

		// Determine whether the admin pages should show or not.
		// Default is enabled.
		$this->admin_enabled    = apply_filters( 'graphql_show_admin', true );
		$this->graphiql_enabled = apply_filters( 'graphql_enable_graphiql', get_graphql_setting( 'graphiql_enabled', true ) );

		// This removes the menu page for WPGraphiQL as it's now built into WPGraphQL
		if ( $this->graphiql_enabled ) {
			add_action(
				'admin_menu',
				static function () {
					remove_menu_page( 'wp-graphiql/wp-graphiql.php' );
				}
			);
		}

		add_action( 'admin_notices', [ $this, 'maybe_display_notices' ] );
		add_action( 'admin_init', [ $this, 'handle_dismissal_of_acf_notice' ]);

		// If the admin is disabled, prevent admin from being scaffolded.
		if ( false === $this->admin_enabled ) {
			return;
		}

		$this->settings = new Settings();
		$this->settings->init();

		if ( 'on' === $this->graphiql_enabled || true === $this->graphiql_enabled ) {
			global $graphiql;
			$graphiql = new GraphiQL();
			$graphiql->init();
		}
	}

	public function maybe_display_notices() {
		if ( ! $this->is_plugin_scoped_page() ) {
			return;
		}

		if ( ! $this->should_display_acf_notice() ) {
			return;
		}

		$dismiss_acf_nonce = wp_create_nonce( 'wpgraphql_disable_acf_notice_nonce' );
		$dismiss_url       = add_query_arg([ 'wpgraphql_disable_acf_notice_nonce' => $dismiss_acf_nonce ]);
		?>
		<style>
			/* Only display the ACF notice */
			body.toplevel_page_graphiql-ide #wpbody #wpgraphql-acf-notice {
				display: block;
				position: absolute;
				top: 0;
				right: 0;
				z-index: 1;
				min-width: 40%;
			}
			body.toplevel_page_graphiql-ide #wpbody #wp-graphiql-wrapper {
				margin-top: 32px;
			}
		</style>
		<div id="wpgraphql-acf-notice" class="notice notice-success">
			<p><?php _e( 'Check out the new WPGraphQL for ACF', 'wpgraphql' ); ?>
			<a href="<?php echo esc_url( $dismiss_url ); ?>"><?php esc_html_e( 'Dismiss', 'graphql' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Checks if the current admin page is within the scope of the plugin's own pages.
	 * 
	 * @return bool True if the current page is within scope of the plugin's pages.
	 */
	protected function is_plugin_scoped_page(): bool {
		$screen = get_current_screen();

		// wp_send_json([ 'screenID'=> $screen->id ]);

		// Guard clause for invalid screen.
		if ( ! $screen ) {
			return false;
		}

		$allowed_pages = [
			"plugins",
			"toplevel_page_graphiql-ide",
			"graphql_page_graphql-settings",
		];

		$current_page_id = $screen->id;

		return in_array( $current_page_id, $allowed_pages, true );
	}

	/**
	 * Checks if an admin notice should be displayed for WPGraphqlQL for ACF
	 *
	 * @return bool
	 */
	protected function should_display_acf_notice(): bool {
		if ( ! class_exists( 'ACF' ) ) {
			return false;
		}

		// Bail if new version of WPGraphQL for ACF is active.
		if ( class_exists( 'WPGraphQLAcf' ) ) {
			return false;
		}

		$is_dismissed = get_option( 'wpgraphql_acf_admin_notice_dismissed', false );
		if ( $is_dismissed ) {
			return false;
		}

		return true;
	}

	/**
	 * Handles the dismissal of the ACF notice.
	 * set_transient reference: https://developer.wordpress.org/reference/functions/set_transient/
	 * This function sets a transient to remember the dismissal status of the notice.
	 */
	function handle_dismissal_of_acf_notice(): void {
		$nonce = $_GET['wpgraphql_disable_acf_notice_nonce'] ?? null;
		
		if ( false === wp_verify_nonce( $nonce, 'wpgraphql_disable_acf_notice_nonce' ) ) {
			return;
		};

		update_option( 'wpgraphql_acf_admin_notice_dismissed', true );
	}
}
