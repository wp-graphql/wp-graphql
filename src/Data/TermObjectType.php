<?php

namespace WPGraphQL\Data;

use WP_Taxonomy;
use WPGraphQL\Model\Term;

/**
 * Class TermObjectType
 *
 * @package WPGraphQL\Data
 */
class TermObjectType {

	/**
	 * Registers a post_type type to the schema as either a GraphQL object, interface, or union.
	 *
	 * @param WP_Taxonomy $tax_object Taxonomy.
	 *
	 * @return void
	 */
	public static function register_term_object_types( WP_Taxonomy $tax_object ) {
		$single_name = $tax_object->graphql_single_name;

		$config = [
			/* translators: post object singular name w/ description */
			'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
			'interfaces'  => static::get_term_object_interfaces( $tax_object ),
			'fields'      => static::get_term_object_fields( $tax_object ),
			'model'       => Term::class,
		];

		// Register as GraphQL objects.
		if ( 'object' === $tax_object->graphql_kind ) {
			register_graphql_object_type( $single_name, $config );
			return;
		}

		/**
		 * Register as GraphQL interfaces or unions.
		 *
		 * It's assumed that the types used in `resolveType` have already been registered to the schema.
		 */
		$config['resolveType'] = $tax_object->graphql_resolve_type;

		if ( 'interface' === $tax_object->graphql_kind ) {
			register_graphql_interface_type( $single_name, $config );
		} elseif ( 'union' === $tax_object->graphql_kind ) {
			register_graphql_union_type( $single_name, $config );
		}
	}

	/**
	 * Gets all the interfaces for the given Taxonomy.
	 *
	 * @param WP_Taxonomy $tax_object Taxonomy.
	 *
	 * @return array
	 */
	protected static function get_term_object_interfaces( WP_Taxonomy $tax_object ) {
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

		// Merge with interfaces set in register_taxonomy.
		if ( ! empty( $tax_object->graphql_interfaces ) ) {
			$interfaces = array_merge( $interfaces, $tax_object->graphql_interfaces );
		}

		return $interfaces;
	}

	/**
	 * Registers common Taxonomy fields on schema type corresponding to provided Taxonomy object.
	 *
	 * @param WP_Taxonomy $tax_object Taxonomy.
	 *
	 * @return array
	 */
	protected static function get_term_object_fields( WP_Taxonomy $tax_object ) {
		$single_name = $tax_object->graphql_single_name;
		$fields      = [
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
		];

		return $fields;
	}
}
