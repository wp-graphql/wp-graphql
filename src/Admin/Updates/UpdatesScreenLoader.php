<?php
/**
 * Handles plugin update checks and notifications on the plugins screen.
 *
 * @package WPGraphQL\Admin\Updates
 */

namespace WPGraphQL\Admin\Updates;

/**
 * Class UpdatesScreenLoader
 */
class UpdatesScreenLoader {
	/**
	 * The UpdateChecker instance.
	 *
	 * @var \WPGraphQL\Admin\Updates\UpdateChecker
	 */
	private $update_checker;

	/**
	 * The class constructor.
	 *
	 * Class properties are set inside the action.
	 */
	public function __construct() {
		add_action( 'admin_print_footer_scripts', [ $this, 'update_screen_modal' ] );
	}

	/**
	 * Show a warning message on the upgrades screen if the user tries to upgrade and has untested plugins.
	 */
	public function update_screen_modal(): void {
		// Bail if the plugin is not on the update list.
		$updateable_plugins = get_plugin_updates();
		if ( empty( $updateable_plugins['wp-graphql/wp-graphql.php']->update->new_version ) ) {
			return;
		}

		$this->update_checker = new UpdateChecker( $updateable_plugins['wp-graphql/wp-graphql.php'] );

		$should_update = $this->update_checker->should_autoupdate( true );

		if ( $should_update ) {
			return;
		}

		$untested_plugins = $this->update_checker->get_untested_plugins( 'major' );

		if ( empty( $untested_plugins ) ) {
			return;
		}

		$update_message = $this->get_untested_plugins_message( $untested_plugins );

		echo wp_kses_post( $update_message );

		$this->modal_js();
	}

	/**
	 * Gets the untested plugins warning message.
	 *
	 * @param array<string,array<string,mixed>> $untested_plugins The untested plugins.
	 */
	private function get_untested_plugins_message( array $untested_plugins ): string {
		$plugins = array_map(
			static function ( $plugin ) {
				return $plugin['Name'];
			},
			$untested_plugins
		);

		if ( empty( $plugins ) ) {
			return '';
		}

		$header  = __( 'Heads up!', 'wp-graphql' );
		$message = sprintf(
			// translators: %s - The WPGraphQL version.
			__( 'The installed versions of the following plugins have not been tested with WPGraphQL v%s. Please update them or confirm compatibility before updating WPGraphQL, or you may experience issues:', 'wp-graphql' ),
			$this->update_checker->new_version
		);

		ob_start();
		// Close the previous message.
		echo '</p>';

		?>
			<p>
				<strong><?php echo esc_html( $header ); ?></strong>
				<?php echo esc_html( $message ); ?>
			</p>
				
			<table cellspacing="0">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin', 'wp-graphql' ); ?></th>
						<th><?php esc_html_e( 'Version', 'wp-graphql' ); ?></th>
						<th><?php esc_html_e( 'Tested Up To', 'wp-graphql' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $untested_plugins as $plugin ) : ?>
						<tr>
							<td><?php echo esc_html( $plugin['Name'] ); ?></td>
							<td><?php echo esc_html( $plugin['Version'] ); ?></td>
							<td><?php echo esc_html( $plugin[ UpdateChecker::TESTED_UP_TO_HEADER ] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php
		// Reopen the message.
		echo '<p class="dummy">';

		return (string) ob_get_clean();
	}

	/**
	 * The modal JS for the plugin update message.
	 *
	 * @todo WIP.
	 */
	public function modal_js(): void {
		?>
		<script>
			// @todo
		</script>
		<?php
		$this->update_checker->modal_js();
	}
}
