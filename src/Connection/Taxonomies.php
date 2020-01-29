<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\TaxonomyConnectionResolver;
use WPGraphQL\Data\DataSource;

class Taxonomies {
	public static function register_connections() {

		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'Taxonomy',
				'fromFieldName' => 'taxonomies',
				'resolveNode'   => function( $taxonomy ) {
					return DataSource::resolve_taxonomy( $taxonomy );
				},
				'resolve'       => function( $source, $args, $context, $info ) {
					$connection = new TaxonomyConnectionResolver( $source, $args, $context, $info );

					return $connection->get_connection();
				},
			]
		);

		$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ], 'OBJECT' );

		if ( is_array( $taxonomies ) && ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				register_graphql_connection(
					[
						'fromType'      => $taxonomy->graphql_single_name,
						'toType'        => 'Taxonomy',
						'fromFieldName' => 'taxonomy',
						'oneToOne'      => true,
						'resolveNode'   => function( $taxonomy ) {
							return DataSource::resolve_taxonomy( $taxonomy );
						},
						'resolve'       => function( $source, $args, $context, $info ) {
							$connection = new TaxonomyConnectionResolver( $source, $args, $context, $info );
							return $connection->get_connection();
						},
					]
				);
			}
		}

	}
}
