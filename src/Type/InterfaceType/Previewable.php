<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class Previewable {

	/**
	 * Adds the Previewable Type to the WPGraphQL Registry
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'Previewable',
			[
				'description' => __( 'Nodes that can be seen in a preview (unpublished) state.', 'wp-graphql' ),
				'fields'      => [
					'isPreview'                 => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is a node in the preview state', 'wp-graphql' ),
					],
					'previewRevisionDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'The database id of the preview node', 'wp-graphql' ),
					],
					'previewRevisionId'         => [
						'type'        => 'ID',
						'description' => __( 'Whether the object is a node in the preview state', 'wp-graphql' ),
					],
				],
				'resolveType' => function ( Post $post ) use ( $type_registry ) {

					$type = 'Post';

					$post_type_object = isset( $post->post_type ) ? get_post_type_object( $post->post_type ) : null;

					if ( isset( $post_type_object->graphql_single_name ) ) {
						$type = $type_registry->get_type( $post_type_object->graphql_single_name );
					}

					return $type;
				},
			]
		);
	}
}
