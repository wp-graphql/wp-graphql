<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Connections;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class RootQueryType
 *
 * The RootQueryType is the primary entry for Queries in the GraphQL Schema.
 *
 * @package WPGraphQL\Type
 * @since 0.0.4
 */
class RootQueryType extends ObjectType {

	/**
	 * RootQueryType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		/**
		 * Run an action when the RootQuery is being generated
		 * @since 0.0.5
		 */
		do_action( 'graphql_root_query' );

		/**
		 * Setup data
		 * @since 0.0.5
		 */
		$allowed_post_types = \WPGraphQL::$allowed_post_types;
		$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;
		$node_definition    = DataSource::get_node_definition();

		/**
		 * Creates the node root query field which can be used
		 * to query any node from the system using the globally unique
		 * ID
		 *
		 * @since 0.0.5
		 */
		$fields['node'] = $node_definition['nodeField'];

		/**
		 * Creates the comment root query field
		 * @since 0.0.5
		 */
		$fields['comment']  = self::comment();
		$fields['comments'] = Connections::comments_connection();

		/**
		 * Creates the plugin root query field
		 * @since 0.0.5
		 */
		$fields['plugin']  = self::plugin();
		$fields['plugins'] = Connections::plugins_connection();

		/**
		 * Creates the theme root query field
		 * @since 0.0.5
		 */
		$fields['theme'] = self::theme();

		/**
		 * Creates the theme root query field to query a collection
		 * of themes
		 * @since 0.0.5
		 */
		$fields['themes'] = Connections::themes_connection();

		/**
		 * Creates the user root query field
		 * @since 0.0.5
		 */
		$fields['user'] = self::user();

		/**
		 * Creates the users root query field to query a collection
		 * of users
		 * @since 0.0.5
		 */
		$fields['users'] = Connections::users_connection();

		/**
		 * Creates the viewer root query field
		 * @since 0.0.5
		 */
		$fields['viewer'] = self::viewer();

		/**
		 * Creates the root fields for post objects (of any post_type)
		 *
		 * This registers root fields (single and plural) for any post_type that has been registered as an
		 * allowed post_type.
		 *
		 * @see \WPGraphQL::$allowed_post_types
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				/**
				 * Get the post_type object to pass down to the schema
				 * @since 0.0.5
				 */
				$post_type_object = get_post_type_object( $post_type );

				/**
				 * Root field for single posts (of the specified post_type)
				 * @since 0.0.5
				 */
				$fields[ $post_type_object->graphql_single_name ] = [
					'type'        => Types::post_object( $post_type ),
					'description' => sprintf( __( 'A % object', 'wp-graphql' ), $post_type_object->graphql_single_name ),
					'args'        => [
						'id' => Types::non_null( Types::id() ),
					],
					'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) use ( $post_type ) {
						$id_components = Relay::fromGlobalId( $args['id'] );

						return DataSource::resolve_post_object( $id_components['id'], $post_type );
					},
				];

				/**
				 * Root field for collections of posts (of the specified post_type)
				 * @since 0.0.5
				 */
				$fields[ $post_type_object->graphql_plural_name ] = Connections::post_objects_connection( $post_type_object );
			}
		}

		/**
		 * Creates the root fields for terms of each taxonomy
		 *
		 * This registers root fields (single and plural) for terms of any taxonomy that has been registered as an
		 * allowed taxonomy.
		 *
		 * @see \WPGraphQL::$allowed_taxonomies
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {

				/**
				 * Get the taxonomy object
				 * @since 0.0.5
				 */
				$taxonomy_object = get_taxonomy( $taxonomy );

				/**
				 * Root field for single terms (of the specified taxonomy)
				 * @since 0.0.5
				 */
				$fields[ $taxonomy_object->graphql_single_name ] = [
					'type'        => Types::term_object( $taxonomy ),
					'description' => sprintf( __( 'A % object', 'wp-graphql' ), $taxonomy_object->graphql_single_name ),
					'args'        => [
						'id' => Types::non_null( Types::id() ),
					],
					'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) use ( $taxonomy ) {
						$id_components = Relay::fromGlobalId( $args['id'] );

						return DataSource::resolve_term_object( $id_components['id'], $taxonomy );
					},
				];

				/**
				 * Root field for collections of terms (of the specified taxonomy)
				 * @since 0.0.5
				 */
				$fields[ $taxonomy_object->graphql_plural_name ] = Connections::term_objects_connection( $taxonomy_object );
			}
		}

		/**
		 * Pass the root queries through a filter.
		 * This allows fields to be added or removed.
		 *
		 * NOTE: Use this filter with care. Before removing existing fields seriously consider deprecating the field, as
		 * that will allow the field to still be used and not break systems that rely on it, but just not be present
		 * in Schema documentation, etc.
		 *
		 * If the behavior of a field needs to be changed, depending on the change, it might be better to consider adding
		 * a new field with the new behavior instead of overriding an existing field. This will allow existing fields
		 * to behave as expected, but will allow introduction of new fields with different behavior at any point.
		 *
		 * @since 0.0.5
		 */
		$fields = apply_filters( 'graphql_root_queries', $fields );

		/**
		 * Sort the fields alphabetically by keys
		 * (this makes the schema documentation much nicer to browse)
		 */
		ksort( $fields );

		/**
		 * Configure the RootQuery
		 * @since 0.0.5
		 */
		$config = [
			'name'         => 'rootQuery',
			'fields'       => $fields,
			'resolveField' => function( $value, $args, $context, ResolveInfo $info ) {
				if ( method_exists( $this, $info->fieldName ) ) {
					return $this->{$info->fieldName}( $value, $args, $context, $info );
				} else {
					return $value->{$info->fieldName};
				}
			},
		];

		/**
		 * Pass the config to the parent construct
		 * @since 0.0.5
		 */
		parent::__construct( $config );

	}

	/**
	 * comment
	 * This sets up the comment entry point for the root query
	 * @return array
	 * @since 0.0.5
	 */
	public static function comment() {
		return [
			'type'        => Types::comment(),
			'description' => __( 'Returns a Comment', 'wp-graphql' ),
			'args'        => [
				'id' => Types::non_null( Types::id() ),
			],
			'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_comment( $id_components['id'] );
			},
		];
	}

	/**
	 * plugin
	 * This sets up the plugin entry point for the root query
	 * @return array
	 * @since 0.0.5
	 */
	public static function plugin() {
		return [
			'type'        => Types::plugin(),
			'description' => __( 'A WordPress plugin', 'wp-graphql' ),
			'args'        => [
				'id' => Types::non_null( Types::id() ),
			],
			'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_plugin( $id_components['id'] );
			},
		];
	}

	/**
	 * post_type
	 * This sets up the post_type entry point for the root query
	 * @return array
	 * @since 0.0.5
	 */
	public static function post_type() {
		return [
			'type'        => Types::post_type(),
			'description' => __( 'A WordPress Post Type', 'wp-graphql' ),
			'args'        => [
				'id' => Types::non_null( Types::id() ),
			],
			'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_post_type( $id_components['id'] );
			},
		];
	}

	/**
	 * theme
	 * This sets up the theme entry point for the root query
	 * @return array
	 * @since 0.0.5
	 */
	public static function theme() {
		return [
			'type'        => Types::theme(),
			'description' => __( 'A Theme object', 'wp-graphql' ),
			'args'        => [
				'id' => Types::non_null( Types::id() ),
			],
			'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_theme( $id_components['id'] );
			},
		];
	}

	/**
	 * taxonomy
	 * This sets up the taxonomy entry point for the root query
	 * @return array
	 * @since 0.0.5
	 */
	public static function taxonomy() {
		return [
			'type'        => Types::taxonomy(),
			'description' => __( 'A taxonomy object', 'wp-graphql' ),
			'args'        => [
				'id' => Types::non_null( Types::id() ),
			],
			'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_taxonomy( $id_components['id'] );
			},
		];
	}

	/**
	 * user
	 * This sets up the user entry point for the root query
	 * @return array
	 * @since 0.0.5
	 */
	public static function user() {
		return [
			'type'        => Types::user(),
			'description' => __( 'Returns a user', 'wp-graphql' ),
			'args'        => [
				'id' => Types::non_null( Types::id() ),
			],
			'resolve'     => function( $source, array $args, $context, ResolveInfo $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_user( $id_components['id'] );
			},
		];
	}

	/**
	 * viewer
	 * This sets up the viewer entry point for the root query
	 * @return array
	 * @since 0.0.5
	 */
	public static function viewer() {
		return [
			'type'        => Types::user(),
			'description' => __( 'Returns the current user', 'wp-graphql' ),
			'resolve'     => function( $source, array $args, AppContext $context, ResolveInfo $info ) {

				if ( ! ( $context->viewer instanceof \WP_User ) ) {
					throw new \Exception( __( 'The current viewe is invalid', 'wp-graphql' ) );
				}

				return false !== $context->viewer->ID ? DataSource::resolve_user( $context->viewer->ID ) : null;
			},
		];
	}
}
