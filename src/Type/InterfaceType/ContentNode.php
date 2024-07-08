<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class ContentNode {

	/**
	 * Get the handles of all scripts enqueued for a given post
	 *
	 * @param array $queue
	 *
	 * @return array
	 */
	public static function get_enqueued_scripts_handles( $queue ) {
		global $wp_scripts;
		$registered_scripts = $wp_scripts->registered;
		$handles = [];
		foreach ( $queue as $handle ) {
			if ( empty( $registered_scripts[ $handle ] ) ) {
				continue;
			}
			$script = $registered_scripts[ $handle ];
			if ( ! empty( $script->src ) ) {
				$handles[] = $script->handle;
			}

			$dependencies = self::get_enqueued_scripts_handles( $script->deps );
			if ( empty( $dependencies ) ) {
				continue;
			}
	
			//$dependencies = array_reverse( $dependencies );
			array_unshift( $handles, ...$dependencies );
		}

		return array_values( array_unique( $handles ) );
	}

	public static function get_enqueued_styles_handles( $queue ) {
		global $wp_styles;
		$registered_scripts = $wp_styles->registered;
		$handles = [];
		foreach ( $queue as $handle ) {
			if ( empty( $registered_scripts[ $handle ] ) ) {
				continue;
			}
			$script = $registered_scripts[ $handle ];
			if ( ! empty( $script->src ) ) {
				$handles[] = $script->handle;
			}

			$dependencies = self::get_enqueued_styles_handles( $script->deps );
			if ( empty( $dependencies ) ) {
				continue;
			}
	
			//$dependencies = array_reverse( $dependencies );
			array_unshift( $handles, ...$dependencies );
		}

		return array_values( array_unique( $handles ) );
	}



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
				'description' => __( 'Nodes used to manage content', 'wp-graphql' ),
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
							// Simulate WP template rendering
							ob_start();
							wp_head();
							$source->contentRendered;
							wp_footer();
							ob_get_clean();
							$queue = self::get_enqueued_scripts_handles( $source->enqueuedScriptsQueue ?? [] );
							$source = (object) [ 'enqueuedScriptsQueue' => $queue ];
							
							// Reset the scripts queue to avoid conflicts with other queries
							global $wp_scripts;
							$wp_scripts->reset();
					
							$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'enqueuedStylesheets' => [
						'toType'  => 'EnqueuedStylesheet',
						'resolve' => static function ( $source, $args, $context, $info ) {
							// Simulate WP template rendering
							ob_start();
							wp_head();
							$source->contentRendered;
							do_action( 'get_sidebar', null, [] );
							wp_footer();
							ob_get_clean();
							$queue = self::get_enqueued_styles_handles( $source->enqueuedStylesheetsQueue ?? [] );
							$source = (object) [ 'enqueuedStylesheetsQueue' => $queue ];

							// Reset the styles queue to avoid conflicts with other queries
							global $wp_styles;
							$wp_styles->reset();

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
				'fields'      => [
					'contentTypeName'           => [
						'type'        => [ 'non_null' => 'String' ],
						'description' => __( 'The name of the Content Type the node belongs to', 'wp-graphql' ),
						'resolve'     => static function ( $node ) {
							return $node->post_type;
						},
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
