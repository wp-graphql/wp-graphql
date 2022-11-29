<?php

namespace WPGraphQL\Type\Connection;

use WPGraphQL\Data\Connection\TaxonomyConnectionResolver;
use WPGraphQL\Model\PostType;

class Taxonomies {

	/**
	 * Registers connections to the Taxonomy type
	 *
	 * @return void
	 */
	public static function register_connections() {

		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'Taxonomy',
				'fromFieldName' => 'taxonomies',
				'resolve'       => function ( $source, $args, $context, $info ) {
					$resolver = new TaxonomyConnectionResolver( $source, $args, $context, $info );
					return $resolver->get_connection();
				},
			]
		);

		register_graphql_connection(
			[
				'fromType'      => 'ContentType',
				'toType'        => 'Taxonomy',
				'fromFieldName' => 'connectedTaxonomies',
				'resolve'       => function ( PostType $source, $args, $context, $info ) {
					if ( empty( $source->taxonomies ) ) {
						return null;
					}
					$resolver = new TaxonomyConnectionResolver( $source, $args, $context, $info );
					$resolver->setQueryArg( 'in', $source->taxonomies );
					return $resolver->get_connection();
				},
			]
		);
	}
}
