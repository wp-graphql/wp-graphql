<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class CommentStatusEnum {
	use EnumDescriptionTrait;

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
			$description = self::get_filtered_description( 'CommentStatusEnum', $status );

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

	/**
	 * Get the default description for a comment status.
	 *
	 * @param string               $value The comment status (approve, hold, etc.)
	 * @param array<string, mixed> $context Additional context data
	 */
	protected static function get_default_description( string $value, array $context = [] ): string {
		switch ( $value ) {
			case 'approve':
				return __( 'Comments that are publicly visible on content.', 'wp-graphql' );
			case 'hold':
				return __( 'Comments awaiting moderation before becoming publicly visible.', 'wp-graphql' );
			case 'spam':
				return __( 'Comments flagged as spam and hidden from public view.', 'wp-graphql' );
			case 'trash':
				return __( 'Comments marked for deletion but still recoverable. Hidden from public view.', 'wp-graphql' );
			default:
				return sprintf(
					// translators: %s is the comment status.
					__( 'Comments with the %s status', 'wp-graphql' ),
					$value
				);
		}
	}
}
