<?php

namespace WPGraphQL\Type\ObjectType;

use WP_Taxonomy;
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
	 * @param WP_Taxonomy $tax_object The taxonomy being registered
	 *
	 * @return void
	 */
	public static function register_taxonomy_object_type( WP_Taxonomy $tax_object ) {

		$interfaces = [ 'Node', 'TermNode', 'DatabaseIdentifier' ];

		if ( true === $tax_object->public ) {
			$interfaces[] = 'UniformResourceIdentifiable';
		}

		if ( $tax_object->hierarchical ) {
			$interfaces[] = 'HierarchicalTermNode';
		}

		if ( true === $tax_object->show_in_nav_menus ) {
			$interfaces[] = 'MenuItemLinkable';
		}

		$single_name = $tax_object->graphql_single_name;
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
						'resolve'           => function ( Term $term, $args, $context, $info ) {
							return absint( $term->term_id );
						},
					],
					'uri'               => [
						'resolve' => function ( $term, $args, $context, $info ) {
							$url = $term->link;
							if ( ! empty( $url ) ) {
								$parsed = wp_parse_url( $url );
								if ( isset( $parsed ) ) {
									$path  = isset( $parsed['path'] ) ? $parsed['path'] : '';
									$query = isset( $parsed['query'] ) ? ( '?' . $parsed['query'] ) : '';
									return trim( $path . $query );
								}
							}
							return '';
						},
					],
				],
			]
		);

	}

}
