<?php

namespace WPGraphQL\Registry\Utils;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WP_Taxonomy;
use WPGraphQL;
use WPGraphQL\AppContext;
use WPGraphQL\Connection\PostObjects;
use WPGraphQL\Connection\TermObjects;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TaxonomyConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Model\Term;

/**
 * Class TermObjectType
 *
 * @package WPGraphQL\Data
 */
class TermObject {

	/**
	 * Registers a post_type type to the schema as either a GraphQL object, interface, or union.
	 *
	 * @param WP_Taxonomy $tax_object Taxonomy.
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_types( WP_Taxonomy $tax_object ) {
		$single_name = $tax_object->graphql_single_name;

		$config = [
			/* translators: post object singular name w/ description */
			'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
			'connections' => static::get_connections( $tax_object ),
			'interfaces'  => static::get_interfaces( $tax_object ),
			'fields'      => static::get_fields( $tax_object ),
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
	 * Gets all the connections for the given post type.
	 *
	 * @param WP_Taxonomy $tax_object
	 *
	 * @return array
	 */
	protected static function get_connections( WP_Taxonomy $tax_object ) {
		$connections = [];

		// Taxonomy.
		// @todo connection move to TermNode (breaking).
		$connections['taxonomy'] = [
			'toType'   => 'Taxonomy',
			'oneToOne' => true,
			'resolve'  => function ( Term $source, $args, $context, $info ) {
				if ( empty( $source->taxonomyName ) ) {
					return null;
				}
				$resolver = new TaxonomyConnectionResolver( $source, $args, $context, $info );
				$resolver->set_query_arg( 'name', $source->taxonomyName );
				return $resolver->one_to_one()->get_connection();
			},
		];

		if ( true === $tax_object->hierarchical ) {
			// Children.
			$connections['children'] = [
				'toType'         => $tax_object->graphql_single_name,
				'description'    => sprintf(
					__( 'Connection between the %1$s type and its children %2$s.', 'wp-graphql' ),
					$tax_object->graphql_single_name,
					$tax_object->graphql_plural_name
				),
				'connectionArgs' => TermObjects::get_connection_args(),
				'queryClass'     => 'WP_Term_Query',
				'resolve'        => function ( Term $term, $args, AppContext $context, $info ) {
					$resolver = new TermObjectConnectionResolver( $term, $args, $context, $info );
					$resolver->set_query_arg( 'parent', $term->term_id );

					return $resolver->get_connection();

				},
			];

			// Parent.
			$connections['parent'] = [
				'toType'             => $tax_object->graphql_single_name,
				'description'        => sprintf(
					__( 'Connection between the %1$s type and its parent %1$s.', 'wp-graphql' ),
					$tax_object->graphql_single_name
				),
				'connectionTypeName' => ucfirst( $tax_object->graphql_single_name ) . 'ToParent' . ucfirst( $tax_object->graphql_single_name ) . 'Connection',
				'oneToOne'           => true,
				'resolve'            => function ( Term $term, $args, AppContext $context, $info ) use ( $tax_object ) {
					if ( ! isset( $term->parentDatabaseId ) || empty( $term->parentDatabaseId ) ) {
						return null;
					}

					$resolver = new TermObjectConnectionResolver( $term, $args, $context, $info, $tax_object->name );
					$resolver->set_query_arg( 'include', $term->parentDatabaseId );

					return $resolver->one_to_one()->get_connection();
				},
			];

			// Ancestors.
			$connections['ancestors'] = [
				'toType'             => $tax_object->graphql_single_name,
				'description'        => __( 'The ancestors of the node. Default ordered as lowest (closest to the child) to highest (closest to the root).', 'wp-graphql' ),
				'connectionTypeName' => ucfirst( $tax_object->graphql_single_name ) . 'ToAncestors' . ucfirst( $tax_object->graphql_single_name ) . 'Connection',
				'resolve'            => function ( Term $term, $args, AppContext $context, $info ) use ( $tax_object ) {
					if ( ! $tax_object instanceof WP_Taxonomy ) {
						return null;
					}

					$ancestor_ids = get_ancestors( absint( $term->term_id ), $term->taxonomyName, 'taxonomy' );

					if ( empty( $ancestor_ids ) ) {
						return null;
					}

					$resolver = new TermObjectConnectionResolver( $term, $args, $context, $info, $tax_object->name );
					$resolver->set_query_arg( 'include', $ancestor_ids );

					return $resolver->get_connection();
				},
			];
		}

		// Used to ensure contentNodes connection doesn't get registered multiple times.
		$already_registered = false;
		$allowed_post_types = WPGraphQL::get_allowed_post_types( 'objects' );

		foreach ( $allowed_post_types as $post_type_object ) {

			if ( ! in_array( $tax_object->name, get_object_taxonomies( $post_type_object->name ), true ) ) {
				continue;
			}

			// ContentNodes.
			if ( ! $already_registered ) {

				$connections['contentNodes'] = PostObjects::get_connection_config( $tax_object, [
					'toType'  => 'ContentNode',
					'resolve' => function ( Term $term, $args, $context, $info ) {
						$resolver = new PostObjectConnectionResolver( $term, $args, $context, $info, 'any' );
						$resolver->set_query_arg( 'tax_query', [
							[
								'taxonomy'         => $term->taxonomyName,
								'terms'            => [ $term->term_id ],
								'field'            => 'term_id',
								'include_children' => false,
							],
						] );

						return $resolver->get_connection();
					},
				] );

				// We won't need to register this connection again.
				$already_registered = true;
			}

			// PostObjects.
			$connections[ $post_type_object->graphql_plural_name ] = PostObjects::get_connection_config( $post_type_object, [
				'toType'     => $post_type_object->graphql_single_name,
				'queryClass' => 'WP_Query',
				'resolve'    => function ( Term $term, $args, AppContext $context, ResolveInfo $info ) use ( $post_type_object ) {
					$resolver = new PostObjectConnectionResolver( $term, $args, $context, $info, $post_type_object->name );
					$resolver->set_query_arg( 'tax_query', [
						[
							'taxonomy'         => $term->taxonomyName,
							'terms'            => [ $term->term_id ],
							'field'            => 'term_id',
							'include_children' => false,
						],
					] );

					return $resolver->get_connection();
				},
			]);
		}

		// Merge with connections set in register_taxonomy.
		if ( ! empty( $tax_object->graphql_connections ) ) {
			$connections = array_merge( $connections, $tax_object->graphql_connections );
		}

		// Remove excluded connections.
		foreach ( $tax_object->graphql_exclude_connections as $connection_name ) {
			unset( $connections[ lcfirst( $connection_name ) ] );
		}

		return $connections;
	}
	/**
	 * Gets all the interfaces for the given Taxonomy.
	 *
	 * @param WP_Taxonomy $tax_object Taxonomy.
	 *
	 * @return array
	 */
	protected static function get_interfaces( WP_Taxonomy $tax_object ) {
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

		// Remove excluded interfaces.
		if ( ! empty( $tax_object->graphql_exclude_interfaces ) ) {
			$interfaces = array_diff( $interfaces, $tax_object->graphql_exclude_interfaces );
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
	protected static function get_fields( WP_Taxonomy $tax_object ) {
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
