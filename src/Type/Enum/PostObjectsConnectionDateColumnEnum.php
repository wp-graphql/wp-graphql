<?php

namespace WPGraphQL\Type\Enum;

class PostObjectsConnectionDateColumnEnum {

	/**
	 * Register the PostObjectsConnectionDateColumnEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'PostObjectsConnectionDateColumnEnum',
			[
				'description' => static function () {
					return __( 'The column to use when filtering by date', 'wp-graphql' );
				},
				'values'      => [
					'DATE'     => [
						'value'       => 'post_date',
						'description' => static function () {
							return __( 'The date the comment was created in local time.', 'wp-graphql' );
						},
					],
					'MODIFIED' => [
						'value'       => 'post_modified',
						'description' => static function () {
							return __( 'The most recent modification date of the comment.', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
