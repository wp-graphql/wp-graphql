<?php

namespace WPGraphQL;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Connection\Comments;
use WPGraphQL\Connection\MenuItems;
use WPGraphQL\Connection\Menus;
use WPGraphQL\Connection\Plugins;
use WPGraphQL\Connection\PostObjects;
use WPGraphQL\Connection\TermObjects;
use WPGraphQL\Connection\Themes;
use WPGraphQL\Connection\UserRoles;
use WPGraphQL\Connection\Users;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Mutation\CommentCreate;
use WPGraphQL\Mutation\CommentDelete;
use WPGraphQL\Mutation\CommentRestore;
use WPGraphQL\Mutation\CommentUpdate;
use WPGraphQL\Mutation\MediaItemCreate;
use WPGraphQL\Mutation\MediaItemDelete;
use WPGraphQL\Mutation\MediaItemUpdate;
use WPGraphQL\Mutation\PostObjectCreate;
use WPGraphQL\Mutation\PostObjectDelete;
use WPGraphQL\Mutation\PostObjectUpdate;
use WPGraphQL\Mutation\ResetUserPassword;
use WPGraphQL\Mutation\SendPasswordResetEmail;
use WPGraphQL\Mutation\TermObjectCreate;
use WPGraphQL\Mutation\TermObjectDelete;
use WPGraphQL\Mutation\TermObjectUpdate;
use WPGraphQL\Mutation\UpdateSettings;
use WPGraphQL\Mutation\UserCreate;
use WPGraphQL\Mutation\UserDelete;
use WPGraphQL\Mutation\UserRegister;
use WPGraphQL\Mutation\UserUpdate;
use function WPGraphQL\Type\register_post_object_types;
use function WPGraphQL\Type\register_settings_group;
use function WPGraphQL\Type\register_taxonomy_object_type;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Type\WPUnionType;

/**
 * Class TypeRegistry
 *
 * This serves as the central TypeRegistry for WPGraphQL.
 *
 * Types can be added to the registry via `register_graphql_type`, then can be referenced throughout
 * via TypeRegistry::get_type( 'typename' );
 *
 * @package WPGraphQL
 */
class TypeRegistry {

	/**
	 * Stores all registered Types
	 *
	 * @var array
	 */
	protected static $types;

	/**
	 * Initialize the TypeRegistry by registering core Types that should be available
	 */
	public static function init() {

		/**
		 * Register core Scalars
		 */
		register_graphql_type( 'Bool', Types::boolean() );
		register_graphql_type( 'Boolean', Types::boolean() );
		register_graphql_type( 'Float', Types::float() );
		register_graphql_type( 'Number', Types::float() );
		register_graphql_type( 'Id', Types::id() );
		register_graphql_type( 'Int', Types::int() );
		register_graphql_type( 'Integer', Types::int() );
		register_graphql_type( 'String', Types::string() );

		/**
		 * Register core WPGraphQL Types
		 */
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/Avatar.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/AvatarRatingEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/Comment.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/CommentsConnectionOrderbyEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/CommentAuthor.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Input/DateInput.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Input/DateQueryInput.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/EditLock.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/MediaItemStatusEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/MediaItemSizeEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/MediaDetails.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/MediaItemMeta.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/MediaSize.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/MenuItem.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Input/MenuItemsConnectionWhereArgs.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/MenuLocationEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/Menu.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/MimeTypeEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/OrderEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/PageInfo.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/Plugin.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Input/PostObjectsConnectionOrderbyInput.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/PostObjectsConnectionOrderbyEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/PostObjectsConnectionDateColumnEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/PostObjectFieldFormatEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/PostStatusEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/PostType.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/PostTypeLabelDetails.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/RootMutation.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/RootQuery.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/PostTypeEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/RelationEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/Settings.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/TermObjectsConnectionOrderbyEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/TimezoneEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/Theme.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/Taxonomy.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/TaxonomyEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/User.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/UsersConnectionSearchColumnEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/UserRole.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Enum/UserRoleEnum.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/SettingGroup.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/PostObject.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Object/TermObject.php';

		/**
		 * Create the root query fields for any setting type in
		 * the $allowed_setting_types array.
		 */
		$allowed_setting_types = DataSource::get_allowed_settings_by_group();
		if ( ! empty( $allowed_setting_types ) && is_array( $allowed_setting_types ) ) {
			foreach ( $allowed_setting_types as $group => $setting_type ) {

				$group_name = lcfirst( str_replace( '_', '', ucwords( $group, '_' ) ) );
				register_settings_group( $group_name );

				register_graphql_field(
					'RootQuery',
					$group_name . 'Settings',
					[
						'type'    => ucfirst( $group_name ) . 'Settings',
						'resolve' => function () use ( $setting_type ) {
							return $setting_type;
						},
					]
				);
			}
		}

		/**
		 * Register PostObject types based on post_types configured to show_in_graphql
		 */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				$post_type_object = get_post_type_object( $post_type );
				register_post_object_types( $post_type_object );

				/**
				 * Mutations for attachments are handled differently
				 * because they require different inputs
				 */
				if ( 'attachment' !== $post_type_object->name ) {

					/**
					 * Revisions are created behind the scenes as a side effect of post updates,
					 * they aren't created manually.
					 */
					if ( 'revision' !== $post_type_object->name ) {
						PostObjectCreate::register_mutation( $post_type_object );
						PostObjectUpdate::register_mutation( $post_type_object );
					}

					PostObjectDelete::register_mutation( $post_type_object );

				}
			}
		}

		/**
		 * Register TermObject types based on taxonomies configured to show_in_graphql
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				$taxonomy_object = get_taxonomy( $taxonomy );
				register_taxonomy_object_type( $taxonomy_object );
				TermObjectCreate::register_mutation( $taxonomy_object );
				TermObjectUpdate::register_mutation( $taxonomy_object );
				TermObjectDelete::register_mutation( $taxonomy_object );
			}
		}

		/**
		 * Register all Union Types
		 * Unions need to be registered after other types as they reference other Types
		 */
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Union/CommentAuthorUnion.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Union/MenuItemObjectUnion.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Union/PostObjectUnion.php';
		require_once WPGRAPHQL_PLUGIN_DIR . 'src/Type/Union/TermObjectUnion.php';

		/**
		 * Register core connections
		 */
		Comments::register_connections();
		Menus::register_connections();
		MenuItems::register_connections();
		Plugins::register_connections();
		PostObjects::register_connections();
		TermObjects::register_connections();
		Themes::register_connections();
		Users::register_connections();
		UserRoles::register_connections();

		/**
		 * Register core mutations
		 */
		CommentCreate::register_mutation();
		CommentDelete::register_mutation();
		CommentRestore::register_mutation();
		CommentUpdate::register_mutation();
		MediaItemCreate::register_mutation();
		MediaItemDelete::register_mutation();
		MediaItemUpdate::register_mutation();
		ResetUserPassword::register_mutation();
		SendPasswordResetEmail::register_mutation();
		UserCreate::register_mutation();
		UserDelete::register_mutation();
		UserUpdate::register_mutation();
		UserRegister::register_mutation();
		UpdateSettings::register_mutation();

		/**
		 * Hook to register connections
		 */
		if ( ! did_action( 'graphql_register_types' ) ) {
			do_action( 'graphql_register_types' );
		}

	}

	/**
	 * Formats the array key to a more friendly format
	 *
	 * @param string $key Name of the array key to format
	 *
	 * @return string
	 * @access protected
	 */
	protected static function format_key( $key ) {
		return strtolower( $key );
	}

	/**
	 * Wrapper for the register_field method to register multiple fields at once
	 *
	 * @param string $type_name Name of the type in the Type Registry to add the fields to
	 * @param array  $fields    Fields to register
	 *
	 * @access public
	 * @return void
	 */
	public static function register_fields( $type_name, $fields ) {
		if ( isset( $type_name ) && is_string( $type_name ) && ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $config ) {
				if ( isset( $field_name ) && is_string( $field_name ) && ! empty( $config ) && is_array( $config ) ) {
					self::register_field( $type_name, $field_name, $config );
				}
			}
		}
	}

	/**
	 * Add a field to a Type in the Type Registry
	 *
	 * @param string $type_name  Name of the type in the Type Registry to add the fields to
	 * @param string $field_name Name of the field to add to the type
	 * @param array  $config     Info about the field to register to the type
	 *
	 * @access public
	 * @return void
	 */
	public static function register_field( $type_name, $field_name, $config ) {

		add_filter(
			'graphql_' . $type_name . '_fields',
			function ( $fields ) use ( $type_name, $field_name, $config ) {

				if ( isset( $fields[ $field_name ] ) ) {
					if ( true === GRAPHQL_DEBUG ) {
						throw new InvariantViolation( sprintf( __( 'You cannot register duplicate fields on the same Type. The field \'%1$s\' already exists on the type \'%2$s\'. Make sure to give the field a unique name.' ), $field_name, $type_name ) );
					}

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

			},
			10,
			1
		);

	}

	/**
	 * Remove a field from a type
	 *
	 * @param string $type_name  Name of the Type the field is registered to
	 * @param string $field_name Name of the field you would like to remove from the type
	 *
	 * @access public
	 * @return void
	 */
	public static function deregister_field( $type_name, $field_name ) {

		add_filter(
			'graphql_' . $type_name . '_fields',
			function ( $fields ) use ( $type_name, $field_name ) {

				if ( isset( $fields[ $field_name ] ) ) {
					unset( $fields[ $field_name ] );
				} else {
					if ( true === GRAPHQL_DEBUG ) {
						throw new InvariantViolation( sprintf( __( 'The field \'%1$s\' does not exist on the type \'%2$s\' and cannot be deregistered', 'wp-graphql' ), $field_name, $type_name ) );
					}
				}

				return $fields;

			}
		);

	}

	/**
	 * Add a new Type to the registry
	 *
	 * @param string $type_name Name of the type being registered
	 * @param array  $config    Info about the type being registered
	 *
	 * @access public
	 * @return void
	 */
	public static function register_type( $type_name, $config ) {
		if ( isset( self::$types[ self::format_key( $type_name ) ] ) ) {
			return;
		}
		$prepared_type = self::prepare_type( $type_name, $config );
		if ( ! empty( $prepared_type ) ) {
			self::$types[ self::format_key( $type_name ) ] = $prepared_type;
		}
	}

	/**
	 * Build a new type object for the Type you are trying to register. Returns an instance of the
	 * appropriate class given the Type
	 *
	 * @param string $type_name Name of the type being registered
	 * @param array  $config    Info about the type being registered
	 *
	 * @return null|WPEnumType|WPInputObjectType|WPObjectType|WPUnionType
	 */
	protected static function prepare_type( $type_name, $config ) {

		if ( is_array( $config ) ) {
			$kind           = isset( $config['kind'] ) ? $config['kind'] : null;
			$config['name'] = ucfirst( $type_name );

			if ( ! empty( $config['fields'] ) && is_array( $config['fields'] ) ) {
				$config['fields'] = function () use ( $config, $kind, $type_name ) {
					$prepared_fields = self::prepare_fields( $config['fields'], $type_name );
					$prepared_fields = WPObjectType::prepare_fields( $prepared_fields, $type_name );

					/**
					 * If the object defines input fields, additionally apply a
					 * centralized filter for all input fields.
					 */
					if ( 'input' === $kind ) {
						$prepared_fields = WPInputObjectType::prepare_fields( $prepared_fields, $type_name, $config );
					}

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

	/**
	 * Wrapper for prepare_field to prepare multiple fields for registration at once
	 *
	 * @param array  $fields    Array of fields and their settings to register on a Type
	 * @param string $type_name Name of the Type to register the fields to
	 *
	 * @access protected
	 * @return array
	 * @throws \Exception
	 */
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

	/**
	 * Prepare the field to be registered on the type
	 *
	 * @param string $field_name   Friendly name of the field
	 * @param array  $field_config Config data about the field to prepare
	 * @param string $type_name    Name of the type to prepare the field for
	 *
	 * @access protected
	 * @return array|null
	 * @throws \Exception
	 */
	protected static function prepare_field( $field_name, $field_config, $type_name ) {

		/**
		 * If the Field is a Type definition and not
		 */
		if ( ! is_array( $field_config ) ) {
			return $field_config;
		}

		if ( ! isset( $field_config['name'] ) ) {
			$field_config['name'] = lcfirst( $field_name );
		}

		if ( ! isset( $field_config['type'] ) ) {
			throw new InvariantViolation( sprintf( __( 'The registered field \'%s\' does not have a Type defined. Make sure to define a type for all fields.', 'wp-graphql' ), $field_name ) );
		}

		if ( is_string( $field_config['type'] ) ) {
			$type = self::get_type( $field_config['type'] );
			if ( ! empty( $type ) ) {
				$field_config['type'] = $type;
			} else {
				return null;
			}
		}

		$field_config['type'] = self::setup_type_modifiers( $field_config['type'] );

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
	 * @param mixed string|array $type
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function setup_type_modifiers( $type ) {

		if ( is_array( $type ) ) {
			if ( isset( $type['non_null'] ) ) {
				if ( is_array( $type['non_null'] ) ) {
					$non_null_type = self::setup_type_modifiers( $type['non_null'] );
				} elseif ( is_string( $type['non_null'] ) ) {
					$non_null_type = self::get_type( $type['non_null'] );
				}
				if ( empty( $non_null_type ) ) {
					throw new \Exception( sprintf( __( 'The non_null type %s is an invalid or non-existent type', 'wp-graphql' ), (string) $type['non_null'] ) );
				} else {
					$type = Types::non_null( $non_null_type );
				}
			} elseif ( isset( $type['list_of'] ) ) {
				if ( is_array( $type['list_of'] ) ) {
					$list_of_type = self::setup_type_modifiers( $type['list_of'] );
				} elseif ( is_string( $type['list_of'] ) ) {
					$list_of_type = self::get_type( $type['list_of'] );
				}

				if ( empty( $list_of_type ) ) {
					throw new \Exception( sprintf( __( 'The list_of type %s is an invalid or non-existent type', 'wp-graphql' ), (string) $type['list_of'] ) );
				} else {
					$type = Types::list_of( $list_of_type );
				}
			}
		}

		return $type;

	}

	/**
	 * Return one specific Type given a name
	 *
	 * @param string $type_name Name of the type to b e returned
	 *
	 * @access public
	 * @return mixed|WPObjectType|WPUnionType|WPInputObjectType|WPEnumType
	 */
	public static function get_type( $type_name ) {
		return isset( self::$types[ self::format_key( $type_name ) ] ) ? ( self::$types[ self::format_key( $type_name ) ] ) : null;
	}

	/**
	 * Return array of all Types
	 *
	 * @access public
	 * @return array
	 */
	public static function get_types() {
		return ! empty( self::$types ) ? self::$types : [];
	}

	/**
	 * Utility method that formats the connection name given the name of the from Type and the to
	 * Type
	 *
	 * @param string $from_type Name of the Type the connection is coming from
	 * @param string $to_type   Name of the Type the connection is going to
	 *
	 * @access protected
	 * @return string
	 */
	protected static function get_connection_name( $from_type, $to_type ) {
		return ucfirst( $from_type ) . 'To' . ucfirst( $to_type ) . 'Connection';
	}

	/**
	 * Method to register a new connection in the Type registry
	 *
	 * @param array $config The info about the connection being registered
	 *
	 * @access public
	 * @return void
	 * @throws \InvalidArgumentException
	 */
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
		$resolve_node       = array_key_exists( 'resolveNode', $config ) && is_callable( $config['resolve'] ) ? $config['resolveNode'] : null;
		$resolve_cursor     = array_key_exists( 'resolveCursor', $config ) && is_callable( $config['resolve'] ) ? $config['resolveCursor'] : null;
		$resolve_connection = array_key_exists( 'resolve', $config ) && is_callable( $config['resolve'] ) ? $config['resolve'] : function () {
			return null;
		};
		$connection_name    = ! empty( $config['connectionTypeName'] ) ? $config['connectionTypeName'] : self::get_connection_name( $from_type, $to_type );
		$where_args         = [];

		/**
		 * If there are any $connectionArgs,
		 * register their inputType and configure them as $where_args to be added to the connection
		 * field as arguments
		 */
		if ( ! empty( $connection_args ) ) {
			register_graphql_input_type(
				$connection_name . 'WhereArgs',
				[
					// Translators: Placeholder is the name of the connection
					'description' => sprintf( __( 'Arguments for filtering the %s connection', 'wp-graphql' ), $connection_name ),
					'fields'      => $connection_args,
					'queryClass'  => ! empty( $config['queryClass'] ) ? $config['queryClass'] : null,
				]
			);

			$where_args = [
				'where' => [
					// @TODO: Same as above ^ description seems a little vague
					'description' => __( 'Arguments for filtering the connection', 'wp-graphql' ),
					'type'        => $connection_name . 'WhereArgs',
				],
			];

		}

		register_graphql_type(
			$connection_name . 'Edge',
			[
				'description' => __( 'An edge in a connection', 'wp-graphql' ),
				'fields'      => array_merge(
					[
						'cursor' => [
							'type'        => 'String',
							'description' => __( 'A cursor for use in pagination', 'wp-graphql' ),
							'resolve'     => $resolve_cursor,
						],
						'node'   => [
							'type'        => $to_type,
							'description' => __( 'The item at the end of the edge', 'wp-graphql' ),
							'resolve'     => function ( $source, $args, $context, ResolveInfo $info ) use ( $resolve_node ) {
								if ( ! empty( $resolve_node ) && is_callable( $resolve_node ) ) {
									return ! empty( $source['node'] ) ? $resolve_node( $source['node'], $args, $context, $info ) : null;
								} else {
									return $source['node'];
								}
							},
						],
					],
					$edge_fields
				),
			]
		);

		register_graphql_type(
			$connection_name,
			[
				// Translators: the placeholders are the name of the Types the connection is between.
				'description' => __( sprintf( 'Connection between the %1$s type and the %2s type', $from_type, $to_type ), 'wp-graphql' ),
				'fields'      => array_merge(
					[
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
						'nodes'    => [
							'type'        => [
								'list_of' => $to_type,
							],
							'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
							'resolve'     => function ( $source, $args, $context, $info ) use ( $resolve_node ) {
								$nodes = [];
								if ( ! empty( $source['nodes'] ) && is_array( $source['nodes'] ) ) {
									if ( is_callable( $resolve_node ) ) {
										foreach ( $source['nodes'] as $node ) {
											$nodes[] = $resolve_node( $node, $args, $context, $info );
										}
									} else {
										return $source['nodes'];
									}
								}

								return $nodes;
							},
						],
					],
					$connection_fields
				),
			]
		);

		register_graphql_field(
			$from_type,
			$from_field_name,
			[
				'type'        => $connection_name,
				'args'        => array_merge(
					[
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
					],
					$where_args
				),
				'description' => sprintf( __( 'Connection between the %1$s type and the %2s type', 'wp-graphql' ), $from_type, $to_type ),
				'resolve'     => function ( $root, $args, $context, $info ) use ( $resolve_connection, $connection_name ) {

					/**
					 * Set the connection args context. Use base64_encode( wp_json_encode( $args ) ) to prevent conflicts as there can be
					 * numerous instances of the same connection within any given query. If the connection
					 * has the same args, we can use the existing cached args instead of storing new context
					 */
					$connection_id = $connection_name . ':' . base64_encode( wp_json_encode( $args ) );

					/**
					 * Set the previous connection by getting the currentConnection
					 */
					$context->prevConnection = isset( $context->currentConnection ) ? $context->currentConnection : null;

					/**
					 * Set the currentConnection using the $connectionId
					 */
					$context->currentConnection = $connection_id;

					/**
					 * Set the connectionArgs if they haven't already been set
					 * (it's possible, although rare, to have multiple connections in a single query with the same args)
					 */
					if ( ! isset( $context->connectionArgs[ $connection_id ] ) ) {
						$context->connectionArgs[ $connection_id ] = $args;
					}

					/**
					 * Return the results
					 */
					return call_user_func( $resolve_connection, $root, $args, $context, $info );
				},
			]
		);

	}

	/**
	 * Handles registration of a mutation to the Type registry
	 *
	 * @param string $mutation_name Name of the mutation being registered
	 * @param array  $config        Info about the mutation being registered
	 *
	 * @access public
	 * @return void
	 */
	public static function register_mutation( $mutation_name, $config ) {

		$output_fields = [
			'clientMutationId' => [
				'type' => [
					'non_null' => 'String',
				],
			],
		];

		if ( ! empty( $config['outputFields'] ) && is_array( $config['outputFields'] ) ) {
			$output_fields = array_merge( $config['outputFields'], $output_fields );
		}

		register_graphql_object_type(
			$mutation_name . 'Payload',
			[
				'description' => __( sprintf( 'The payload for the %s mutation', $mutation_name ) ),
				'fields'      => $output_fields,
			]
		);

		$input_fields = [
			'clientMutationId' => [
				'type' => [
					'non_null' => 'String',
				],
			],
		];

		if ( ! empty( $config['inputFields'] ) && is_array( $config['inputFields'] ) ) {
			$input_fields = array_merge( $config['inputFields'], $input_fields );
		}

		register_graphql_input_type(
			$mutation_name . 'Input',
			[
				'description' => __( sprintf( 'Input for the %s mutation', $mutation_name ) ),
				'fields'      => $input_fields,
			]
		);

		$mutateAndGetPayload = ! empty( $config['mutateAndGetPayload'] ) ? $config['mutateAndGetPayload'] : null;

		register_graphql_field(
			'rootMutation',
			$mutation_name,
			[
				'description' => __( sprintf( 'The payload for the %s mutation', $mutation_name ) ),
				'args'        => [
					'input' => [
						'type'        => [
							'non_null' => $mutation_name . 'Input',
						],
						'description' => __( sprintf( 'Input for the %s mutation', $mutation_name ), 'wp-graphql' ),
					],
				],
				'type'        => $mutation_name . 'Payload',
				'resolve'     => function ( $root, $args, $context, ResolveInfo $info ) use ( $mutateAndGetPayload, $mutation_name ) {
					// @todo: Might want to check that this is callable before invoking, otherwise errors could happen
					if ( ! is_callable( $mutateAndGetPayload ) ) {
						// Translators: The placeholder is the name of the mutation
						throw new \Exception( sprintf( __( 'The resolver for the mutation %s is not callable', 'wp-graphql' ), $mutation_name ) );
					}
					$payload                     = call_user_func( $mutateAndGetPayload, $args['input'], $context, $info );
					$payload['clientMutationId'] = $args['input']['clientMutationId'];

					return $payload;
				},
			]
		);

	}

}
