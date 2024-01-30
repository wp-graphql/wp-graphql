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
	 * @var array<array>
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

		$this->admin_notices = [
			'wpgraphql-gravity-forms' => [
				'type' => 'danger',
				'message' => __( 'You are using WPGraphQL and Advanced Custom Fields. Have you seen the new <a href="https://acf.wpgraphql.com/" target="_blank" rel="nofollow">WPGraphQL for ACF</a>?', 'wp-graphql' ),
				'is_dismissable' => false,
			],
			'wpgraphql-acf-announcement' => [
                'type' => 'warning',
				'message' => __( 'You are using WPGraphQL and Advanced Custom Fields. Have you seen the new <a href="https://acf.wpgraphql.com/" target="_blank" rel="nofollow">WPGraphQL for ACF</a>?', 'wp-graphql' ),
				'is_dismissable' => true,
			]
		];

        $this->dismissed_notices = get_option( 'wpgraphql_dismissed_admin_notices', [] );

		do_action( 'graphql_admin_notices_init', $this );
		add_action( 'admin_notices', [ $this, 'maybe_display_notices' ] );
		add_action( 'admin_init', [ $this, 'handle_dismissal_of_acf_notice' ] );
	}

	/**
	 * Return all admin notices
	 *
	 * @return array<mixed>
	 */
	public function get_admin_notices(): array {
        $dismissed_notices = $this->dismissed_notices;
        foreach ( $dismissed_notices as $dismissed_notice ) {
            unset( $this->admin_notices[ $dismissed_notice ] );
        }

        return $this->admin_notices;
	}

	/**
	 * @param string $slug Return the admin notice corresponding with the given slug
	 *
	 * @return array<mixed>
	 */
	public function get_admin_notice( string $slug ): array {
        $notices = $this->get_admin_notices();
		return $notices[ $slug ] ?? [];
	}

	/**
	 * @param string       $slug The slug identifying the admin notice
	 * @param array<mixed> $config The config of the admin notice
	 *
	 * @return array<mixed>
	 */
	public function add_admin_notice( string $slug, array $config ): array {
		$this->admin_notices[ $slug ] = $config;
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
		if ( isset( $this->admin_notices[ $slug ] ) ) {
			unset( $this->admin_notices[ $slug ] );
		}
		return $this->admin_notices;
	}

	/**
     * Determine whether a notice is dismissable or not
     *
	 * @param array $notice The notice to check whether its dismissable or not
	 *
	 * @return bool
	 */
    public function is_notice_dismissable( array $notice = [] ): bool {
        return ( ! isset( $notice['is_dismissable'] ) || false !== (bool) $notice['is_dismissable'] );
    }

	/**
	 * @return void
	 */
	public function maybe_display_notices(): void {
		if ( ! $this->is_plugin_scoped_page() ) {
			return;
		}

		if ( ! $this->should_display_acf_notice() ) {
			return;
		}

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
                margin-top: <?php echo count($notices) * 45 ?>px;
            }
            .wpgraphql-admin-notice {
                position: relative;
                text-decoration: none;
            }
            .wpgraphql-admin-notice .notice-dismiss {
                text-decoration: none;
            }

        </style>
        <?php
        $count = 0;
		foreach ( $notices as $notice_slug => $notice ) {
			?>
            <div style="top: <?php echo ($count * 45) ?>px" id="wpgraphql-admin-notice-<?php echo $notice_slug; ?>" class="wpgraphql-admin-notice notice notice-<?php echo $notice['type'] ?? 'success' ?> <?php echo $this->is_notice_dismissable( $notice ) ? 'is-dismissable' : '' ?>">
                <p><?php echo ! empty( $notice['message'] ) ? wp_kses_post( $notice['message'] ) : ''; ?></p>
                <?php if ( $this->is_notice_dismissable( $notice ) ) {
	                $dismiss_acf_nonce = wp_create_nonce( 'wpgraphql_disable_acf_notice_nonce' );
	                $dismiss_url       = add_query_arg( [
		                'wpgraphql_disable_acf_notice_nonce' => $dismiss_acf_nonce,
		                'wpgraphql_disable_notice'           => $notice_slug,
	                ] );
                ?>
                <a href="<?php echo esc_url( $dismiss_url ); ?>" class="notice-dismiss">
                    <span class="screen-reader-text"><?php esc_html_e( 'Dismiss', 'wp-graphql' ); ?></span>
                </a>
                <?php } ?>
            </div>
			<?php
			$count++;
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
	 * Checks if an admin notice should be displayed for WPGraphqlQL for ACF
	 */
	protected function should_display_acf_notice(): bool {
		if ( ! class_exists( 'ACF' ) ) {
			return false;
		}

		// Bail if new version of WPGraphQL for ACF is active.
		if ( class_exists( 'WPGraphQLAcf' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Handles the dismissal of the ACF notice.
	 * set_transient reference: https://developer.wordpress.org/reference/functions/set_transient/
	 * This function sets a transient to remember the dismissal status of the notice.
	 */
	public function handle_dismissal_of_acf_notice(): void {
		$nonce       = isset( $_GET['wpgraphql_disable_acf_notice_nonce'] ) ? sanitize_text_field( $_GET['wpgraphql_disable_acf_notice_nonce'] ) : null;

        if ( empty( $nonce ) || false === wp_verify_nonce( $nonce, 'wpgraphql_disable_acf_notice_nonce' ) ) {
			return;
		}

		$notice_slug = isset( $_GET['wpgraphql_disable_notice'] )  ? sanitize_text_field( $_GET['wpgraphql_disable_notice'] ) : null;

        if ( empty( $notice_slug ) ) {
            return;
        }

		$disabled   = get_option( 'wpgraphql_dismissed_admin_notices', [] );
		$disabled[] = $notice_slug;
		update_option( 'wpgraphql_dismissed_admin_notices', array_unique( $disabled ) );
	}
}
