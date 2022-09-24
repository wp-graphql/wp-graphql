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

		$stati = get_comment_statuses();

		/**
		 * Loop through the post_stati
		 */
		foreach ( $stati as $status => $name ) {

			$values[ WPEnumType::get_safe_name( $status ) ] = [
				'description' => sprintf( __( 'Comments with the %1$s status', 'wp-graphql' ), $name ),
				'value'       => $status,
			];
		}

		register_graphql_enum_type(
			'CommentStatusEnum',
			[
				'description' => __( 'The status of the comment object.', 'wp-graphql' ),
				'values'      => $values,
			]
		);

	}
}
