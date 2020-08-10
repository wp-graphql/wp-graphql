<?php

namespace WPGraphQL\Type\Object;

use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Term;

/**
 * Class TermObject
 *
 * @package WPGraphQL\Type\Object
 */
class TermObject {

	/**
	 * Register the Type for each kind of Taxonomy
	 *
	 * @param $taxonomy_object
	 */
	public static function register_taxonomy_object_type( $taxonomy_object ) {

		$interfaces = [ 'Node', 'TermNode', 'UniformResourceIdentifiable', 'DatabaseIdentifier' ];

		if ( $taxonomy_object->hierarchical ) {
			$interfaces[] = 'HierarchicalTermNode';
		}

		if ( true === $taxonomy_object->show_in_nav_menus ) {
			$interfaces[] = 'MenuItemLinkable';
		}

		$single_name = $taxonomy_object->graphql_single_name;
		register_graphql_object_type(
			$single_name,
			[
				'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
				'interfaces'  => $interfaces,
				'fields'      => [
					$single_name . 'Id' => [
						'type'              => 'Int',
						'deprecationReason' => __( 'Deprecated in favor of databaseId', 'wp-graphql' ),
						'description'       => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve'           => function( Term $term, $args, $context, $info ) {
							return absint( $term->term_id );
						},
					],
					'uri'               => [
						'resolve' => function( $term, $args, $context, $info ) {
							return ! empty( $term->link ) ? str_ireplace( home_url(), '', $term->link ) : '';
						},
					],
				],
			]
		);

	}

}
