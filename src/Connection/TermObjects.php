<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

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
	 * @access public
	 */
	public static function register_connections() {

		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();

		/**
		 * Loop through the allowed_taxonomies to register appropriate connections
		 */
		if ( ! empty( $allowed_taxonomies && is_array( $allowed_taxonomies ) ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				$tax_object = get_taxonomy( $taxonomy );

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
										'fromType'      => $post_type_object->graphql_single_name,
										'toType'        => $tax_object->graphql_single_name,
										'fromFieldName' => $tax_object->graphql_plural_name,
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
							]
						)
					);
				}
			}
		}

	}

	/**
	 * Given the Taxonomy Object and an array of args, this returns an array of args for use in
	 * registering a connection.
	 *
	 * @access public
	 * @param \WP_Taxonomy $tax_object        The taxonomy object for the taxonomy having a
	 *                                        connection registered to it
	 * @param array        $args              The custom args to modify the connection registration
	 *
	 * @return array
	 */
	public static function get_connection_config( $tax_object, $args = [] ) {

		$defaults = [
			'fromType'         => 'RootQuery',
			'queryClass'       => 'WP_Term_Query',
			'toType'           => $tax_object->graphql_single_name,
			'fromFieldName'    => $tax_object->graphql_plural_name,
			'connectionArgs'   => self::get_connection_args(),
			'connectionFields' => [
				'taxonomyInfo' => [
					'type'        => 'Taxonomy',
					'description' => __( 'Information about the type of content being queried', 'wp-graphql' ),
					'resolve'     => function ( $source, array $args, $context, $info ) use ( $tax_object ) {
						return DataSource::resolve_taxonomy( $tax_object->name );
					},
				],
			],
			'resolveNode'      => function( $id, $args, $context, $info ) {
				return DataSource::resolve_term_object( $id, $context );
			},
			'resolve'          => function ( $root, $args, $context, $info ) use ( $tax_object ) {
				return DataSource::resolve_term_objects_connection( $root, $args, $context, $info, $tax_object->name );
			},
		];

		return array_merge( $defaults, $args );
	}

	/**
	 * Given an optional array of args, this returns the args to be used in the connection
	 *
	 * @access public
	 * @param array $args The args to modify the defaults
	 *
	 * @return array
	 */
	public static function get_connection_args( $args = [] ) {
		return array_merge(
			[
				'objectIds'                       => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of object IDs. Results will be limited to terms associated with these objects.', 'wp-graphql' ),
				],
				'orderby'                         => [
					'type'        => 'TermObjectsConnectionOrderbyEnum',
					'description' => __( 'Field(s) to order terms by. Defaults to \'name\'.', 'wp-graphql' ),
				],
				'hideEmpty'                       => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to hide terms not assigned to any posts. Accepts true or false. Default false', 'wp-graphql' ),
				],
				'include'                         => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term ids to include. Default empty array.', 'wp-graphql' ),
				],
				'exclude'                         => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term ids to exclude. If $include is non-empty, $exclude is ignored. Default empty array.', 'wp-graphql' ),
				],
				'excludeTree'                     => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term ids to exclude along with all of their descendant terms. If $include is non-empty, $exclude_tree is ignored. Default empty array.', 'wp-graphql' ),
				],
				'name'                            => [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of names to return term(s) for. Default empty.', 'wp-graphql' ),
				],
				'slug'                            => [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of slugs to return term(s) for. Default empty.', 'wp-graphql' ),
				],
				'termTaxonomId'                   => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term taxonomy IDs, to match when querying terms.', 'wp-graphql' ),
				],
				'hierarchical'                    => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to include terms that have non-empty descendants (even if $hide_empty is set to true). Default true.', 'wp-graphql' ),
				],
				'search'                          => [
					'type'        => 'String',
					'description' => __( 'Search criteria to match terms. Will be SQL-formatted with wildcards before and after. Default empty.', 'wp-graphql' ),
				],
				'nameLike'                        => [
					'type'        => 'String',
					'description' => __( 'Retrieve terms where the name is LIKE the input value. Default empty.', 'wp-graphql' ),
				],
				'descriptionLike'                 => [
					'type'        => 'String',
					'description' => __( 'Retrieve terms where the description is LIKE the input value. Default empty.', 'wp-graphql' ),
				],
				'padCounts'                       => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to pad the quantity of a term\'s children in the quantity of each term\'s "count" object variable. Default false.', 'wp-graphql' ),
				],
				'childOf'                         => [
					'type'        => 'Int',
					'description' => __( 'Term ID to retrieve child terms of. If multiple taxonomies are passed, $child_of is ignored. Default 0.', 'wp-graphql' ),
				],
				'parent'                          => [
					'type'        => 'Int',
					'description' => __( 'Parent term ID to retrieve direct-child terms of. Default empty.', 'wp-graphql' ),
				],
				'childless'                       => [
					'type'        => 'Boolean',
					'description' => __( 'True to limit results to terms that have no children. This parameter has no effect on non-hierarchical taxonomies. Default false.', 'wp-graphql' ),
				],
				'cacheDomain'                     => [
					'type'        => 'String',
					'description' => __( 'Unique cache key to be produced when this query is stored in an object cache. Default is \'core\'.', 'wp-graphql' ),
				],
				'updateTermMetaCache'             => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to prime meta caches for matched terms. Default true.', 'wp-graphql' ),
				],
				'shouldOnlyIncludeConnectedItems' => [
					'type'        => 'Boolean',
					'description' => __( 'Default false. If true, only the items connected to the source item will be returned. If false, all items will be returned regardless of connection to the source', 'wp-graphql' ),
				],
				'shouldOutputInFlatList'          => [
					'type'        => 'Boolean',
					'description' => __( 'Default false. If true, the connection will be output in a flat list instead of the hierarchical list. So child terms will be output in the same level as the parent terms', 'wp-graphql' ),
				],
			],
			$args
		);
	}

}
