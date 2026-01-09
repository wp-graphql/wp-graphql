<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class CommentStatusEnum {

	/**
	 * Register the CommentStatusEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$values = [];

		$stati = \get_comment_statuses();

		/**
		 * Loop through the post_stati
		 */
		foreach ( $stati as $status => $name ) {
			$values[ WPEnumType::get_safe_name( $status ) ] = [
				'description' => static function () use ( $name ) {
					return self::get_status_description( $name );
				},
				'value'       => $status,
			];
		}

		register_graphql_enum_type(
			'CommentStatusEnum',
			[
				'description' => static function () {
					return __( 'Moderation state for user comments. Determines whether comments are publicly visible, pending approval, or marked as spam.', 'wp-graphql' );
				},
				'values'      => $values,
			]
		);
	}

	/**
	 * Get the description for a comment status
	 *
	 * @param string $status_name The name of the comment status.
	 * @return string The description for the comment status.
	 *
	 * @since 2.3.0
	 */
	protected static function get_status_description( $status_name ) {
		switch ( $status_name ) {
			case 'approve':
				$description = __( 'Comments that are publicly visible on content.', 'wp-graphql' );
				break;
			case 'hold':
				$description = __( 'Comments awaiting moderation before becoming publicly visible.', 'wp-graphql' );
				break;
			case 'spam':
				$description = __( 'Comments flagged as spam and hidden from public view.', 'wp-graphql' );
				break;
			case 'trash':
				$description = __( 'Comments marked for deletion but still recoverable. Hidden from public view.', 'wp-graphql' );
				break;
			default:
				$description = sprintf(
					// translators: %s is the comment status.
					__( 'Comments with the %s status', 'wp-graphql' ),
					$status_name
				);
		}

		return $description;
	}
}
