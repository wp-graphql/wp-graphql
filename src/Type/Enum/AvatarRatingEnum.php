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
				'description' => static function () {
					return __( "What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are judged in that order. Default is the value of the 'avatar_rating' option", 'wp-graphql' );
				},
				'values'      => [
					'G'  => [
						'description' => static function () {
							return __( 'Indicates a G level avatar rating level.', 'wp-graphql' );
						},
						'value'       => 'G',
					],
					'PG' => [
						'description' => static function () {
							return __( 'Indicates a PG level avatar rating level.', 'wp-graphql' );
						},
						'value'       => 'PG',
					],
					'R'  => [
						'description' => static function () {
							return __( 'Indicates an R level avatar rating level.', 'wp-graphql' );
						},
						'value'       => 'R',
					],
					'X'  => [
						'description' => static function () {
							return __( 'Indicates an X level avatar rating level.', 'wp-graphql' );
						},
						'value'       => 'X',
					],
				],
			]
		);
	}
}
