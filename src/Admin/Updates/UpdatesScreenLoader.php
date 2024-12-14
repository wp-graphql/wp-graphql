<?php
/**
 * Handles plugin update checks and notifications on the plugins screen.
 *
 * Code is inspired by and adapted from WooCommerce's WC_Updates_Screen_Updates class.
 *
 * @see https://github.com/woocommerce/woocommerce/blob/5f04212f8188e0f7b09f6375d1a6c610fac8a631/plugins/woocommerce/includes/admin/plugin-updates/class-wc-updates-screen-updates.php
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

		$this->update_checker = new UpdateChecker( $updateable_plugins['wp-graphql/wp-graphql.php']->update );

		if ( $this->update_checker->should_autoupdate( true ) ) {
			return;
		}

		$untested_plugins = $this->update_checker->get_untested_plugins( 'major' );

		if ( empty( $untested_plugins ) ) {
			return;
		}

		// Output the modal.
		echo wp_kses_post( $this->update_checker->get_untested_plugins_modal( $untested_plugins ) );

		$this->modal_js();
	}

	/**
	 * The modal JS for the plugin update message.
	 */
	public function modal_js(): void {
		?>
		<script>
			( function( $ ) {
				var modal_dismissed = false;

				// Show the modal if the WC upgrade checkbox is checked.
				var show_modal_if_checked = function() {
					if ( modal_dismissed ) {
						return;
					}
					var $checkbox = $( 'input[value="wp-graphql/wp-graphql.php"]' );
					if ( $checkbox.prop( 'checked' ) ) {
						$( '#wp-graphql-upgrade-warning' ).trigger( 'click' );
					}
				}

				$( '#plugins-select-all, input[value="wp-graphql/wp-graphql.php"]' ).on( 'change', function() {
					show_modal_if_checked();
				} );

				// Add a hidden thickbox link to use for bringing up the modal.
				$('body').append( '<a href="#TB_inline?height=600&width=550&inlineId=wp-graphql-update-modal" class="wp-graphql-thickbox" id="wp-graphql-upgrade-warning" style="display:none"></a>' );

				// Don't show the modal again once it's been accepted.
				$( '#wp-graphql-update-modal .accept' ).on( 'click', function( evt ) {
					evt.preventDefault();
					modal_dismissed = true;
					tb_remove();
				});

				// Uncheck the WC update checkbox if the modal is canceled.
				$( '#wp-graphql-update-modal .cancel' ).on( 'click', function( evt ) {
					evt.preventDefault();
					$( 'input[value="wp-graphql/wp-graphql.php"]' ).prop( 'checked', false );
					tb_remove();
				});
			})( jQuery );
		</script>

		<?php
		$this->update_checker->modal_js();
	}
}
