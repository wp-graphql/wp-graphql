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
				'description' => __( 'The root mutation', 'wp-graphql' ),
				'fields'      => [
					'increaseCount' => [
						'type'        => 'Int',
						'description' => __( 'Increase the count.', 'wp-graphql' ),
						'args'        => [
							'count' => [
								'type'        => 'Int',
								'description' => __( 'The count to increase', 'wp-graphql' ),
							],
						],
						'resolve'     => function ( $root, $args ) {
							return isset( $args['count'] ) ? absint( $args['count'] ) + 1 : null;
						},
					],
				],
			]
		);

	}
}
