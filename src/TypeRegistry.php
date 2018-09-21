<?php

namespace WPGraphQL;

use WPGraphQL\Connection\Comments;
use WPGraphQL\Connection\MenuItems;
use WPGraphQL\Connection\Menus;
use WPGraphQL\Connection\Users;
use WPGraphQL\Type\Avatar;
use WPGraphQL\Type\AvatarRatingEnum;
use WPGraphQL\Type\Comment;
use WPGraphQL\Type\Comment\Connection\CommentConnectionDefinition;
use WPGraphQL\Type\CommentAuthor;
use WPGraphQL\Type\CommentAuthorUnion;
use WPGraphQL\Type\CommentsConnectionOrderbyEnum;
use WPGraphQL\Type\DateInput;
use WPGraphQL\Type\EditLock;
use WPGraphQL\Type\MediaDetails;
use WPGraphQL\Type\MediaItemMeta;
use WPGraphQL\Type\MediaItemStatusEnum;
use WPGraphQL\Type\MediaSize;
use WPGraphQL\Type\MenuItem;
use WPGraphQL\Type\MenuItemObjectUnion;
use WPGraphQL\Type\MenuItemsConnectionWhereArgs;
use WPGraphQL\Type\MenuLocationEnum;
use WPGraphQL\Type\Menu;
use WPGraphQL\Type\MimeTypeEnum;
use WPGraphQL\Type\OrderEnum;
use WPGraphQL\Type\PageInfo;
use WPGraphQL\Type\Plugin;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionDefinition;
use WPGraphQL\Type\PostObjectFieldFormatEnum;
use WPGraphQL\Type\PostObjectsConnectionDateColumnEnum;
use WPGraphQL\Type\PostObjectsConnectionOrderbyEnum;
use WPGraphQL\Type\PostObjectsConnectionOrderbyInput;
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
use WPGraphQL\Type\TermObjectsConnectionOrderbyEnum;
use WPGraphQL\Type\TermObjectUnion;
use WPGraphQL\Type\Theme;
use WPGraphQL\Type\User;
use WPGraphQL\Type\UserRole;
use WPGraphQL\Type\UserRoleEnum;
use WPGraphQL\Type\UsersConnectionSearchColumnEnum;
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
		CommentsConnectionOrderbyEnum::register_type();
		CommentAuthor::register_type();
		DateInput::register_type();
		EditLock::register_type();
		MediaItemStatusEnum::register_type();
		MediaDetails::register_type();
		MediaItemMeta::register_type();
		MediaSize::register_type();
		MenuItem::register_type();
		MenuItemsConnectionWhereArgs::register_type();
		MenuLocationEnum::register_type();
		Menu::register_type();
		MimeTypeEnum::register_type();
		OrderEnum::register_type();
		PageInfo::register_type();
		Plugin::register_type();
		PostObjectsConnectionOrderbyInput::register_type();
		PostObjectsConnectionOrderbyEnum::register_type();
		PostObjectsConnectionDateColumnEnum::register_type();
		PostObjectFieldFormatEnum::register_type();
		PostStatusEnum::register_type();
		PostType::register_type();
		PostTypeLabelDetails::register_type();
		PostTypeEnum::register_type();
		RelationEnum::register_type();
		Settings::register_type();
		TermObjectsConnectionOrderbyEnum::register_type();
		Theme::register_type();
		Taxonomy::register_type();
		TaxonomyEnum::register_type();
		User::register_type();
		UsersConnectionSearchColumnEnum::register_type();
		UserRole::register_type();
		UserRoleEnum::register_type();
		RootMutation::register_type();
		RootQuery::register_type();

		/**
		 * Unions (need to be registered after other types)
		 */
		CommentAuthorUnion::register_type();
		MenuItemObjectUnion::register_type();
		PostObjectUnion::register_type();
		TermObjectUnion::register_type();

		if ( ! did_action( 'graphql_register_types' ) ) {
			do_action( 'graphql_register_types' );
		}

		Comments::register_connections();
		Menus::register_connections();
		MenuItems::register_connections();
		Users::register_connections();

		self::register_connections();

	}

	protected static function register_connections() {

		/**
		 * Connections: Need to be registered after other Types
		 *
		 * @todo: abstract to "register_graphql_connection"
		 */
		register_graphql_field( 'Comment', 'children', CommentConnectionDefinition::connection( 'Children' ) );


		self::register_user_connections();

	}

	protected static function register_user_connections() {

		/**
		 * Add a connection between user and comments.
		 *
		 * @todo: abstract to "register_graphql_connection"
		 */
		register_graphql_field( 'User', 'comments', CommentConnectionDefinition::connection( 'User' ) );

		/**
		 * Get the allowed_post_types so that we can create a connection from users
		 * to post_types
		 *
		 * @since 0.0.5
		 */
		$allowed_post_types = \WPGraphQL::$allowed_post_types;

		/**
		 * Add connection to each of the allowed post_types as users can have connections
		 * to any post_type.
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				// @todo: maybe look into narrowing this based on permissions?
				$post_type_object = get_post_type_object( $post_type );
				if ( ! empty( $post_type_object->graphql_plural_name ) ) {
					register_graphql_field( 'User', lcfirst( $post_type_object->graphql_plural_name ), PostObjectConnectionDefinition::connection( $post_type_object, 'User' ) );
				}
			}
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

		if ( is_string( $field_config['type'] ) ) {
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
				$field_config['args'][ $arg_name ] = self::prepare_field( $arg_name, $arg_config, $type_name );
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

		if ( ! array_key_exists( 'toType', $config ) ) {
			throw new \InvalidArgumentException( __( 'Connection config needs to have at least a toType defined', 'wp-graphql' ) );
		}

		if ( ! array_key_exists( 'fromFieldName', $config ) ) {
			throw new \InvalidArgumentException( __( 'Connection config needs to have at least a fromFieldName defined', 'wp-graphql' ) );
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
					// @todo: change to PageInfo when/if the Relay lib is deprecated
					'type'        => 'WPPageInfo',
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