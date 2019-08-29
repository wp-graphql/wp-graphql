<?php

namespace WPGraphQL\Type\Object;

class RootMutation {
	public static function register_type() {

		register_graphql_object_type(
			'RootMutation',
			[
				'description' => __( 'The root mutation', 'wp-graphql' ),
				'fields'      => [
					'increaseCount' => [
						'type'    => 'Int',
						'args'    => [
							'count' => [
								'type'        => 'Int',
								'description' => __( 'The count to increase', 'wp-graphql' ),
							],
						],
						'resolve' => function( $root, $args ) {
							return isset( $args['count'] ) ? absint( $args['count'] ) + 1 : null;
						},
					],
				],
			]
		);

	}
}
