<?php
namespace WPGraphQL\Type\Enum;

class AvatarRatingEnum {

	/**
	 * Register the AvatarRatingEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'AvatarRatingEnum',
			[
				'description' => __( "Content rating filter for user avatars. Determines the maximum maturity level of avatars to display, following standard content rating classifications (G, PG, R, X).", 'wp-graphql' ),
				'values'      => [
					'G'  => [
						'description' => 'Indicates a G level avatar rating level.',
						'value'       => 'G',
					],
					'PG' => [
						'description' => 'Indicates a PG level avatar rating level.',
						'value'       => 'PG',
					],
					'R'  => [
						'description' => 'Indicates an R level avatar rating level.',
						'value'       => 'R',
					],
					'X'  => [
						'description' => 'Indicates an X level avatar rating level.',
						'value'       => 'X',
					],
				],
			]
		);
	}
}
