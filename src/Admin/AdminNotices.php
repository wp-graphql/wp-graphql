<?php

namespace WPGraphQL\Admin;

/**
 * This class isn't intended for direct extending or customizing.
 *
 * This class is responsible for handling the management and display of admin notices
 * related directly to WPGraphQL.
 *
 * Breaking changes to this class will not be considered a semver breaking change as there's no
 * expectation that users will be calling these functions directly or extending this class.
 */
final class AdminNotices {

	/**
	 * Stores the admin notices to display
	 *
	 * @var array<mixed>
	 */
	protected $admin_notices = [];

	/**
	 * @var array<string>
	 */
	protected $dismissed_notices = [];

	/**
	 * Initialize the Admin Notices class
	 */
	public function init(): void {

		register_graphql_admin_notice(
			'wpgraphql-acf-announcement',
			[
				'type'           => 'warning',
				'message'        => __( 'You are using WPGraphQL and Advanced Custom Fields. Have you seen the new <a href="https://acf.wpgraphql.com/" target="_blank" rel="nofollow">WPGraphQL for ACF</a>?', 'wp-graphql' ),
				'is_dismissable' => true,
				'conditions'     => static function () {
					if ( ! class_exists( 'ACF' ) ) {
						return false;
					}
			
					// Bail if new version of WPGraphQL for ACF is active.
					if ( class_exists( 'WPGraphQLAcf' ) ) {
						return false;
					}
				
					return true;
				},
			]
		);

		// Initialize Admin Notices. This is where register_graphql_admin_notice hooks in
		do_action( 'graphql_admin_notices_init', $this );

		$current_user_id = get_current_user_id();
		$this->dismissed_notices = get_user_meta( $current_user_id, 'wpgraphql_dismissed_admin_notices', true ) ?: [];

		// Filter the notices to remove any dismissed notices
		$this->pre_filter_dismissed_notices();

		add_action( 'admin_notices', [ $this, 'maybe_display_notices' ] );
		add_action( 'admin_init', [ $this, 'handle_dismissal_of_acf_notice' ] );
	}

	/**
	 * Pre-filters dismissed notices from the admin notices array.
	 */
	protected function pre_filter_dismissed_notices(): void {

		// remove any notice that's been dismissed
		foreach ( $this->dismissed_notices as $dismissed_notice ) {
			$this->remove_admin_notice( $dismissed_notice );
		}

		// For all remaining notices, run the callback to see if it's actually relevant
		foreach ( $this->admin_notices as $notice_slug => $notice ) {
			if ( ! isset( $notice['conditions'] ) ) {
				continue;
			}

			if ( ! is_callable( $notice['conditions'] ) ) {
				continue;
			}

			if ( false === $notice['conditions']() ) {
				$this->remove_admin_notice( $notice_slug );
			}       
		}
	}

	/**
	 * Return all admin notices
	 *
	 * @return array<mixed>
	 */
	public function get_admin_notices(): array {
		return $this->admin_notices;
	}

	/**
	 * @param string       $slug The slug identifying the admin notice
	 * @param array<mixed> $config The config of the admin notice
	 *
	 * @return array<mixed>
	 */
	public function add_admin_notice( string $slug, array $config ): array {

		/**
		 * Pass the notice through a filter before registering it
		 *
		 * @param array<mixed>  $config The config of the admin notice
		 * @param string $slug The slug identifying the admin notice
		 */
		$filtered_notice = apply_filters( 'graphql_add_admin_notice', $config, $slug );

		if ( ! isset( $config['message'] ) ) {
			return [];
		}

		$this->admin_notices[ $slug ] = $filtered_notice;
		return $this->admin_notices[ $slug ];
	}

	/**
	 * Given the slug of an admin notice, remove it from the notices
	 *
	 * @param string $slug The slug identifying the admin notice to remove
	 *
	 * @return array<mixed>
	 */
	public function remove_admin_notice( string $slug ): array {
		unset( $this->admin_notices[ $slug ] );
		return $this->admin_notices;
	}

	/**
	 * Determine whether a notice is dismissable or not
	 *
	 * @param array<mixed> $notice The notice to check whether its dismissable or not
	 */
	public function is_notice_dismissable( array $notice = [] ): bool {
		return ( ! isset( $notice['is_dismissable'] ) || false !== (bool) $notice['is_dismissable'] );
	}

	/**
	 * Display notices if they are displayable
	 */
	public function maybe_display_notices(): void {
		if ( ! $this->is_plugin_scoped_page() ) {
			return;
		}

		$this->render_notices();
	}

	/**
	 * Render the notices.
	 */
	protected function render_notices(): void {
		$notices = $this->get_admin_notices();

		if ( empty( $notices ) ) {
			return;
		}
		?>
		<style>
			/* Only display the ACF notice */
			body.toplevel_page_graphiql-ide #wpbody .wpgraphql-admin-notice {
				display: block;
				position: absolute;
				top: 0;
				right: 0;
				z-index: 1;
				min-width: 40%;
			}
			body.toplevel_page_graphiql-ide #wpbody #wp-graphiql-wrapper {
				margin-top: <?php echo count( $notices ) * 45; ?>px;
			}
			.wpgraphql-admin-notice {
				position: relative;
				text-decoration: none;
				padding: 1px 40px 1px 12px;
			}
			.wpgraphql-admin-notice .notice-dismiss {
				text-decoration: none;
			}

		</style>
		<?php
		$count = 0;
		foreach ( $notices as $notice_slug => $notice ) {
			?>
			<style>
				body.toplevel_page_graphiql-ide #wpbody #wpgraphql-admin-notice-<?php echo esc_attr( $notice_slug ); ?> {
					top: <?php echo esc_attr( ( $count * 45 ) . 'px' ); ?>
				}
			</style>
			<div id="wpgraphql-admin-notice-<?php echo esc_attr( $notice_slug ); ?>" class="wpgraphql-admin-notice notice notice-<?php echo esc_attr( $notice['type'] ) ?? 'success'; ?> <?php echo $this->is_notice_dismissable( $notice ) ? 'is-dismissable' : ''; ?>">
				<p><?php echo ! empty( $notice['message'] ) ? wp_kses_post( $notice['message'] ) : ''; ?></p>
				<?php
				if ( $this->is_notice_dismissable( $notice ) ) {
					$dismiss_acf_nonce = wp_create_nonce( 'wpgraphql_disable_notice_nonce' );
					$dismiss_url       = add_query_arg(
						[
							'wpgraphql_disable_notice_nonce' => $dismiss_acf_nonce,
							'wpgraphql_disable_notice' => $notice_slug,
						]
					);
					?>
					<a href="<?php echo esc_url( $dismiss_url ); ?>" class="notice-dismiss">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'wp-graphql' ); ?></span>
					</a>
				<?php } ?>
			</div>
			<?php
			++$count;
		}
	}

	/**
	 * Checks if the current admin page is within the scope of the plugin's own pages.
	 *
	 * @return bool True if the current page is within scope of the plugin's pages.
	 */
	protected function is_plugin_scoped_page(): bool {
		$screen = get_current_screen();

		// Guard clause for invalid screen.
		if ( ! $screen ) {
			return false;
		}

		$allowed_pages = [
			'plugins',
			'toplevel_page_graphiql-ide',
			'graphql_page_graphql-settings',
		];

		$current_page_id = $screen->id;

		return in_array( $current_page_id, $allowed_pages, true );
	}

	/**
	 * Handles the dismissal of the ACF notice.
	 * set_transient reference: https://developer.wordpress.org/reference/functions/set_transient/
	 * This function sets a transient to remember the dismissal status of the notice.
	 */
	public function handle_dismissal_of_acf_notice(): void {
		if ( ! isset( $_GET['wpgraphql_disable_notice_nonce'], $_GET['wpgraphql_disable_notice'] ) ) {
			return;
		}

		$nonce       = sanitize_text_field( wp_unslash( $_GET['wpgraphql_disable_notice_nonce'] ) );
		$notice_slug = sanitize_text_field( wp_unslash( $_GET['wpgraphql_disable_notice'] ) );

		if ( empty( $notice_slug ) || ! wp_verify_nonce( $nonce, 'wpgraphql_disable_notice_nonce' ) ) {
			return;
		}

		$current_user_id = get_current_user_id();

		$disabled   = get_user_meta( $current_user_id, 'wpgraphql_dismissed_admin_notices', true ) ?: [];
		$disabled[] = $notice_slug;

		update_user_meta( $current_user_id, 'wpgraphql_dismissed_admin_notices', array_unique( $disabled ) );

		// Redirect to clear URL parameters
		wp_safe_redirect( remove_query_arg( [ 'wpgraphql_disable_notice_nonce', 'wpgraphql_disable_notice' ] ) );
		exit();
	}
}