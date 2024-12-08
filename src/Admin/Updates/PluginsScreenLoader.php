<?php
/**
 * Handles plugin update checks and notifications on the plugins screen.
 *
 * @package WPGraphQL\Admin\Updates
 */

namespace WPGraphQL\Admin\Updates;

/**
 * Class PluginsScreenLoader
 */
class PluginsScreenLoader {
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
		add_action( 'in_plugin_update_message-wp-graphql/wp-graphql.php', [ $this, 'in_plugin_update_message' ], 10, 2 );
	}

	/**
	 * Injects a warning message into the plugin update message.
	 *
	 * @param array<string,mixed> $args The plugin update message arguments.
	 * @param object              $response The plugin update response.
	 */
	public function in_plugin_update_message( $args, $response ): void {
		$this->update_checker = new UpdateChecker( $response );

		if ( $this->update_checker->should_autoupdate( true ) ) {
			return;
		}

		$update_message = '';

		$incompatible_plugins = $this->update_checker->get_incompatible_plugins( $this->update_checker->new_version, true );

		if ( ! empty( $incompatible_plugins ) ) {
			$update_message .= $this->get_incompatible_plugins_message( $incompatible_plugins );
		}

		$untested_plugins = $this->update_checker->get_untested_plugins( 'major' );

		if ( ! empty( $untested_plugins ) ) {
			$update_message .= $this->get_untested_plugins_message( $untested_plugins );
		}

		// Handle dangling <p> tags from the default update message.
		$update_message = sprintf( '</p>%s<p class="hidden">', $update_message );

		// Output the JS for the modal.
		add_action( 'admin_print_footer_scripts', [ $this, 'modal_js' ] );

		echo wp_kses_post( $update_message );
	}

	/**
	 * Gets the incompatible plugins warning message.
	 *
	 * @param array<string,array<string,mixed>> $incompatible_plugins The incompatible plugins.
	 */
	private function get_incompatible_plugins_message( array $incompatible_plugins ): string {
		$plugins = array_map(
			static function ( $plugin ) {
				return [
					'name'    => $plugin['Name'],
					'version' => $plugin[ UpdateChecker::VERSION_HEADER ],
				];
			},
			$incompatible_plugins
		);

		if ( empty( $plugins ) ) {
			return '';
		}

		$message = sprintf(
			// translators: %s - The WPGraphQL version.
			__( 'The following plugins are incompatible with WPGraphQL v%s and will be disabled upon updating:', 'wp-graphql' ),
			$this->update_checker->new_version
		);

		ob_start();
		?>
		
		<p>
			<strong><?php echo esc_html__( 'Incompatible Plugins', 'wp-graphql' ); ?></strong>
			<?php echo esc_html( $message ); ?>
		</p>

		<ul>
			<?php foreach ( $plugins as $plugin ) : ?>
				<li>
					<?php
					printf(
						// translators: %1$s - The plugin name, %2$s - The plugin version.
						esc_html__( '%1$s (requires at least WPGraphQL: v%2$s)', 'wp-graphql' ),
						esc_html( $plugin['name'] ),
						esc_html( $plugin['version'] )
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php

		return (string) ob_get_clean();
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

		$message = sprintf(
			// translators: %s - The WPGraphQL version.
			__( 'The installed versions of the following plugins have not been tested with WPGraphQL v%s. Please update them or confirm compatibility before updating WPGraphQL, or you may experience issues:', 'wp-graphql' ),
			$this->update_checker->new_version
		);

		ob_start();
		?>
			<p>
				<strong><?php echo esc_html__( 'Untested Plugins', 'wp-graphql' ); ?></strong>
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
