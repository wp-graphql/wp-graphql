<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQLRelay\Relay;

/**
 * Class EnqueuedStylesheet
 *
 * @package WPGraphQL\Type\Object
 */
class EnqueuedStylesheet {

	/**
	 * Register the EnqueuedStylesheet Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type( 'EnqueuedStylesheet', [
			'description' => __( 'Stylesheet enqueued by the CMS', 'wp-graphql' ),
			'interfaces'  => [ 'Node', 'EnqueuedAsset' ],
			'fields'      => [
				'id'  => [
					'type'    => [
						'non_null' => 'ID',
					],
					'resolve' => function ( $asset ) {
						return isset( $asset->handle ) ? Relay::toGlobalId( 'enqueued_stylesheet', $asset->handle ) : null;
					},
				],
				'src' => [
					'resolve' => function ( \_WP_Dependency $stylesheet ) {
						return isset( $stylesheet->src ) && is_string( $stylesheet->src ) ? $stylesheet->src : null;
					},
				],
			],
		] );
	}
}
