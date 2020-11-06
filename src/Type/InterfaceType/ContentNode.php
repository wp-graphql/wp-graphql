<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;

class ContentNode {

	/**
	 * Adds the ContentNode Type to the WPGraphQL Registry
	 *
	 * @param TypeRegistry $type_registry
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		/**
		 * The Content interface represents Post Types and the common shared fields
		 * across Post Type Objects
		 */
		register_graphql_interface_type(
			'ContentNode',
			[
				'description' => __( 'Nodes used to manage content', 'wp-graphql' ),
				'resolveType' => function( $post ) use ( $type_registry ) {

					/**
					 * The resolveType callback is used at runtime to determine what Type an object
					 * implementing the ContentNode Interface should be resolved as.
					 *
					 * You can filter this centrally using the "graphql_wp_interface_type_config" filter
					 * to override if you need something other than a Post object to be resolved via the
					 * $post->post_type attribute.
					 */
					$type      = null;
					$post_type = null;

					if ( isset( $post->post_type ) && 'revision' === $post->post_type ) {
						$parent    = get_post( $post->parentDatabaseId );
						$post_type = $parent->post_type;
					} else {
						$post_type = isset( $post->post_type ) ? $post->post_type : null;
					}

					$post_type_object = ! empty( $post_type ) ? get_post_type_object( $post_type ) : null;

					if ( isset( $post_type_object->graphql_single_name ) ) {
						$type = $type_registry->get_type( $post_type_object->graphql_single_name );
					}

					return ! empty( $type ) ? $type : null;

				},
				'fields'      => [
					'id'                        => [
						'type'        => [
							'non_null' => 'ID',
						],
						'description' => __( 'The globally unique identifier of the node.', 'wp-graphql' ),
					],
					'template'                  => [
						'type'        => 'ContentTemplate',
						'description' => __( 'The template assigned to a node of content', 'wp-graphql' ),
					],
					'databaseId'                => [
						'type'        => [
							'non_null' => 'Int',
						],
						'description' => __( 'The ID of the node in the database.', 'wp-graphql' ),
					],
					'date'                      => [
						'type'        => 'String',
						'description' => __( 'Post publishing date.', 'wp-graphql' ),
					],
					'dateGmt'                   => [
						'type'        => 'String',
						'description' => __( 'The publishing date set in GMT.', 'wp-graphql' ),
					],
					'enclosure'                 => [
						'type'        => 'String',
						'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
					],
					'status'                    => [
						'type'        => 'String',
						'description' => __( 'The current status of the object', 'wp-graphql' ),
					],
					'slug'                      => [
						'type'        => 'String',
						'description' => __( 'The uri slug for the post. This is equivalent to the WP_Post->post_name field and the post_name column in the database for the "post_objects" table.', 'wp-graphql' ),
					],
					'modified'                  => [
						'type'        => 'String',
						'description' => __( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' ),
					],
					'modifiedGmt'               => [
						'type'        => 'String',
						'description' => __( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' ),
					],
					'guid'                      => [
						'type'        => 'String',
						'description' => __( 'The global unique identifier for this post. This currently matches the value stored in WP_Post->guid and the guid column in the "post_objects" database table.', 'wp-graphql' ),
					],
					'desiredSlug'               => [
						'type'        => 'String',
						'description' => __( 'The desired slug of the post', 'wp-graphql' ),
					],
					'link'                      => [
						'type'        => 'String',
						'description' => __( 'The permalink of the post', 'wp-graphql' ),
					],
					'uri'                       => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'URI path for the resource', 'wp-graphql' ),
					],
					'isRestricted'              => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
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
			]
		);

	}

}
