<?php
/**
 * Handles plugin update checks and notifications on the plugins screen.
 *
 * Code is inspired by and adapted from WooCommerce's WC_Plugins_Screen_Updates class.
 *
 * @see https://github.com/woocommerce/woocommerce/blob/5f04212f8188e0f7b09f6375d1a6c610fac8a631/plugins/woocommerce/includes/admin/plugin-updates/class-wc-plugins-screen-updates.php
 * *
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

		// @todo - maybe show upgrade notice?
		$update_message = '';

		$untested_plugins = $this->update_checker->get_untested_plugins( 'major' );

		if ( ! empty( $untested_plugins ) ) {
			$update_message .= $this->get_untested_plugins_message( $untested_plugins );
			$update_message .= $this->update_checker->get_untested_plugins_modal( $untested_plugins );
		}

		// Handle dangling <p> tags from the default update message.
		$update_message = sprintf( '</p>%s<p class="hidden">', $update_message );

		// Output the JS for the modal.
		add_action( 'admin_print_footer_scripts', [ $this, 'modal_js' ] );

		echo wp_kses_post( $update_message );
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

		ob_start();
		?>
		<div class="wp-graphql-update-notice">
			<p class="warning"><strong><?php echo esc_html__( 'Untested Plugins:', 'wp-graphql' ); ?></strong></p>
			<?php echo wp_kses_post( $this->update_checker->get_compatibility_warning_message( $untested_plugins ) ); ?>
		</div>

		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The modal JS for the plugin update message.
	 */
	public function modal_js(): void {
		?>
		<script>
			( function( $ ) {
				var $update_box = $( '#wp-graphql-update' );
				var $update_link = $update_box.find('a.update-link').first();
				var update_url = $update_link.attr( 'href' );

				// Set up thickbox.
				$update_link.removeClass( 'update-link' );
				$update_link.addClass( 'wp-graphql-thickbox' );
				$update_link.attr( 'href', '#TB_inline?height=600&width=550&inlineId=wp-graphql-update-modal' );

				// Trigger the update if the user accepts the modal's warning.
				$( '#wp-graphql-update-modal .accept' ).on( 'click', function( evt ) {
					evt.preventDefault();
					tb_remove();
					$update_link.removeClass( 'wc-thickbox open-plugin-details-modal' );
					$update_link.addClass( 'update-link' );
					$update_link.attr( 'href', update_url );
					$update_link.trigger( 'click' );
				});

				$( '#wp-graphql-update-modal .cancel' ).on( 'click', function( evt ) {
					evt.preventDefault();
					tb_remove();
				});
			})( jQuery );
		</script>

		<?php
		$this->update_checker->modal_js();
	}
}
