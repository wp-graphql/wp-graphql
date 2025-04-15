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

			switch ( $name ) {
				case 'approve':
					$description = __( 'Comments that are publicly visible on content.', 'wp-graphql' );
				case 'hold':
					$description = __( 'Comments awaiting moderation before becoming publicly visible.', 'wp-graphql' );
				case 'spam':
					$description = __( 'Comments flagged as spam and hidden from public view.', 'wp-graphql' );
				case 'trash':
					$description = __( 'Comments marked for deletion but still recoverable. Hidden from public view.', 'wp-graphql' );
				default:
					$description = sprintf(
						// translators: %s is the comment status.
						__( 'Comments with the %s status', 'wp-graphql' ),
						$name
					);
			}

			$values[ WPEnumType::get_safe_name( $status ) ] = [
				'description' => $description,
				'value'       => $status,
			];
		}

		register_graphql_enum_type(
			'CommentStatusEnum',
			[
				'description' => __( 'Moderation state for user comments. Determines whether comments are publicly visible, pending approval, or marked as spam.', 'wp-graphql' ),
				'values'      => $values,
			]
		);
	}

}
