<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithFeaturedImage {
	/**
	 * @param TypeRegistry $type_registry Instance of the Type Registry
	 */
	public static function register_type( $type_registry ) {

		register_graphql_interface_type(
			'NodeWithFeaturedImage',
			[
				'description' => __( 'A node that can have a featured image set', 'wp-graphql' ),
				'fields'      => [
					'featuredImageId'         => [
						'type'        => 'ID',
						'description' => __( 'Globally unique ID of the featured image assigned to the node', 'wp-graphql' ),
					],
					'featuredImageDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'The database identifier for the featured image node assigned to the content node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
