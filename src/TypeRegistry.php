<?php

namespace WPGraphQL;

use WPGraphQL\Connection\Comments;
use WPGraphQL\Connection\Connections;
use WPGraphQL\Connection\MenuItems;
use WPGraphQL\Connection\Menus;
use WPGraphQL\Connection\Plugins;
use WPGraphQL\Connection\PostObjects;
use WPGraphQL\Connection\Themes;
use WPGraphQL\Connection\Users;
use WPGraphQL\Type\Avatar;
use WPGraphQL\Type\AvatarRatingEnum;
use WPGraphQL\Type\Comment;
use WPGraphQL\Type\CommentAuthor;
use WPGraphQL\Type\CommentAuthorUnion;
use WPGraphQL\Type\CommentsOrderbyEnum;
use WPGraphQL\Type\CommentsOrderEnum;
use WPGraphQL\Type\DateInput;
use WPGraphQL\Type\EditLock;
use WPGraphQL\Type\MediaItemStatusEnum;
use WPGraphQL\Type\MenuItem;
use WPGraphQL\Type\MenuItemObjectUnion;
use WPGraphQL\Type\MenuLocationEnum;
use WPGraphQL\Type\Menu;
use WPGraphQL\Type\MimeTypeEnum;
use WPGraphQL\Type\PageInfo;
use WPGraphQL\Type\Plugin;
use WPGraphQL\Type\PostObject;
use WPGraphQL\Type\PostObjectFieldFormatEnum;
use WPGraphQL\Type\PostObjectOrderEnum;
use WPGraphQL\Type\PostObjectsConnectionDateQuery;
use WPGraphQL\Type\PostObjectsDateColumnEnum;
use WPGraphQL\Type\PostObjectsOrderby;
use WPGraphQL\Type\PostObjectsOrderbyFieldEnum;
use WPGraphQL\Type\PostObjectUnion;
use WPGraphQL\Type\PostStatusEnum;
use WPGraphQL\Type\PostType;
use WPGraphQL\Type\PostTypeEnum;
use WPGraphQL\Type\PostTypeLabelDetails;
use WPGraphQL\Type\RelationEnum;
use WPGraphQL\Type\RootMutation;
use WPGraphQL\Type\RootQuery;
use WPGraphQL\Type\Settings;
use WPGraphQL\Type\Taxonomy;
use WPGraphQL\Type\TaxonomyEnum;
use WPGraphQL\Type\TaxonomyOrderbyEnum;
use WPGraphQL\Type\TermObject;
use WPGraphQL\Type\TermObjectUnion;
use WPGraphQL\Type\Theme;
use WPGraphQL\Type\User;
use WPGraphQL\Type\UserRole;
use WPGraphQL\Type\UserRolesEnum;
use WPGraphQL\Type\UserSearchColumnEnum;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Type\WPUnionType;

class TypeRegistry {

	protected static $types;

	public static function init() {

		register_graphql_type( 'Bool', Types::boolean() );
		register_graphql_type( 'Boolean', Types::boolean() );
		register_graphql_type( 'Float', Types::float() );
		register_graphql_type( 'Number', Types::float() );
		register_graphql_type( 'Id', Types::id() );
		register_graphql_type( 'Int', Types::int() );
		register_graphql_type( 'Integer', Types::int() );
		register_graphql_type( 'String', Types::string() );

		Avatar::register_type();
		AvatarRatingEnum::register_type();
		Comment::register_type();
		CommentAuthor::register_type();
		CommentsOrderbyEnum::register_type();
		CommentsOrderEnum::register_type();
		DateInput::register_type();
		EditLock::register_type();
		MediaItemStatusEnum::register_type();
		MenuItem::register_type();
		MenuLocationEnum::register_type();
		Menu::register_type();
		MimeTypeEnum::register_type();
		PageInfo::register_type();
		Plugin::register_type();
		PostObjectsConnectionDateQuery::register_type();
		PostObjectsOrderby::register_type();
		PostObjectsOrderbyFieldEnum::register_type();
		PostObjectsDateColumnEnum::register_type();
		PostObjectOrderEnum::register_type();
		PostObjectFieldFormatEnum::register_type();
		PostStatusEnum::register_type();
		PostType::register_type();
		PostTypeLabelDetails::register_type();
		PostTypeEnum::register_type();
		RelationEnum::register_type();
		Settings::register_type();
		Theme::register_type();
		Taxonomy::register_type();
		TaxonomyEnum::register_type();
		TaxonomyOrderbyEnum::register_type();
		User::register_type();
		UserRole::register_type();
		UserRolesEnum::register_type();
		UserSearchColumnEnum::register_type();

		$allowed_post_types = \WPGraphQL::get_allowed_post_types();

		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				$post_type_object = get_post_type_object( $post_type );
				if ( ! empty( $post_type_object->graphql_single_name) ) {
					PostObject::register_type( $post_type_object );
				}
			}
		}


		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				$tax_object = get_taxonomy( $taxonomy );
				TermObject::register_type( $tax_object );
			}
		}

		RootMutation::register_type();
		RootQuery::register_type();

		if ( ! did_action( 'graphql_register_types' ) ) {
			do_action( 'graphql_register_types' );
		}

		/**
		 * Unions: need to be registered after other types as they are a merger of Types
		 */
		CommentAuthorUnion::register_type();
		MenuItemObjectUnion::register_type();
		PostObjectUnion::register_type();
		TermObjectUnion::register_type();


		if ( ! did_action( 'graphql_register_unions' ) ) {
			do_action( 'graphql_register_unions' );
		}

		/**
		 * Connections get registered after Types
		 */
		Connections::register_connections();

		/**
		 * RootQueryToCommentConnection
		 */
		// Comments::register_connection();
		// Comments::register_connection([ 'fromType' => 'Comment', 'fromFieldName' => 'children' ]);
		// Comments::register_connection([ 'fromType' => 'User', 'fromFieldName' => 'comments' ]);
		Menus::register_connection();
		MenuItems::register_connection();
		MenuItems::register_connection([ 'fromType' => 'Menu', 'fromFieldName' => 'menuItems' ]);
		Plugins::register_connection();
		Themes::register_connection();
		Users::register_connection();

		if ( ! did_action( 'graphql_register_connections' ) ) {
			do_action( 'graphql_register_connections' );
		}

	}


	protected static function format_key( $key ) {
		return strtolower( $key );
	}

	public static function register_fields( $type_name, $fields ) {
		if ( isset( $type_name ) && is_string( $type_name ) && ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $config ) {
				if ( isset( $field_name ) && is_string( $field_name ) && ! empty( $config ) && is_array( $config ) ) {
					self::register_field( $type_name, $field_name, $config );
				}
			}
		}
	}

	public static function register_field( $type_name, $field_name, $config ) {

		add_filter( 'graphql_' . $type_name . '_fields', function ( $fields ) use ( $type_name, $field_name, $config ) {

			if ( isset ( $fields[ $field_name ] ) ) {
				return $fields;
			}

			/**
			 * If the field returns a properly prepared field, add it the the field registry
			 */
			$field = self::prepare_field( $field_name, $config, $type_name );

			if ( ! empty( $field ) ) {
				$fields[ $field_name ] = self::prepare_field( $field_name, $config, $type_name );
			}

			return $fields;

		} );

	}

	public static function register_type( $type_name, $config ) {
		if ( ! isset( self::$types[ $type_name ] ) ) {
			$prepared_type = self::prepare_type( $type_name, $config );
			if ( ! empty( $prepared_type ) ) {
				self::$types[ self::format_key( $type_name ) ] = $prepared_type;
			}
		}
	}

	protected static function prepare_type( $type_name, $config ) {

		if ( is_array( $config ) ) {
			$kind           = isset( $config['kind'] ) ? $config['kind'] : null;
			$config['name'] = $type_name;

			if ( ! empty( $config['fields'] ) && is_array( $config['fields'] ) ) {
				$config['fields'] = function () use ( $config, $type_name ) {
					$prepared_fields = self::prepare_fields( $config['fields'], $type_name );
					$prepared_fields = WPObjectType::prepare_fields( $prepared_fields, $type_name );

					return $prepared_fields;
				};
			}

			switch ( $kind ) {
				case 'enum':
					$prepared_type = new WPEnumType( $config );
					break;
				case 'input':
					$prepared_type = new WPInputObjectType( $config );
					break;
				case 'union':
					$prepared_type = new WPUnionType( $config );
					break;
				case 'object':
				default:
					$prepared_type = new WPObjectType( $config );
			}
		} else {
			$prepared_type = $config;
		}

		return isset( $prepared_type ) ? $prepared_type : null;
	}

	protected static function prepare_fields( $fields, $type_name ) {
		$prepared_fields = [];
		$prepared_field  = null;
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $field_config ) {
				if ( isset( $field_config['type'] ) ) {
					$prepared_field = self::prepare_field( $field_name, $field_config, $type_name );
					if ( ! empty( $prepared_field ) ) {
						$prepared_fields[ self::format_key( $field_name ) ] = $prepared_field;
					} else {
						unset( $prepared_fields[ self::format_key( $field_name ) ] );
					}

				}
			}
		}

		return $prepared_fields;
	}

	protected static function prepare_field( $field_name, $field_config, $type_name ) {

		if ( ! isset( $field_config['name'] ) ) {
			$field_config['name'] = $field_name;
		}

		if ( isset( $field_config['type'] ) && is_string( $field_config['type'] ) ) {
			$type = TypeRegistry::get_type( $field_config['type'] );
			if ( ! empty( $type ) ) {
				$field_config['type'] = $type;
			} else {
				return null;
			}
		}

		if ( is_array( $field_config['type'] ) ) {
			if ( isset( $field_config['type']['non_null'] ) && is_string( $field_config['type']['non_null'] ) ) {
				$non_null_type = TypeRegistry::get_type( $field_config['type']['non_null'] );
				if ( ! empty( $non_null_type ) ) {
					$field_config['type'] = Types::non_null( $non_null_type );
				}
			} else if ( isset( $field_config['type']['list_of'] ) && is_string( $field_config['type']['list_of'] ) ) {
				$list_of_type = TypeRegistry::get_type( $field_config['type']['list_of'] );
				if ( ! empty( $list_of_type ) ) {
					$field_config['type'] = Types::list_of( $list_of_type );
				}
			}
		}

		if ( ! empty( $field_config['args'] ) && is_array( $field_config['args'] ) ) {
			foreach ( $field_config['args'] as $arg_name => $arg_config ) {
				$field_config['args'][ $arg_name ] = function() use ( $arg_name, $arg_config, $type_name ) {
					return self::prepare_field( $arg_name, $arg_config, $type_name );
				};
			}
		} else {
			unset( $field_config['args'] );
		}

		return $field_config;
	}

	/**
	 * @param $type_name
	 *
	 * @return mixed|WPObjectType|WPUnionType|WPInputObjectType|WPEnumType
	 */
	public static function get_type( $type_name ) {
		return ( null !== self::$types[ self::format_key( $type_name ) ] ) ? ( self::$types[ self::format_key( $type_name ) ] ) : null;
	}

	public static function get_types() {
		return ! empty( self::$types ) ? self::$types : [];
	}

	protected static function get_connection_name( $from_type, $to_type ) {
		return $from_type . 'To' . $to_type . 'Connection';
	}

	public static function register_connection( $config ) {

		if ( ! array_key_exists( 'fromType', $config ) ) {
			throw new \InvalidArgumentException( __( 'Connection config needs to have at least a fromType defined', 'wp-graphql' ) );
		}

		$from_type          = $config['fromType'];
		$to_type            = $config['toType'];
		$from_field_name    = $config['fromFieldName'];
		$connection_fields  = ! empty( $config['connectionFields'] ) && is_array( $config['connectionFields'] ) ? $config['connectionFields'] : [];
		$connection_args    = ! empty( $config['connectionArgs'] ) && is_array( $config['connectionArgs'] ) ? $config['connectionArgs'] : [];
		$edge_fields        = ! empty( $config['edgeFields'] ) && is_array( $config['edgeFields'] ) ? $config['edgeFields'] : [];
		$resolve_node       = array_key_exists( 'resolveNode', $config ) ? $config['resolveNode'] : null;
		$resolve_cursor     = array_key_exists( 'resolveCursor', $config ) ? $config['resolveCursor'] : null;
		$resolve_connection = array_key_exists( 'resolve', $config ) ? $config['resolve'] : null;
		$connection_name    = self::get_connection_name( $from_type, $to_type );
		$where_args = [];

		/**
		 * If there are any $connectionArgs,
		 * register their inputType and configure them as $where_args to be added to the connection
		 * field as arguments
		 */
		if ( ! empty( $connection_args ) ) {
			register_graphql_input_type( $from_type . 'To' . $to_type . 'ConnectionWhereArgs', [
				'description' => __( 'Arguments for filtering the connection', 'wp-graphql' ),
				'fields' => $connection_args,
			]);

			$where_args = [
				'where' => [
					'description' => __( 'Arguments for filtering the connection', 'wp-graphql' ),
					'type' => $from_type . 'To' . $to_type . 'ConnectionWhereArgs',
				],
			];

		}

		register_graphql_type( $connection_name . 'Edge', [
			'description' => __( 'An edge in a connection', 'wp-graphql' ),
			'fields'      => array_merge( [
				'cursor' => [
					'type'        => 'String',
					'description' => __( 'A cursor for use in pagination', 'wp-graphql' ),
					'resolve'     => $resolve_cursor
				],
				'node'   => [
					'type'        => $to_type,
					'description' => __( 'The item at the end of the edge', 'wp-graphql' ),
					'resolve'     => $resolve_node
				],
			], $edge_fields ),
		] );

		register_graphql_type( $connection_name, [
			// Translators: the placeholders are the name of the Types the connection is between.
			'description' => __( sprintf( 'Connection between the %1$s type and the %2s type', $from_type, $to_type ), 'wp-graphql' ),
			'fields'      => array_merge( [
				'pageInfo' => [
					'type'        => 'PageInfo',
					'description' => __( 'Information about pagination in a connection.', 'wp-graphql' ),
				],
				'edges'    => [
					'type'        => [
						'list_of' => $connection_name . 'Edge',
					],
					'description' => __( sprintf( 'Edges for the %1$s connection', $connection_name ), 'wp-graphql' ),
				],
			], $connection_fields ),
		] );

		register_graphql_field( $from_type, $from_field_name, [
			'type'        => $connection_name,
			'args'        => array_merge( [
				'first'  => [
					'type'        => 'Int',
					'description' => __( 'The number of items to return after the referenced "after" cursor', 'wp-graphql' ),
				],
				'last'   => [
					'type'         => 'Int',
					'description ' => __( 'The number of items to return before the referenced "before" cursor', 'wp-graphql' ),
				],
				'after'  => [
					'type'        => 'String',
					'description' => __( 'Cursor used along with the "first" argument to reference where in the dataset to get data', 'wp-graphql' ),
				],
				'before' => [
					'type'        => 'String',
					'description' => __( 'Cursor used along with the "last" argument to reference where in the dataset to get data', 'wp-graphql' ),
				],
			], $where_args ),
			'description' => __( sprintf( 'Connection between the %1$s type and the %2s type', $from_type, $to_type ), 'wp-graphql' ),
			'resolve'     => $resolve_connection
		] );

	}

}