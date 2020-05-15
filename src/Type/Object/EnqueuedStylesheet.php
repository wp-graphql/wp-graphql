<?php

namespace WPGraphQL\Type\Object;

/**
 * Class EnqueuedStylesheet
 *
 * @package WPGraphQL\Type\Object
 */
class EnqueuedStylesheet {

	/**
	 * Register the EnqueuedStylesheet Type
	 */
	public static function register_type() {
		register_graphql_object_type( 'EnqueuedStylesheet', [
			'description' => __( 'Stylesheet enqueued by the CMS', 'wp-graphql' ),
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
