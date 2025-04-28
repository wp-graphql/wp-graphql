<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class ContentNode {

	/**
	 * Adds the ContentNode Type to the WPGraphQL Registry
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		/**
		 * The Content interface represents Post Types and the common shared fields
		 * across Post Type Objects
		 */
		register_graphql_interface_type(
			'ContentNode',
			[
				'interfaces'  => [ 'Node', 'UniformResourceIdentifiable' ],
				'description' => static function () {
					return __( 'Base interface for content objects like posts, pages, and media items. Provides common fields available across these content types.', 'wp-graphql' );
				},
				'connections' => [
					'contentType'         => [
						'toType'   => 'ContentType',
						'resolve'  => static function ( Post $source, $args, $context, $info ) {
							if ( $source->isRevision ) {
								$parent    = get_post( $source->parentDatabaseId );
								$post_type = $parent->post_type ?? null;
							} else {
								$post_type = $source->post_type ?? null;
							}

							if ( empty( $post_type ) ) {
								return null;
							}

							$resolver = new ContentTypeConnectionResolver( $source, $args, $context, $info );

							return $resolver->one_to_one()->set_query_arg( 'name', $post_type )->get_connection();
						},
						'oneToOne' => true,
					],
					'enqueuedScripts'     => [
						'toType'  => 'EnqueuedScript',
						'resolve' => static function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'enqueuedStylesheets' => [
						'toType'  => 'EnqueuedStylesheet',
						'resolve' => static function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );
							return $resolver->get_connection();
						},
					],
				],
				'resolveType' => static function ( Post $post ) use ( $type_registry ) {

					/**
					 * The resolveType callback is used at runtime to determine what Type an object
					 * implementing the ContentNode Interface should be resolved as.
					 *
					 * You can filter this centrally using the "graphql_wp_interface_type_config" filter
					 * to override if you need something other than a Post object to be resolved via the
					 * $post->post_type attribute.
					 */
					$type      = null;
					$post_type = isset( $post->post_type ) ? $post->post_type : null;

					if ( isset( $post->post_type ) && 'revision' === $post->post_type ) {
						$parent = get_post( $post->parentDatabaseId );
						if ( $parent instanceof \WP_Post ) {
							$post_type = $parent->post_type;
						}
					}

					$post_type_object = ! empty( $post_type ) ? get_post_type_object( $post_type ) : null;

					if ( isset( $post_type_object->graphql_single_name ) ) {
						$type = $type_registry->get_type( $post_type_object->graphql_single_name );
					}

					return ! empty( $type ) ? $type : null;
				},
				'fields'      => static function () {
					return [
						'contentTypeName'           => [
							'type'        => [ 'non_null' => 'String' ],
							'description' => static function () {
								return __( 'The name of the Content Type the node belongs to', 'wp-graphql' );
							},
							'resolve'     => static function ( $node ) {
								return $node->post_type;
							},
						],
						'template'                  => [
							'type'        => 'ContentTemplate',
							'description' => static function () {
								return __( 'The template assigned to a node of content', 'wp-graphql' );
							},
						],
						'databaseId'                => [
							'type'        => [
								'non_null' => 'Int',
							],
							'description' => static function () {
								return __( 'The ID of the node in the database.', 'wp-graphql' );
							},
						],
						'date'                      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Post publishing date.', 'wp-graphql' );
							},
						],
						'dateGmt'                   => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The publishing date set in GMT.', 'wp-graphql' );
							},
						],
						'enclosure'                 => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The RSS enclosure for the object', 'wp-graphql' );
							},
						],
						'status'                    => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The current status of the object', 'wp-graphql' );
							},
						],
						'slug'                      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The uri slug for the post. This is equivalent to the WP_Post->post_name field and the post_name column in the database for the "post_objects" table.', 'wp-graphql' );
							},
						],
						'modified'                  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' );
							},
						],
						'modifiedGmt'               => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' );
							},
						],
						'guid'                      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The global unique identifier for this post. This currently matches the value stored in WP_Post->guid and the guid column in the "post_objects" database table.', 'wp-graphql' );
							},
						],
						'desiredSlug'               => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The desired slug of the post', 'wp-graphql' );
							},
						],
						'link'                      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The permalink of the post', 'wp-graphql' );
							},
						],
						'isRestricted'              => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
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
			]
		);
	}
}
