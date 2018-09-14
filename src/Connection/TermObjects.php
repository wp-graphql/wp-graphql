<?php
namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

class TermObjects {
	public static function register_connection( $taxonomy_object, $config = [] ) {

		$default = [
			'fromType' => 'RootQuery',
			'toType' => $taxonomy_object->graphql_single_name,
			'fromFieldName' => lcfirst( $taxonomy_object->graphql_plural_name ),
			'queryClass' => 'WP_Term_Query',
			'connectionArgs' => [
				'objectIds'           => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of object IDs. Results will be limited to terms associated with these objects.', 'wp-graphql' ),
				],
				'orderby'             => [
					'type'        => 'TaxonomyOrderbyEnum',
					'description' => __( 'Field(s) to order terms by. Defaults to \'name\'.', 'wp-graphql' ),
				],
				'hideEmpty'           => [
					'type'        => 'Boolean',
					'description' => __( 'Whether to hide terms not assigned to any posts. Accepts true or false. Default true', 'wp-graphql' ),
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
			'connectionFields' => [
				'taxonomyInfo' => [
					'type'        => 'Taxonomy',
					'description' => __( 'Information about the type of content being queried', 'wp-graphql' ),
					'resolve'     => function( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $taxonomy_object ) {
						return $taxonomy_object;
					},
				],
				'nodes'        => [
					'type'        => [
						'list_of' => $taxonomy_object->graphql_single_name,
					],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
					'resolve'     => function( $source, $args, $context, $info ) {
						return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
					},
				],
			],
		];

		$config = array_merge( $default, $config );

		register_graphql_connection( $config );

	}
}