<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class TaxonomyConnection {

	/**
	 * Register the TaxonomyConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'TaxonomyConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Taxonomy Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'TaxonomyConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Taxonomy' ] ] ],
					'description' => __( 'A list of connected Taxonomy Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'TaxonomyConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Taxonomy Node', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'Taxonomy' ],
				'description' => __( 'The connected Taxonomy Node', 'wp-graphql' ),
			],
		]);

	}

}
