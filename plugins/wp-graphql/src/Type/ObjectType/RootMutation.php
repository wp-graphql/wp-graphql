<?php

namespace WPGraphQL\Type\ObjectType;

class RootMutation {

	/**
	 * Register RootMutation type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'RootMutation',
			[
				'description' => static function () {
					return __( 'The root mutation', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'increaseCount' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Increase the count.', 'wp-graphql' );
							},
							'args'        => [
								'count' => [
									'type'        => 'Int',
									'description' => static function () {
										return __( 'The count to increase', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $root, $args ) {
								return isset( $args['count'] ) ? absint( $args['count'] ) + 1 : null;
							},
						],
					];
				},
			]
		);
	}
}
