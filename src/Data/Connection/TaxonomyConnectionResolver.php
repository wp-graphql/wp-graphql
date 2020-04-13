<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Taxonomy;
use WPGraphQL\Model\Term;

/**
 * Class TaxonomyConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class TaxonomyConnectionResolver {

	/**
	 * Creates the connection for taxonomies
	 *
	 * @param mixed       $source  The query results
	 * @param array       $args    The query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @since  0.8.0
	 * @return array
	 * @throws \Exception Throws Exception.
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {

		$query_args = [];

		if ( $source instanceof Term ) {
			$query_args['name'] = $source->taxonomyName;
		}
		$query_args['show_in_graphql'] = true;

		$taxonomies = get_taxonomies( $query_args );

		$tax_array = [];
		foreach ( $taxonomies as $taxonomy ) {

			$tax_object = get_taxonomy( $taxonomy );
			$model      = ! empty( $tax_object ) ? new Taxonomy( $tax_object ) : null;

			if ( 'private' !== $model->get_visibility() ) {
				$tax_array[] = $model;
			}
		}
		$connection = Relay::connectionFromArray( $tax_array, $args );

		$nodes = [];
		if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
			foreach ( $connection['edges'] as $edge ) {
				$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
			}
		}
		$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

		return ! empty( $tax_array ) ? $connection : null;

	}

}
