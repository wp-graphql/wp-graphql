<?php

namespace WPGraphQL\Type\Object;

/**
 * Class EnqueuedScript
 *
 * @package WPGraphQL\Type\Object
 */
class EnqueuedScript {

	/**
	 * Register the EnqueuedScript Type
	 */
	public static function register_type() {
		register_graphql_object_type( 'EnqueuedScript', [
			'description' => __( 'Script enqueued by the CMS', 'wp-graphql' ),
			'interfaces'  => [ 'Node', 'EnqueuedAsset' ],
			'fields'      => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
		] );
	}
}
