<?php
namespace WPGraphQL\Type\InterfaceType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class EnqueuedAsset
 *
 * @package WPGraphQL\Type
 */
class EnqueuedAsset {

	/**
	 * Register the Enqueued Script Type
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL Type Registry
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'EnqueuedAsset', [
			'description' => __( 'Asset enqueued by the CMS', 'wp-graphql' ),
			'resolveType' => function( $asset ) use ( $type_registry ) {

				/**
				 * The resolveType callback is used at runtime to determine what Type an object
				 * implementing the ContentNode Interface should be resolved as.
				 *
				 * You can filter this centrally using the "graphql_wp_interface_type_config" filter
				 * to override if you need something other than a Post object to be resolved via the
				 * $post->post_type attribute.
				 */
				$type = null;

				if ( isset( $asset['type'] ) ) {
					$type = $type_registry->get_type( $asset['type'] );
				}

				return ! empty( $type ) ? $type : null;

			},
			'fields'      => [
				'id'           => [
					'type'        => [
						'non_null' => 'ID',
					],
					'description' => __( 'The ID of the enqueued asset', 'wp-graphql' ),
				],
				'handle'       => [
					'type'        => 'String',
					'description' => __( 'The handle of the enqueued asset', 'wp-graphql' ),
				],
				'version'      => [
					'type'        => 'String',
					'description' => __( 'The version of the enqueued asset', 'wp-graphql' ),
				],
				'src'          => [
					'type'        => 'String',
					'description' => __( 'The source of the asset', 'wp-graphql' ),
				],
				'dependencies' => [
					'type'        => [
						'list_of' => 'EnqueuedScript',
					],
					'description' => __( 'Dependencies needed to use this asset', 'wp-graphql' ),
				],
				'args'         => [
					'type'        => 'Boolean',
					'description' => __( '@todo', 'wp-graphql' ),
				],
				'extra'        => [
					'type'        => 'String',
					'description' => __( 'Extra information needed for the script', 'wp-graphql' ),
					'resolve'     => function( $asset ) {
						return isset( $asset->extra['data'] ) ? $asset->extra['data'] : null;
					},
				],
			],
		]);

	}

}
