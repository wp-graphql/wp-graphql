<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class Previewable {

	/**
	 * Adds the Previewable Type to the WPGraphQL Registry
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'Previewable',
			[
				'description' => static function () {
					return __( 'Content that supports a draft preview mode. Allows viewing unpublished changes before they are made publicly available. Previewing unpublished changes requires appropriate permissions.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'isPreview'                 => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is a node in the preview state', 'wp-graphql' );
							},
						],
						'previewRevisionDatabaseId' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The database id of the preview node', 'wp-graphql' );
							},
						],
						'previewRevisionId'         => [
							'type'        => 'ID',
							'description' => static function () {
								return __( 'Whether the object is a node in the preview state', 'wp-graphql' );
							},
						],
					];
				},
				'resolveType' => static function ( Post $post ) use ( $type_registry ) {
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
