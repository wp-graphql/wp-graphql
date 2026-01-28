<?php
/**
 * Content
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Document;

class AdminErrors {

	const TRANSIENT_NAME      = 'graphql_save_graphql_query_validation_error_messages';
	const MESSAGE_TTL_SECONDS = 60;

	/**
	 * @return void
	 */
	public function init() {
		add_action( 'admin_notices', [ $this, 'display_validation_messages' ] );
		add_filter( 'post_updated_messages', [ $this, 'post_updated_messages_cb' ] );
	}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function add_message( $message ) {
		set_transient( self::TRANSIENT_NAME, [ $message ], self::MESSAGE_TTL_SECONDS );
	}

	/**
	 * @return void
	 */
	public function display_validation_messages() {
		$screen = get_current_screen();
		if ( $screen && Document::TYPE_NAME !== $screen->post_type ) {
			return;
		}

		$error_messages = get_transient( self::TRANSIENT_NAME );
		if ( empty( $error_messages ) ) {
			return;
		}

		foreach ( $error_messages as $message ) {
			$html = sprintf( '<div id="plugin-message" class="error below-h2"><p>%s</p></div>', $message );

			/** @var array[] */
			$allowed_html = [
				'div' => [
					'id'    => true,
					'class' => true,
				],
				'p'   => true,
			];

			echo wp_kses(
				$html,
				$allowed_html
			);
		}

		delete_transient( self::TRANSIENT_NAME );
	}

	/**
	* Filters the post updated messages.
	*
	* @param array[] $messages Post updated messages.
	* @return array[]
	*/
	public function post_updated_messages_cb( $messages ) {
		// If have admin error message, don't display the 'Post Published' message for this post type
		$error_messages = get_transient( self::TRANSIENT_NAME );
		if ( ! empty( $error_messages ) ) {
			// phpcs:ignore
			$message_number                                     = isset( $_GET['message'] ) ? absint( $_GET['message'] ) : 0;
			$messages[ Document::TYPE_NAME ][ $message_number ] = '';
		}

		return $messages;
	}
}
