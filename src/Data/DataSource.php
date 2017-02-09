<?php
namespace WPGraphQL\Data;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use WPGraphQL\Types;

/**
 * Class DataSource
 *
 * Any complicated resolvers should be defined / centralized here.
 *
 * @package WPGraphQL\Data
 * @since 0.0.4
 */
class DataSource {

	// Placeholder
	protected static $node_definition;

	public static function get_user( $id ) {
		$user = new \WP_User( $id );
		if ( ! $user->exists() ) {
			return false;
		}
		return $user;
	}

	public static function get_users( $source, array $args, $context, ResolveInfo $info ) {

		$after  = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : null;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : null;
		$last   = ( ! empty( $args['last'] ) ) ? ArrayConnection::cursorToOffset( $args['last'] ) : null;
		$first  = ( ! empty( $args['first'] ) ) ? ArrayConnection::cursorToOffset( $args['first'] ) : null;

		// Default to order DESC
		$query_args['order'] = 'DESC';

		if ( ! empty( $after ) && ! empty( $before ) ) {
			throw new \Exception( __( '"First" and "Last" should not be used together.', 'wp-graphql' ) );
		}

		if ( ! empty( $last ) ) {
			$query_args['order'] = 'ASC';
		}

		if ( ! empty( $first ) ) {
			$query_args['order'] = 'DESC';
		}

		$query_args['number'] = ( ! empty( $first ) ) ? absint( $first ) : 10;
		$query_args['offset'] = ( ! empty( $args['after'] ) ) ? absint( $after ) : 0;

		$users_query = new \WP_User_Query( $query_args );
		$users_query->query();

		// Calculate the total length of the array
		// (total count of users)
		$meta['arrayLength'] = absint( $users_query->total_users );

		// Calculate the offset of the array
		// (the portion of the total query results that are being returned)
		$meta['sliceStart'] = $query_args['offset'];

		// Get the resulting array of users from the query
		$users = $users_query->get_results();
		$users = ArrayConnection::connectionFromArraySlice( $users, $args, $meta );

		// Return the connection
		return $users;

	}

	public static function resolve_post_objects( $post_type, $source, array $args, $context, ResolveInfo $info ) {
		$after  = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : null;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : null;
		$last   = ( ! empty( $args['last'] ) ) ? ArrayConnection::cursorToOffset( $args['last'] ) : null;
		$first  = ( ! empty( $args['first'] ) ) ? ArrayConnection::cursorToOffset( $args['first'] ) : null;

		if ( ! empty( $after ) && ! empty( $before ) ) {
			throw new \Exception( __( '"First" and "Last" should not be used together.', 'wp-graphql' ) );
		}

		if ( ! empty( $first ) ) {
			$query_args['order'] = 'DESC';
		}

		$query_args['post_type'] = $post_type;
		$query_args['posts_per_page'] = ( ! empty( $first ) ) ? absint( $first ) : 10;
		$query_args['page'] = ( ! empty( $args['after'] ) ) ? absint( ( $after/$query_args['posts_per_page'] ) ) : 0;

		$wp_query = new \WP_Query( $query_args );

		// Calculate the total length of the array
		// (total count of users)
		$meta['arrayLength'] = absint( $wp_query->found_posts );

		// Calculate the offset of the array
		// (the portion of the total query results that are being returned)
		$meta['sliceStart'] = $query_args['posts_per_page'];

		$posts = ArrayConnection::connectionFromArraySlice( $wp_query->posts, $args, $meta );

		// Return the connection
		return $posts;
	}

	public static function resolve_term_objects( $taxonomy, $source, array $args, $context, ResolveInfo $info ) {
		$after  = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : null;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : null;
		$last   = ( ! empty( $args['last'] ) ) ? ArrayConnection::cursorToOffset( $args['last'] ) : null;
		$first  = ( ! empty( $args['first'] ) ) ? ArrayConnection::cursorToOffset( $args['first'] ) : null;

		if ( ! empty( $after ) && ! empty( $before ) ) {
			throw new \Exception( __( '"First" and "Last" should not be used together.', 'wp-graphql' ) );
		}

		if ( ! empty( $last ) ) {
			$query_args['order'] = 'ASC';
		}

		if ( ! empty( $first ) ) {
			$query_args['order'] = 'DESC';
		}

		$query_args['taxonomy'] = $taxonomy;
		$query_args['number'] = ( ! empty( $first ) ) ? absint( $first ) : 10;
		$query_args['offset'] = ( ! empty( $args['after'] ) ) ? absint( $after ) : 0;
		$query = new \WP_Term_Query( $query_args );

		// Calculate the total length of the array
		// (total count of users)
		$meta['arrayLength'] = 100;

		// Calculate the offset of the array
		// (the portion of the total query results that are being returned)
		$meta['sliceStart'] = $query_args['offset'];

		$terms = ArrayConnection::connectionFromArraySlice( $query->terms, $args, $meta );


		// Return the connection
		return $terms;
	}

	public static function post_object( $id ) {
		$post_object = \WP_Post::get_instance( $id );
		if ( empty( $post_object ) ) {
			return false;
		}
		return $post_object;
	}

	public static function term_object( $id ) {
		$term_object = new \WP_Term( $id );
		if ( empty( $term_object ) ) {
			return false;
		}
		return $term_object;
	}

	/**
	 * We get the node interface and field from the relay library.
	 *
	 * The first method is the way we resolve an ID to its object. The second is the
	 * way we resolve an object that implements node to its type.
	 */
	public static function get_node_definition() {
		if ( null === self::$node_definition ) {

			$node_definition       = Relay::nodeDefinitions(
				// The ID fetcher definition
				function( $global_id ) {
					$id_components = Relay::fromGlobalId( $global_id );
					$allowed_post_types = \WPGraphQL::$allowed_post_types;
					$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;
					if ( in_array( $id_components['type'], $allowed_post_types, true ) ) {
						return \WP_Post::get_instance( $id_components['id'] );
					} elseif ( in_array( $id_components['type'], $allowed_taxonomies, true ) ) {
						$term = get_term_by( 'id', $id_components['id'], $id_components['type'] );
						return $term;
					} elseif ( 'user' === $id_components['type'] ) {
						$user = new \WP_User( $id_components['id'] );
						return $user;
					} else {
						throw new \Exception( sprintf( __( 'No node could be found with global ID: %' , 'wp-graphql' ), $global_id ) );
					}
				},
				// Type resolver
				function( $object ) {
					$types = new Types();
					if ( $object instanceof \WP_Post ) {
						return Types::post_object( $object->post_type );
					} elseif ( $object instanceof \WP_Term ) {
						return Types::term_object( $object->taxonomy );
					} elseif ( $object instanceof \WP_User ) {
						return Types::user();
					}
					return null;
				}
			);

			self::$node_definition = $node_definition;
		}

		return self::$node_definition;
	}

}