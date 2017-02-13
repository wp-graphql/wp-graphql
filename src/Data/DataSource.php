<?php
namespace WPGraphQL\Data;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use WPGraphQL\Data\Resolvers\PostObjectsConnectionResolver;
use WPGraphQL\Data\Resolvers\TermObjectsConnectionResolver;
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

	public static function resolve_comments( $source, array $args, $context, ResolveInfo $info ) {

		$after  = ( ! empty( $args['after'] ) ) ? ArrayConnection::cursorToOffset( $args['after'] ) : null;
		$before = ( ! empty( $args['before'] ) ) ? ArrayConnection::cursorToOffset( $args['before'] ) : null;
		$last   = ( ! empty( $args['last'] ) ) ? ArrayConnection::cursorToOffset( $args['last'] ) : null;
		$first  = ( ! empty( $args['first'] ) ) ? ArrayConnection::cursorToOffset( $args['first'] ) : null;

		$query_args['number'] = ( ! empty( $first ) ) ? absint( $first ) : 10;
		$query_args['offset'] = ( ! empty( $args['after'] ) ) ? absint( $after ) : 0;

		if ( ! empty( $last ) ) {
			$query_args['order'] = 'ASC';
		}

		if ( ! empty( $first ) ) {
			$query_args['order'] = 'DESC';
		}

		/**
		 * If the query source is a WP_Post object,
		 * adjust the query args to only query for comments connected
		 * to that post_object
		 *
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Post && absint( $source->ID ) ) {
			$query_args['post_id'] = absint( $source->ID );
		}

		/**
		 * If the query source is a WP_User object,
		 * adjust the query args to only query for the comments connected
		 * to that user
		 */
		if ( $source instanceof  \WP_User && absint( $source->ID ) ) {
			$query_args['user_id'] = $source->ID;
		}

		/**
		 * If the query source is a WP_Comment object,
		 * adjust the query args to only query for comments that have
		 * the source ID as their parent
		 *
		 * @since 0.0.5
		 */
		if ( $source instanceof \WP_Comment && absint( $source->comment_ID ) ) {
			$query_args['parent'] = absint( $source->comment_ID );
		}

		$comments_query = new \WP_Comment_Query( $query_args );
		$comments = $comments_query->get_comments();

		$comments = ArrayConnection::connectionFromArray( $comments, $args );

		return $comments;
	}

	public static function resolve_post_objects_connection( $post_type, $source, array $args, $context, ResolveInfo $info ) {
		return PostObjectsConnectionResolver::resolve( $post_type, $source, $args, $context, $info );
	}

	public static function resolve_term_objects_connection( $taxonomy, $source, array $args, $context, ResolveInfo $info ) {
		return TermObjectsConnectionResolver::resolve( $taxonomy, $source, $args, $context, $info );
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