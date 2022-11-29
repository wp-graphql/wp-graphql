<?php

namespace WPGraphQL\Type\Connection;

use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Utils\Utils;

/**
 * Class TermObjects
 *
 * This class organizes the registration of connections to TermObjects
 *
 * @package WPGraphQL\Type\Connection
 */
class TermObjects {

	/**
	 * Register connections to TermObjects
	 *
	 * @return void
	 */
	public static function register_connections() {

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
				'resolve'        => function ( $source, $args, $context, $info ) {
					$taxonomies = isset( $args['where']['taxonomies'] ) && is_array( $args['where']['taxonomies'] ) ? $args['where']['taxonomies'] : \WPGraphQL::get_allowed_taxonomies();
					$resolver   = new TermObjectConnectionResolver( $source, $args, $context, $info, array_values( $taxonomies ) );
					$connection = $resolver->get_connection();

					return $connection;
				},
			]
		);

		/** @var \WP_Taxonomy[] $allowed_taxonomies */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		/**
		 * Loop through the allowed_taxonomies to register appropriate connections
		 */
		foreach ( $allowed_taxonomies as $tax_object ) {
			if ( ! $tax_object->graphql_register_root_connection ) {
				continue;
			}

			$root_query_from_field_name = Utils::format_field_name( $tax_object->graphql_plural_name );

			// Prevent field name conflicts with the singular TermObject type.
			if ( $tax_object->graphql_single_name === $tax_object->graphql_plural_name ) {
				$root_query_from_field_name = 'all' . ucfirst( $tax_object->graphql_single_name );
			}

			/**
			 * Registers the RootQuery connection for each allowed taxonomy's TermObjects
			 */
			register_graphql_connection(
				self::get_connection_config(
					$tax_object,
					[
						'fromFieldName' => $root_query_from_field_name,
					]
				)
			);
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
			'resolve'        => function ( $root, $args, $context, $info ) use ( $tax_object ) {
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
				'childless'           => [
					'type'        => 'Boolean',
					'description' => __( 'True to limit results to terms that have no children. This parameter has no effect on non-hierarchical taxonomies. Default false.', 'wp-graphql' ),
				],
				'childOf'             => [
					'type'        => 'Int',
					'description' => __( 'Term ID to retrieve child terms of. If multiple taxonomies are passed, $child_of is ignored. Default 0.', 'wp-graphql' ),
				],
				'cacheDomain'         => [
					'type'        => 'String',
					'description' => __( 'Unique cache key to be produced when this query is stored in an object cache. Default is \'core\'.', 'wp-graphql' ),
				],
				'descriptionLike'     => [
					'type'        => 'String',
					'description' => __( 'Retrieve terms where the description is LIKE the input value. Default empty.', 'wp-graphql' ),
				],
				'exclude'             => [ // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
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
				'hideEmpty'           => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to hide terms not assigned to any posts. Accepts true or false. Default false', 'wp-graphql' ),
				],
				'hierarchical'        => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to include terms that have non-empty descendants (even if $hide_empty is set to true). Default true.', 'wp-graphql' ),
				],
				'include'             => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term ids to include. Default empty array.', 'wp-graphql' ),
				],
				'name'                => [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of names to return term(s) for. Default empty.', 'wp-graphql' ),
				],
				'nameLike'            => [
					'type'        => 'String',
					'description' => __( 'Retrieve terms where the name is LIKE the input value. Default empty.', 'wp-graphql' ),
				],
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
				'order'               => [
					'type'        => 'OrderEnum',
					'description' => __( 'Direction the connection should be ordered in', 'wp-graphql' ),
				],
				'padCounts'           => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to pad the quantity of a term\'s children in the quantity of each term\'s "count" object variable. Default false.', 'wp-graphql' ),
				],
				'parent'              => [
					'type'        => 'Int',
					'description' => __( 'Parent term ID to retrieve direct-child terms of. Default empty.', 'wp-graphql' ),
				],
				'search'              => [
					'type'        => 'String',
					'description' => __( 'Search criteria to match terms. Will be SQL-formatted with wildcards before and after. Default empty.', 'wp-graphql' ),
				],
				'slug'                => [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of slugs to return term(s) for. Default empty.', 'wp-graphql' ),
				],
				'termTaxonomId'       => [
					'type'              => [
						'list_of' => 'ID',
					],
					'description'       => __( 'Array of term taxonomy IDs, to match when querying terms.', 'wp-graphql' ),
					'deprecationReason' => __( 'Use `termTaxonomyId` instead', 'wp-graphql' ),
				],
				'termTaxonomyId'      => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of term taxonomy IDs, to match when querying terms.', 'wp-graphql' ),
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
