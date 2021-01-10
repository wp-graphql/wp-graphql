<?php

namespace WPGraphQL\Type\Object;

use GraphQLRelay\Relay;

/**
 * Class EnqueuedScript
 *
 * @package WPGraphQL\Type\Object
 */
class EnqueuedScript {

	/**
	 * Register the EnqueuedScript Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type( 'EnqueuedScript', [
			'description' => __( 'Script enqueued by the CMS', 'wp-graphql' ),
			'interfaces'  => [ 'Node', 'EnqueuedAsset' ],
			'fields'      => [
				'id'  => [
					'type'    => [
						'non_null' => 'ID',
					],
					'resolve' => function( $asset ) {
						return isset( $asset->handle ) ? Relay::toGlobalId( 'enqueued_script', $asset->handle ) : null;
					},
				],
				'src' => [
					'resolve' => function( \_WP_Dependency $script ) {
						return isset( $script->src ) && is_string( $script->src ) ? $script->src : null;
					},
				],
			],
		] );
	}
}
