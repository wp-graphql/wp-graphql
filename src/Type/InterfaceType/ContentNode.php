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
					$type = null;

					if ( isset( $post->post_type ) ) {
						$post_type_object = get_post_type_object( $post->post_type );

						if ( isset( $post_type_object->graphql_single_name ) ) {
							$type = $type_registry->get_type( $post_type_object->graphql_single_name );
						}
					}

					return ! empty( $type ) ? $type : null;

				},
				'fields'      => [
					'id'            => [
						'type'        => [
							'non_null' => 'ID',
						],
						'description' => __( 'The globally unique identifier of the node.', 'wp-graphql' ),
					],
					'ancestors'     => [
						'type'        => [
							'list_of' => 'PostObjectUnion',
						],
						'description' => esc_html__( 'Ancestors of the object', 'wp-graphql' ),
						'args'        => [
							'types' => [
								'type'        => [
									'list_of' => 'PostTypeEnum',
								],
								'description' => __( 'The types of ancestors to check for. Defaults to the same type as the current object', 'wp-graphql' ),
							],
						],
						'resolve'     => function( $source, $args, AppContext $context, ResolveInfo $info ) {
							$ancestor_ids = get_ancestors( $source->ID, $source->post_type );
							if ( empty( $ancestor_ids ) || ! is_array( $ancestor_ids ) ) {
								return null;
							}
							$context->getLoader( 'post_object' )->buffer( $ancestor_ids );

							return new Deferred(
								function() use ( $context, $ancestor_ids ) {
									// @codingStandardsIgnoreLine.
									return $context->getLoader( 'post_object' )->loadMany( $ancestor_ids );
								}
							);
						},
					],
					'databaseId'    => [
						'type'        => [
							'non_null' => 'Int',
						],
						'description' => __( 'The ID of the object in the database.', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, $context, $info ) {
							return absint( $post->ID );
						},
					],
					'author'        => [
						'type'        => 'User',
						'description' => __( "The author field will return a queryable User type matching the post's author.", 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( ! isset( $post->authorId ) || ! absint( $post->authorId ) ) {
								return null;
							};

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_user( $post->authorId, $context );
						},
					],
					'date'          => [
						'type'        => 'String',
						'description' => __( 'Post publishing date.', 'wp-graphql' ),
					],
					'dateGmt'       => [
						'type'        => 'String',
						'description' => __( 'The publishing date set in GMT.', 'wp-graphql' ),
					],

					'enclosure'     => [
						'type'        => 'String',
						'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
					],
					'status'        => [
						'type'        => 'String',
						'description' => __( 'The current status of the object', 'wp-graphql' ),
					],
					'parent'        => [
						'type'        => 'PostObjectUnion',
						'description' => __( 'The parent of the object. The parent object can be of various types', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( ! isset( $post->parentId ) || ! absint( $post->parentId ) ) {
								return null;
							}

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_post_object( $post->parentId, $context );
						},
					],
					'slug'          => [
						'type'        => 'String',
						'description' => __( 'The uri slug for the post. This is equivalent to the WP_Post->post_name field and the post_name column in the database for the "post_objects" table.', 'wp-graphql' ),
					],
					'modified'      => [
						'type'        => 'String',
						'description' => __( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' ),
					],
					'modifiedGmt'   => [
						'type'        => 'String',
						'description' => __( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' ),
					],
					'editLast'      => [
						'type'        => 'User',
						'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( ! isset( $post->editLastId ) || ! absint( $post->editLastId ) ) {
								return null;
							}

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_user( $post->editLastId, $context );
						},
					],
					'editLock'      => [
						'type'        => 'EditLock',
						'description' => __( 'If a user has edited the object within the past 15 seconds, this will return the user and the time they last edited. Null if the edit lock doesn\'t exist or is greater than 15 seconds', 'wp-graphql' ),
					],
					'guid'          => [
						'type'        => 'String',
						'description' => __( 'The global unique identifier for this post. This currently matches the value stored in WP_Post->guid and the guid column in the "post_objects" database table.', 'wp-graphql' ),
					],

					'desiredSlug'   => [
						'type'        => 'String',
						'description' => __( 'The desired slug of the post', 'wp-graphql' ),
					],
					'link'          => [
						'type'        => 'String',
						'description' => __( 'The permalink of the post', 'wp-graphql' ),
					],
					'uri'           => [
						'type'        => 'String',
						'description' => __( 'URI path for the resource', 'wp-graphql' ),
					],
					'isRestricted'  => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
					'featuredImage' => [
						'type'        => 'MediaItem',
						'description' => __( 'The featured image for the object', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( empty( $post->featuredImageId ) || ! absint( $post->featuredImageId ) ) {
								return null;
							}

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_post_object( $post->featuredImageId, $context );
						},
					],
					'terms'         => [
						'type'        => [
							'list_of' => 'TermObjectUnion',
						],
						'args'        => [
							'taxonomies' => [
								'type'        => [
									'list_of' => 'TaxonomyEnum',
								],
								'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
							],
						],
						'description' => __( 'Terms connected to the object', 'wp-graphql' ),
						'resolve'     => function( $source, $args ) {
							// @TODO eventually use a loader here to grab the taxonomies and pass them through the term model.
							/**
							 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
							 * otherwise use the default $allowed_taxonomies passed down
							 */
							$taxonomies = [];
							if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
								$taxonomies = $args['taxonomies'];
							} else {
								$connected_taxonomies = get_object_taxonomies( $source->post_type, 'names' );
								foreach ( $connected_taxonomies as $taxonomy ) {
									if ( in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
										$taxonomies[] = $taxonomy;
									}
								}
							}

							$tax_terms = [];
							if ( ! empty( $taxonomies ) ) {
								$term_query = new \WP_Term_Query(
									[
										'taxonomy'   => $taxonomies,
										'object_ids' => $source->ID,
									]
								);

								$fetched_terms = $term_query->get_terms();
								$tax_terms     = [];
								if ( ! empty( $fetched_terms ) ) {
									foreach ( $fetched_terms as $tax_term ) {
										$tax_terms[ $tax_term->term_id ] = new Term( $tax_term );
									}
								}
							}

							return ! empty( $tax_terms ) && is_array( $tax_terms ) ? $tax_terms : null;
						},
					],
					'termNames'     => [
						'type'        => [ 'list_of' => 'String' ],
						'args'        => [
							'taxonomies' => [
								'type'        => [
									'list_of' => 'TaxonomyEnum',
								],
								'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
							],
						],
						'description' => __( 'Terms connected to the object', 'wp-graphql' ),
						'resolve'     => function( $source, $args ) {
							/**
							 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
							 * otherwise use the default $allowed_taxonomies passed down
							 */
							$taxonomies = [];
							if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
								$taxonomies = $args['taxonomies'];
							} else {
								$connected_taxonomies = get_object_taxonomies( $source->post_type, 'names' );
								foreach ( $connected_taxonomies as $taxonomy ) {
									if ( in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
										$taxonomies[] = $taxonomy;
									}
								}
							}

							$tax_terms = [];
							if ( ! empty( $taxonomies ) ) {
								$term_query = new \WP_Term_Query(
									[
										'taxonomy'   => $taxonomies,
										'object_ids' => [ $source->ID ],
									]
								);

								$tax_terms = $term_query->get_terms();

							}
							$term_names = ! empty( $tax_terms ) && is_array( $tax_terms ) ? wp_list_pluck( $tax_terms, 'name' ) : [];

							return ! empty( $term_names ) ? $term_names : null;
						},
					],
					'termSlugs'     => [
						'type'        => [ 'list_of' => 'String' ],
						'args'        => [
							'taxonomies' => [
								'type'        => [
									'list_of' => 'TaxonomyEnum',
								],
								'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
							],
						],
						'description' => __( 'Terms connected to the object', 'wp-graphql' ),
						'resolve'     => function( $source, $args ) {
							/**
							 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
							 * otherwise use the default $allowed_taxonomies passed down
							 */
							$taxonomies = [];
							if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
								$taxonomies = $args['taxonomies'];
							} else {
								$connected_taxonomies = get_object_taxonomies( $source->post_type, 'names' );
								foreach ( $connected_taxonomies as $taxonomy ) {
									if ( in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
										$taxonomies[] = $taxonomy;
									}
								}
							}

							$tax_terms = [];
							if ( ! empty( $taxonomies ) ) {

								$term_query = new \WP_Term_Query(
									[
										'taxonomy'   => $taxonomies,
										'object_ids' => [ $source->ID ],
									]
								);

								$tax_terms = $term_query->get_terms();

							}
							$term_slugs = ! empty( $tax_terms ) && is_array( $tax_terms ) ? wp_list_pluck( $tax_terms, 'slug' ) : [];

							return ! empty( $term_slugs ) ? $term_slugs : null;
						},
					],
				],
			]
		);

	}

}
