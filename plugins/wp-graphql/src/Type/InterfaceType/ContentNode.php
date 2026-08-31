<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQLRelay\Relay;
use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\Connection\EnqueuedAssets;

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
						'toType'         => 'EnqueuedScript',
						'connectionArgs' => EnqueuedAssets::get_connection_args(),
						'resolve'        => static function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'enqueuedStylesheets' => [
						'toType'         => 'EnqueuedStylesheet',
						'connectionArgs' => EnqueuedAssets::get_connection_args(),
						'resolve'        => static function ( $source, $args, $context, $info ) {
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
								return __( 'The URL-friendly, human-readable identifier for the content node, used in its permalink.', 'wp-graphql' );
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
								return __( 'The global unique identifier for this content node. This is a stable, unique identifier for the node that does not change even if the node is moved or its url changes.', 'wp-graphql' );
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
							'type'           => 'Boolean',
							'description'    => static function () {
								return __( 'Whether the object is a node in the preview state', 'wp-graphql' );
							},
							// When an authorized preview targets this node, report whether previewed
							// values are actually being overlaid (an autosave to overlay from, or a
							// previewed featured image), so a client can render a truthful preview
							// state: "authorized but nothing to preview" resolves false, exactly
							// like a request without preview context.
							'previewResolve' => static function ( $source, $args, $context, $info, array $preview ) {
								return ! empty( $preview['revisionDatabaseId'] ) || isset( $preview['featuredImageDatabaseId'] );
							},
						],
						'previewRevisionDatabaseId' => [
							'type'           => 'Int',
							'description'    => static function () {
								return __( 'The database id of the preview node', 'wp-graphql' );
							},
							// Under preview context, expose the actual overlay source (the autosave
							// resolved for this request) rather than the latest revision.
							'previewResolve' => static function ( $source, $args, $context, $info, array $preview ) {
								return ! empty( $preview['revisionDatabaseId'] ) ? (int) $preview['revisionDatabaseId'] : null;
							},
						],
						'previewRevisionId'         => [
							'type'           => 'ID',
							'description'    => static function () {
								return __( 'The globally unique ID of the preview node', 'wp-graphql' );
							},
							'previewResolve' => static function ( $source, $args, $context, $info, array $preview ) {
								return ! empty( $preview['revisionDatabaseId'] ) ? Relay::toGlobalId( 'post', (string) $preview['revisionDatabaseId'] ) : null;
							},
						],
					];
				},
			]
		);
	}
}
