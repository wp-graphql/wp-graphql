<?php

namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;

/**
 * Class TermObjects
 *
 * This class organizes the registration of connections to TermObjects
 *
 * @package WPGraphQL\Connection
 */
class TermObjects {

	/**
	 * Register connections to TermObjects
	 *
	 * @return void
	 */
	public static function register_connections() {

		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();

		register_graphql_connection(
			[
				'fromType'       => 'RootQuery',
				'toType'         => 'TermNode',
				'queryClass'     => 'WP_Term_Query',
				'fromFieldName'  => 'terms',
				'connectionArgs' => self::get_connection_args(
					[
						'taxonomies' => [
							'type'        => [ 'list_of' => 'TaxonomyEnum' ],
							'description' => __( 'The Taxonomy to filter terms by', 'wp-graphql' ),
						],
					]
				),
				'resolve'        => function( $source, $args, $context, $info ) {
					$taxonomies = isset( $args['where']['taxonomies'] ) && is_array( $args['where']['taxonomies'] ) ? $args['where']['taxonomies'] : \WPGraphQL::get_allowed_taxonomies();
					$resolver   = new TermObjectConnectionResolver( $source, $args, $context, $info, array_values( $taxonomies ) );
					$connection = $resolver->get_connection();

					return $connection;
				},
			]
		);

		/**
		 * Loop through the allowed_taxonomies to register appropriate connections
		 */
		if ( ! empty( $allowed_taxonomies && is_array( $allowed_taxonomies ) ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				$tax_object = get_taxonomy( $taxonomy );

				if ( $tax_object instanceof \WP_Taxonomy ) {

					/**
					 * Registers the RootQuery connection for each allowed taxonomy's TermObjects
					 */
					register_graphql_connection( self::get_connection_config( $tax_object ) );

					/**
					 * Registers the connections between each allowed PostObjectType and it's TermObjects
					 */
					if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
						foreach ( $allowed_post_types as $post_type ) {
							if ( in_array( $post_type, $tax_object->object_type, true ) ) {
								$post_type_object = get_post_type_object( $post_type );
								register_graphql_connection(
									self::get_connection_config(
										$tax_object,
										[
											'fromType' => $post_type_object->graphql_single_name,
											'toType'   => $tax_object->graphql_single_name,
											'fromFieldName' => $tax_object->graphql_plural_name,
											'resolve'  => function( Post $post, $args, AppContext $context, $info ) use ( $tax_object ) {

												$object_id = true === $post->isPreview && ! empty( $post->parentDatabaseId ) ? $post->parentDatabaseId : $post->ID;

												if ( empty( $object_id ) || ! absint( $object_id ) ) {
													return null;
												}

												$resolver = new TermObjectConnectionResolver( $post, $args, $context, $info, $tax_object->name );
												$resolver->set_query_arg( 'object_ids', absint( $object_id ) );

												return $resolver->get_connection();

											},
										]
									)
								);

							}
						}
					}

					if ( true === $tax_object->hierarchical ) {
						register_graphql_connection(
							self::get_connection_config(
								$tax_object,
								[
									'fromType'      => $tax_object->graphql_single_name,
									'fromFieldName' => 'children',
									'resolve'       => function( Term $term, $args, AppContext $context, $info ) {
										$resolver = new TermObjectConnectionResolver( $term, $args, $context, $info );
										$resolver->set_query_arg( 'parent', $term->term_id );

										return $resolver->get_connection();

									},
								]
							)
						);

						register_graphql_connection( [
							'fromType'           => $tax_object->graphql_single_name,
							'toType'             => $tax_object->graphql_single_name,
							'fromFieldName'      => 'parent',
							'connectionTypeName' => ucfirst( $tax_object->graphql_single_name ) . 'ToParent' . ucfirst( $tax_object->graphql_single_name ) . 'Connection',
							'oneToOne'           => true,
							'resolve'            => function( Term $term, $args, AppContext $context, $info ) use ( $tax_object ) {

								if ( ! isset( $term->parentDatabaseId ) || empty( $term->parentDatabaseId ) ) {
									return null;
								}

								$resolver = new TermObjectConnectionResolver( $term, $args, $context, $info, $tax_object->name );
								$resolver->set_query_arg( 'include', $term->parentDatabaseId );

								return $resolver->one_to_one()->get_connection();

							},
						] );

						register_graphql_connection( [
							'fromType'           => $tax_object->graphql_single_name,
							'toType'             => $tax_object->graphql_single_name,
							'fromFieldName'      => 'ancestors',
							'description'        => __( 'The ancestors of the node. Default ordered as lowest (closest to the child) to highest (closest to the root).', 'wp-graphql' ),
							'connectionTypeName' => ucfirst( $tax_object->graphql_single_name ) . 'ToAncestors' . ucfirst( $tax_object->graphql_single_name ) . 'Connection',
							'resolve'            => function( Term $term, $args, AppContext $context, $info ) use ( $tax_object ) {

								if ( ! $tax_object instanceof \WP_Taxonomy ) {
									return null;
								}

								$ancestor_ids = get_ancestors( absint( $term->term_id ), $term->taxonomyName, 'taxonomy' );

								if ( empty( $ancestor_ids ) ) {
									return null;
								}

								$resolver = new TermObjectConnectionResolver( $term, $args, $context, $info, $tax_object->name );
								$resolver->set_query_arg( 'include', $ancestor_ids );

								return $resolver->get_connection();

							},
						] );
					}
				}
			}
		}

		// Register a connection from each post type that
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $allowed_post_type ) {

				$post_type_object = get_post_type_object( $allowed_post_type );

				if ( empty( get_object_taxonomies( $allowed_post_type ) ) ) {
					return;
				}

				register_graphql_connection( [
					'fromType'       => $post_type_object->graphql_single_name,
					'toType'         => 'TermNode',
					'fromFieldName'  => 'terms',
					'queryClass'     => 'WP_Term_Query',
					'connectionArgs' => self::get_connection_args(
						[
							'taxonomies' => [
								'type'        => [ 'list_of' => 'TaxonomyEnum' ],
								'description' => __( 'The Taxonomy to filter terms by', 'wp-graphql' ),
							],
						]
					),
					'resolve'        => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
						$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ] );
						$terms      = wp_get_post_terms( $post->ID, $taxonomies, [ 'fields' => 'ids' ] );
						if ( empty( $terms ) || is_wp_error( $terms ) ) {
							return null;
						}
						$resolver = new TermObjectConnectionResolver( $post, $args, $context, $info, $taxonomies );
						$resolver->set_query_arg( 'include', $terms );

						return $resolver->get_connection();

					},
				] );
			}
		}

	}

	/**
	 * Given the Taxonomy Object and an array of args, this returns an array of args for use in
	 * registering a connection.
	 *
	 * @param \WP_Taxonomy $tax_object        The taxonomy object for the taxonomy having a
	 *                                        connection registered to it
	 * @param array        $args              The custom args to modify the connection registration
	 *
	 * @return array
	 */
	public static function get_connection_config( $tax_object, $args = [] ) {

		$defaults = [
			'fromType'       => 'RootQuery',
			'queryClass'     => 'WP_Term_Query',
			'toType'         => $tax_object->graphql_single_name,
			'fromFieldName'  => $tax_object->graphql_plural_name,
			'connectionArgs' => self::get_connection_args(),
			'resolve'        => function( $root, $args, $context, $info ) use ( $tax_object ) {
				return DataSource::resolve_term_objects_connection( $root, $args, $context, $info, $tax_object->name );
			},
		];

		return array_merge( $defaults, $args );
	}

	/**
	 * Given an optional array of args, this returns the args to be used in the connection
	 *
	 * @param array $args The args to modify the defaults
	 *
	 * @return array
	 */
	public static function get_connection_args( $args = [] ) {
		return array_merge(
			[
				'objectIds'           => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of object IDs. Results will be limited to terms associated with these objects.', 'wp-graphql' ),
				],
				'orderby'             => [
					'type'        => 'TermObjectsConnectionOrderbyEnum',
					'description' => __( 'Field(s) to order terms by. Defaults to \'name\'.', 'wp-graphql' ),
				],
				'hideEmpty'           => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to hide terms not assigned to any posts. Accepts true or false. Default false', 'wp-graphql' ),
				],
				'include'             => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term ids to include. Default empty array.', 'wp-graphql' ),
				],
				'exclude'             => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term ids to exclude. If $include is non-empty, $exclude is ignored. Default empty array.', 'wp-graphql' ),
				],
				'excludeTree'         => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term ids to exclude along with all of their descendant terms. If $include is non-empty, $exclude_tree is ignored. Default empty array.', 'wp-graphql' ),
				],
				'name'                => [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of names to return term(s) for. Default empty.', 'wp-graphql' ),
				],
				'slug'                => [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of slugs to return term(s) for. Default empty.', 'wp-graphql' ),
				],
				'termTaxonomId'       => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term taxonomy IDs, to match when querying terms.', 'wp-graphql' ),
				],
				'hierarchical'        => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to include terms that have non-empty descendants (even if $hide_empty is set to true). Default true.', 'wp-graphql' ),
				],
				'search'              => [
					'type'        => 'String',
					'description' => __( 'Search criteria to match terms. Will be SQL-formatted with wildcards before and after. Default empty.', 'wp-graphql' ),
				],
				'nameLike'            => [
					'type'        => 'String',
					'description' => __( 'Retrieve terms where the name is LIKE the input value. Default empty.', 'wp-graphql' ),
				],
				'descriptionLike'     => [
					'type'        => 'String',
					'description' => __( 'Retrieve terms where the description is LIKE the input value. Default empty.', 'wp-graphql' ),
				],
				'padCounts'           => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to pad the quantity of a term\'s children in the quantity of each term\'s "count" object variable. Default false.', 'wp-graphql' ),
				],
				'childOf'             => [
					'type'        => 'Int',
					'description' => __( 'Term ID to retrieve child terms of. If multiple taxonomies are passed, $child_of is ignored. Default 0.', 'wp-graphql' ),
				],
				'parent'              => [
					'type'        => 'Int',
					'description' => __( 'Parent term ID to retrieve direct-child terms of. Default empty.', 'wp-graphql' ),
				],
				'childless'           => [
					'type'        => 'Boolean',
					'description' => __( 'True to limit results to terms that have no children. This parameter has no effect on non-hierarchical taxonomies. Default false.', 'wp-graphql' ),
				],
				'cacheDomain'         => [
					'type'        => 'String',
					'description' => __( 'Unique cache key to be produced when this query is stored in an object cache. Default is \'core\'.', 'wp-graphql' ),
				],
				'updateTermMetaCache' => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to prime meta caches for matched terms. Default true.', 'wp-graphql' ),
				],
			],
			$args
		);
	}

}
