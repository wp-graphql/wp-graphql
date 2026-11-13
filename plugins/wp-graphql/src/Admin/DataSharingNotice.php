<?php

namespace WPGraphQL\Admin;

/**
 * Class DataSharingNotice
 *
 * The admin notice that asks about data sharing, in place of each plugin's own Appsero notice. One
 * notice covers every plugin registered with register_graphql_data_sharing_plugin(), and asks two
 * separate questions:
 *
 * - Usage data: Allow or No thanks, for every plugin that hasn't chosen yet. This is what the
 *   Appsero notice asked.
 * - Email updates: an address and a choice of plugins, with a button of its own. Nothing is checked
 *   to start, and answering one question never answers the other. After the usage data choice, the
 *   notice comes back once with only this part, so someone who answered that first still sees it.
 *   Each user can dismiss it for themselves.
 *
 * Only users with `manage_options` see it, the same as the Appsero notice.
 *
 * @package WPGraphQL\Admin
 *
 * @since 2.29.0
 */
final class DataSharingNotice {

	/**
	 * The admin-post action of the usage data form.
	 */
	public const USAGE_ACTION = 'graphql_data_sharing_usage';

	/**
	 * The admin-post action of the email updates form.
	 */
	public const EMAIL_ACTION = 'graphql_data_sharing_email';

	/**
	 * The admin-post action of the link that stops asking the current user about email updates.
	 */
	public const DISMISS_ACTION = 'graphql_data_sharing_email_dismiss';

	/**
	 * The query argument the forms redirect back with: `decided` or `emailed`.
	 */
	public const QUERY_ARG = 'graphql_data_sharing_notice';

	/**
	 * The user meta that records that a user doesn't want to be asked about email updates.
	 */
	public const DISMISSED_META = 'graphql_email_updates_notice_dismissed';

	/**
	 * @var \WPGraphQL\Admin\EmailUpdates
	 */
	private $email_updates;

	/**
	 * @param \WPGraphQL\Admin\EmailUpdates $email_updates The email updates rows, which make the sign-up request.
	 */
	public function __construct( EmailUpdates $email_updates ) {
		$this->email_updates = $email_updates;
	}

	/**
	 * Register the hooks.
	 */
	public function init(): void {
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'admin_post_' . self::USAGE_ACTION, [ $this, 'handle_usage' ] );
		add_action( 'admin_post_' . self::EMAIL_ACTION, [ $this, 'handle_email' ] );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ $this, 'handle_dismiss' ] );
	}

	/**
	 * The registered plugins whose site hasn't chosen about usage data yet.
	 *
	 * @return array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}>
	 */
	public function get_undecided_plugins(): array {
		return array_filter(
			DataSharing::get_registered_plugins(),
			static function ( $plugin ) {
				return $plugin['usage_tracking']->is_undecided();
			}
		);
	}

	/**
	 * The registered plugins the current user could still sign up for email updates for: those with no
	 * confirmation request recorded, unless the user stopped being asked.
	 *
	 * @return array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}>
	 */
	public function get_email_plugins(): array {
		if ( get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return [];
		}

		return array_diff_key( DataSharing::get_registered_plugins(), $this->email_updates->get_requests() );
	}

	/**
	 * Show the notice.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only chooses what to show.
		$state = isset( $_GET[ self::QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : '';

		$undecided = $this->get_undecided_plugins();
		$email     = $this->get_email_plugins();

		if ( 'emailed' === $state ) {
			$this->render_messages();

			// The email question was just answered, so only the usage data question can still show.
			$email = [];
		}

		$show_usage = ! empty( $undecided );
		$show_email = ! empty( $email ) && ( $show_usage || 'decided' === $state );

		if ( ! $show_usage && ! $show_email ) {
			return;
		}

		echo '<div class="notice notice-info" id="graphql-data-sharing-notice">';

		if ( $show_usage ) {
			$this->render_usage( $undecided );
		} else {
			echo '<p><strong>' . esc_html__( 'Your choice was saved. Want email updates too?', 'wp-graphql' ) . '</strong></p>';
		}

		if ( $show_email ) {
			$this->render_email( $email, $show_usage );
		}

		echo '</div>';
	}

	/**
	 * The usage data question.
	 *
	 * @param array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}> $plugins The plugins that haven't chosen yet.
	 */
	private function render_usage( array $plugins ): void {
		echo '<p><strong>';
		printf(
			// translators: %s is a list of plugin names.
			esc_html__( 'Help improve %s? Share usage data with the WPGraphQL team.', 'wp-graphql' ),
			esc_html( wp_sprintf_l( '%l', wp_list_pluck( $plugins, 'name' ) ) )
		);
		echo '</strong></p>';

		echo '<p>';
		echo esc_html__( 'Allowing sends details about this site to help guide development, now and then about once a week: server environment and WordPress version, site name, URL and IP address, site language, active theme and plugins, number of users, and this site\'s admin email address and an administrator\'s name. It doesn\'t sign anyone up for emails.', 'wp-graphql' );
		echo ' <a href="https://appsero.com/privacy-policy/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more', 'wp-graphql' ) . '</a> ';
		printf(
			// translators: %s is a link to the Data Sharing tab of the WPGraphQL settings page.
			esc_html__( 'You can change this at any time under %s.', 'wp-graphql' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=graphql-settings#' . DataSharing::SECTION ) ) . '">' . esc_html__( 'GraphQL → Settings → Data Sharing', 'wp-graphql' ) . '</a>'
		);
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::USAGE_ACTION ) . '">';
		wp_nonce_field( self::USAGE_ACTION );
		echo '<p>';
		echo '<button type="submit" name="choice" value="allow" class="button button-primary">' . esc_html__( 'Allow', 'wp-graphql' ) . '</button> ';
		echo '<button type="submit" name="choice" value="decline" class="button">' . esc_html__( 'No thanks', 'wp-graphql' ) . '</button>';
		echo '</p>';
		echo '</form>';
	}

	/**
	 * The email updates question.
	 *
	 * @param array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}> $plugins   The plugins the user can sign up for.
	 * @param bool                                                                                                                  $separate Whether the usage data question is shown above it.
	 */
	private function render_email( array $plugins, bool $separate ): void {
		if ( $separate ) {
			echo '<hr>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::EMAIL_ACTION ) . '">';
		wp_nonce_field( self::EMAIL_ACTION );

		if ( 1 === count( $plugins ) ) {
			$plugin = reset( $plugins );

			echo '<input type="hidden" name="plugins[]" value="' . esc_attr( $plugin['slug'] ) . '">';
			echo '<p>';
			printf(
				// translators: %s is the plugin name.
				esc_html__( 'Email me about security releases and breaking changes in %s.', 'wp-graphql' ),
				esc_html( $plugin['name'] )
			);
			echo '</p>';
		} else {
			echo '<p>' . esc_html__( 'Email me about security releases and breaking changes in:', 'wp-graphql' ) . '</p>';
			echo '<p>';
			foreach ( $plugins as $plugin ) {
				echo '<label style="margin-right:1em"><input type="checkbox" name="plugins[]" value="' . esc_attr( $plugin['slug'] ) . '"> ' . esc_html( $plugin['name'] ) . '</label>';
			}
			echo '</p>';
		}

		$user = wp_get_current_user();

		echo '<p>';
		echo '<label for="graphql-data-sharing-email" class="screen-reader-text">' . esc_html__( 'Email address', 'wp-graphql' ) . '</label>';
		echo '<input type="email" id="graphql-data-sharing-email" name="address" class="regular-text" placeholder="' . esc_attr( $user->exists() ? (string) $user->user_email : '' ) . '" required> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Send confirmation email', 'wp-graphql' ) . '</button> ';
		echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION ) ) . '">' . esc_html__( 'Don\'t ask me about email', 'wp-graphql' ) . '</a>';
		echo '</p>';

		echo '<p class="description">' . esc_html__( 'This is separate from sharing usage data. Nothing is subscribed until you click the link in the confirmation email, and you can unsubscribe with the link in any of the emails.', 'wp-graphql' ) . '</p>';
		echo '</form>';
	}

	/**
	 * Show what happened to the email updates sign-up, once.
	 */
	private function render_messages(): void {
		$key      = $this->get_messages_key();
		$messages = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $messages ) ) {
			return;
		}

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) || ! isset( $message['type'], $message['message'] ) ) {
				continue;
			}

			$type = in_array( $message['type'], [ 'success', 'warning', 'error', 'info' ], true ) ? $message['type'] : 'info';

			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( (string) $message['message'] ) . '</p></div>';
		}
	}

	/**
	 * The transient that carries the sign-up messages to the page after the redirect, per user.
	 */
	private function get_messages_key(): string {
		return 'graphql_data_sharing_notice_' . get_current_user_id();
	}

	/**
	 * Handle the usage data form, then go back to the page it was on.
	 */
	public function handle_usage(): void {
		$this->redirect( $this->process_usage() );
	}

	/**
	 * Apply the usage data choice to every plugin that hasn't chosen yet.
	 *
	 * @return string The URL to go back to.
	 */
	public function process_usage(): string {
		$this->check_request( self::USAGE_ACTION );

		$choice = isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( $_POST['choice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in check_request().

		if ( in_array( $choice, [ 'allow', 'decline' ], true ) ) {
			foreach ( $this->get_undecided_plugins() as $plugin ) {
				if ( 'allow' === $choice ) {
					$plugin['usage_tracking']->opt_in();
				} else {
					$plugin['usage_tracking']->opt_out();
				}
			}
		}

		return add_query_arg( self::QUERY_ARG, 'decided', $this->get_return_url() );
	}

	/**
	 * Handle the email updates form, then go back to the page it was on.
	 */
	public function handle_email(): void {
		$this->redirect( $this->process_email() );
	}

	/**
	 * Request a confirmation email for the plugins chosen, and keep what happened for the next page.
	 *
	 * @return string The URL to go back to.
	 */
	public function process_email(): string {
		$this->check_request( self::EMAIL_ACTION );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in check_request().
		$slugs   = isset( $_POST['plugins'] ) && is_array( $_POST['plugins'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['plugins'] ) ) : [];
		$address = isset( $_POST['address'] ) && is_string( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		set_transient( $this->get_messages_key(), $this->email_updates->sign_up( array_values( $slugs ), $address ), 5 * MINUTE_IN_SECONDS );

		return add_query_arg( self::QUERY_ARG, 'emailed', $this->get_return_url() );
	}

	/**
	 * Handle the link that stops asking the current user about email updates.
	 */
	public function handle_dismiss(): void {
		$this->redirect( $this->process_dismiss() );
	}

	/**
	 * Stop asking the current user about email updates.
	 *
	 * @return string The URL to go back to.
	 */
	public function process_dismiss(): string {
		$this->check_request( self::DISMISS_ACTION );

		update_user_meta( get_current_user_id(), self::DISMISSED_META, 1 );

		return $this->get_return_url();
	}

	/**
	 * Stop unless the request has a valid nonce and the user can manage options.
	 *
	 * @param string $action The nonce action.
	 */
	private function check_request( string $action ): void {
		check_admin_referer( $action );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change what this site shares.', 'wp-graphql' ), '', [ 'response' => 403 ] );
		}
	}

	/**
	 * The page the form was on, without the notice's own query argument.
	 */
	private function get_return_url(): string {
		$referer = wp_get_referer();

		return remove_query_arg( self::QUERY_ARG, false !== $referer ? $referer : admin_url() );
	}

	/**
	 * Redirect and end the request.
	 *
	 * @param string $url The URL to go to.
	 */
	private function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
