<?php

namespace WPGraphQL\Registry;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use WPGraphQL\Connection\Comments;
use WPGraphQL\Connection\MenuItems;
use WPGraphQL\Connection\Menus;
use WPGraphQL\Connection\Plugins;
use WPGraphQL\Connection\PostObjects;
use WPGraphQL\Connection\Revisions;
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
use WPGraphQL\Type\Enum\ContentNodeIdTypeEnum;
use WPGraphQL\Type\Enum\TermNodeIdTypeEnum;
use WPGraphQL\Type\Enum\UserNodeIdTypeEnum;
use WPGraphQL\Type\Enum\UsersConnectionOrderbyEnum;
use WPGraphQL\Type\Input\UsersConnectionOrderbyInput;
use WPGraphQL\Type\InterfaceType\ContentNode;
use WPGraphQL\Type\InterfaceType\ContentTemplate;
use WPGraphQL\Type\InterfaceType\HierarchicalContentNode;
use WPGraphQL\Type\InterfaceType\NodeWithAuthor;
use WPGraphQL\Type\InterfaceType\NodeWithComments;
use WPGraphQL\Type\InterfaceType\NodeWithContentEditor;
use WPGraphQL\Type\InterfaceType\NodeWithExcerpt;
use WPGraphQL\Type\InterfaceType\NodeWithFeaturedImage;
use WPGraphQL\Type\InterfaceType\NodeWithPageAttributes;
use WPGraphQL\Type\InterfaceType\NodeWithRevisions;
use WPGraphQL\Type\InterfaceType\NodeWithTitle;
use WPGraphQL\Type\InterfaceType\Node;
use WPGraphQL\Type\InterfaceType\NodeWithTrackbacks;
use WPGraphQL\Type\InterfaceType\TermNode;
use WPGraphQL\Type\InterfaceType\UniformResourceIdentifiable;
use WPGraphQL\Type\Union\ContentRevisionUnion;
use WPGraphQL\Type\Union\ContentTemplateUnion;
use WPGraphQL\Type\Union\PostObjectUnion;
use WPGraphQL\Type\Union\MenuItemObjectUnion;
use WPGraphQL\Type\Union\CommentAuthorUnion;
use WPGraphQL\Type\Enum\AvatarRatingEnum;
use WPGraphQL\Type\Enum\CommentsConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\MediaItemSizeEnum;
use WPGraphQL\Type\Enum\MediaItemStatusEnum;
use WPGraphQL\Type\Enum\MenuLocationEnum;
use WPGraphQL\Type\Enum\MimeTypeEnum;
use WPGraphQL\Type\Enum\OrderEnum;
use WPGraphQL\Type\Enum\PostObjectFieldFormatEnum;
use WPGraphQL\Type\Enum\PostObjectsConnectionDateColumnEnum;
use WPGraphQL\Type\Enum\PostObjectsConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\PostStatusEnum;
use WPGraphQL\Type\Enum\PostTypeEnum;
use WPGraphQL\Type\Enum\RelationEnum;
use WPGraphQL\Type\Enum\TaxonomyEnum;
use WPGraphQL\Type\Enum\TermObjectsConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\TimezoneEnum;
use WPGraphQL\Type\Enum\UserRoleEnum;
use WPGraphQL\Type\Enum\UsersConnectionSearchColumnEnum;
use WPGraphQL\Type\Input\DateInput;
use WPGraphQL\Type\Input\DateQueryInput;
use WPGraphQL\Type\Input\MenuItemsConnectionWhereArgs;
use WPGraphQL\Type\Input\PostObjectsConnectionOrderbyInput;
use WPGraphQL\Type\Object\Avatar;
use WPGraphQL\Type\Object\Comment;
use WPGraphQL\Type\Object\CommentAuthor;
use WPGraphQL\Type\Object\EditLock;
use WPGraphQL\Type\Object\MediaDetails;
use WPGraphQL\Type\Object\MediaItemMeta;
use WPGraphQL\Type\Object\MediaSize;
use WPGraphQL\Type\Object\Menu;
use WPGraphQL\Type\Object\MenuItem;
use WPGraphQL\Type\Object\PageInfo;
use WPGraphQL\Type\Object\Plugin;
use WPGraphQL\Type\Object\PostObject;
use WPGraphQL\Type\Object\PostType;
use WPGraphQL\Type\Object\PostTypeLabelDetails;
use WPGraphQL\Type\Object\RootMutation;
use WPGraphQL\Type\Object\RootQuery;
use WPGraphQL\Type\Object\SettingGroup;
use WPGraphQL\Type\Object\Taxonomy;
use WPGraphQL\Type\Object\TermObject;
use WPGraphQL\Type\Object\Theme;
use WPGraphQL\Type\Object\User;
use WPGraphQL\Type\Object\UserRole;
use WPGraphQL\Type\Object\Settings;
use WPGraphQL\Type\Union\TermObjectUnion;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Type\WPInterfaceType;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Type\WPUnionType;

/**
 * Class TypeRegistry
 *
 * This class maintains the registry of Types used in the GraphQL Schema
 *
 * @package WPGraphQL\Registry
 */
class TypeRegistry {

	/**
	 * The registered Types
	 *
	 * @var array
	 * @access protected
	 */
	protected $types;

	/**
	 * TypeRegistry constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		$this->types = [];
	}

	/**
	 * Formats the array key to a more friendly format
	 *
	 * @param string $key Name of the array key to format
	 *
	 * @return string
	 * @access protected
	 */
	protected function format_key( $key ) {
		return strtolower( $key );
	}

	/**
	 * Initialize the TypeRegistry
	 *
	 * @throws \Exception
	 */
	public function init() {

		$this->register_type( 'Bool', Type::boolean() );
		$this->register_type( 'Boolean', Type::boolean() );
		$this->register_type( 'Float', Type::float() );
		$this->register_type( 'Number', Type::float() );
		$this->register_type( 'Id', Type::id() );
		$this->register_type( 'Int', Type::int() );
		$this->register_type( 'Integer', Type::int() );
		$this->register_type( 'String', Type::string() );

		/**
		 * When the Type Registry is initialized execute these files
		 */
		add_action( 'init_graphql_type_registry', [ $this, 'init_type_registry' ], 1, 1 );

		/**
		 * Fire an action as the Type registry is being initiated
		 *
		 * @param TypeRegistry $this Instance of the TypeRegistry
		 */
		do_action( 'init_graphql_type_registry', $this );

	}

	/**
	 * Initialize the Type Registry
	 *
	 * @param TypeRegistry $type_registry
	 */
	public function init_type_registry( TypeRegistry $type_registry ) {

		/**
		 * Register Interfaces
		 */
		Node::register_type();
		ContentNode::register_type( $type_registry );
		ContentTemplate::register_type( $type_registry );
		HierarchicalContentNode::register_type( $type_registry );
		NodeWithAuthor::register_type( $type_registry );
		NodeWithComments::register_type( $type_registry );
		NodeWithContentEditor::register_type( $type_registry );
		NodeWithExcerpt::register_type( $type_registry );
		NodeWithFeaturedImage::register_type( $type_registry );
		NodeWithRevisions::register_type( $type_registry );
		NodeWithTitle::register_type( $type_registry );
		NodeWithTrackbacks::register_type( $type_registry );
		NodeWithPageAttributes::register_type( $type_registry );
		TermNode::register_type( $type_registry );
		UniformResourceIdentifiable::register_type( $type_registry );

		/**
		 * Register Types
		 */
		RootQuery::register_type();
		RootQuery::register_post_object_fields();
		RootQuery::register_term_object_fields();
		RootMutation::register_type();
		Avatar::register_type();
		Comment::register_type();
		CommentAuthor::register_type();
		EditLock::register_type();
		MediaDetails::register_type();
		MediaItemMeta::register_type();
		MediaSize::register_type();
		Menu::register_type();
		MenuItem::register_type();
		PageInfo::register_type();
		Plugin::register_type();
		PostType::register_type();
		PostTypeLabelDetails::register_type();
		Settings::register_type();
		Taxonomy::register_type();
		Theme::register_type();
		User::register_type();
		UserRole::register_type();

		AvatarRatingEnum::register_type();
		CommentsConnectionOrderbyEnum::register_type();
		ContentNodeIdTypeEnum::register_type();
		MediaItemSizeEnum::register_type();
		MediaItemStatusEnum::register_type();
		MenuLocationEnum::register_type();
		MimeTypeEnum::register_type();
		OrderEnum::register_type();
		PostObjectFieldFormatEnum::register_type();
		PostObjectsConnectionDateColumnEnum::register_type();
		PostObjectsConnectionOrderbyEnum::register_type();
		PostStatusEnum::register_type();
		PostTypeEnum::register_type();
		RelationEnum::register_type();
		TaxonomyEnum::register_type();
		TermNodeIdTypeEnum::register_type();
		TermObjectsConnectionOrderbyEnum::register_type();
		TimezoneEnum::register_type();
		UserNodeIdTypeEnum::register_type();
		UserRoleEnum::register_type();
		UsersConnectionOrderbyEnum::register_type();
		UsersConnectionSearchColumnEnum::register_type();

		DateInput::register_type();
		DateQueryInput::register_type();
		MenuItemsConnectionWhereArgs::register_type();
		PostObjectsConnectionOrderbyInput::register_type();
		UsersConnectionOrderbyInput::register_type();

		CommentAuthorUnion::register_type( $this );
		ContentRevisionUnion::register_type( $this );
		ContentTemplateUnion::register_type( $this );
		MenuItemObjectUnion::register_type( $this );
		PostObjectUnion::register_type( $this );
		TermObjectUnion::register_type( $this );

		/**
		 * Register core connections
		 */
		Comments::register_connections();
		Menus::register_connections();
		MenuItems::register_connections();
		Plugins::register_connections();
		PostObjects::register_connections();
		Revisions::register_connections( $this );
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
		 * Register PostObject types based on post_types configured to show_in_graphql
		 */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				$post_type_object = get_post_type_object( $post_type );
				PostObject::register_post_object_types( $post_type_object, $type_registry );

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
				TermObject::register_taxonomy_object_type( $taxonomy_object );
				TermObjectCreate::register_mutation( $taxonomy_object );
				TermObjectUpdate::register_mutation( $taxonomy_object );
				TermObjectDelete::register_mutation( $taxonomy_object );
			}
		}

		/**
		 * Create the root query fields for any setting type in
		 * the $allowed_setting_types array.
		 */
		$allowed_setting_types = DataSource::get_allowed_settings_by_group();

		if ( ! empty( $allowed_setting_types ) && is_array( $allowed_setting_types ) ) {
			foreach ( $allowed_setting_types as $group => $setting_type ) {

				$group_name = lcfirst( preg_replace( '[^a-zA-Z0-9 -]', '_', $group ) );
				$group_name = lcfirst( str_replace( '_', ' ', ucwords( $group_name, '_' ) ) );
				$group_name = lcfirst( str_replace( '-', ' ', ucwords( $group_name, '_' ) ) );
				$group_name = lcfirst( str_replace( ' ', '', ucwords( $group_name, ' ' ) ) );
				SettingGroup::register_settings_group( $group_name, $group );

				register_graphql_field(
					'RootQuery',
					$group_name . 'Settings',
					[
						'type'    => ucfirst( $group_name ) . 'Settings',
						'resolve' => function() use ( $setting_type ) {
							return $setting_type;
						},
					]
				);
			}
		}

		/**
		 * Fire an action as the type registry is initialized. This executes
		 * before the `graphql_register_types` action to allow for earlier hooking
		 *
		 * @param \WPGraphQL\Registry\TypeRegistry $this Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_initial_types', $type_registry );

		/**
		 * Fire an action as the type registry is initialized. This executes
		 * before the `graphql_register_types` action to allow for earlier hooking
		 *
		 * @param TypeRegistry $this Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_types', $this );

		/**
		 * Fire an action as the type registry is initialized. This executes
		 * during the `graphql_register_types` action to allow for earlier hooking
		 *
		 * @param \WPGraphQL\Registry\TypeRegistry $this Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_types_late', $type_registry );

	}

	/**
	 * @param $type_name
	 * @param $config
	 *
	 * @throws \Exception
	 *
	 * @return mixed
	 */
	public function register_type( $type_name, $config ) {
		if ( isset( $this->types[ $this->format_key( $type_name ) ] ) ) {
			return $this->types[ $this->format_key( $type_name ) ];
		}
		$prepared_type = $this->prepare_type( $type_name, $config );
		if ( ! empty( $prepared_type ) ) {
			$this->types[ $this->format_key( $type_name ) ] = $prepared_type;
		}

		return $this->types[ $this->format_key( $type_name ) ];
	}

	/**
	 * @param $type_name
	 * @param $config
	 *
	 * @throws \Exception
	 */
	public function register_object_type( $type_name, $config ) {
		$config['kind'] = 'object';
		$this->register_type( $type_name, $config );
	}

	/**
	 * @param $type_name
	 * @param $config
	 *
	 * @throws \Exception
	 */
	public function register_interface_type( $type_name, $config ) {
		$config['kind'] = 'interface';
		$this->register_type( $type_name, $config );
	}

	/**
	 * @param $type_name
	 * @param $config
	 *
	 * @throws \Exception
	 */
	public function register_enum_type( $type_name, $config ) {
		$config['kind'] = 'enum';
		$this->register_type( $type_name, $config );
	}

	/**
	 * @param $type_name
	 * @param $config
	 *
	 * @throws \Exception
	 */
	public function register_input_type( $type_name, $config ) {
		$config['kind'] = 'input';
		$this->register_type( $type_name, $config );
	}

	/**
	 * @param $type_name
	 * @param $config
	 *
	 * @throws \Exception
	 */
	public function register_union_type( $type_name, $config ) {
		$config['kind'] = 'union';
		$this->register_type( $type_name, $config );
	}

	/**
	 * @param $type_name
	 * @param $config
	 *
	 * @return array|WPObjectType
	 * @throws \Exception
	 */
	public function prepare_type( $type_name, $config ) {

		if ( ! is_array( $config ) ) {
			return $config;
		}

		$prepared_type = null;

		if ( is_array( $config ) ) {

			$kind           = isset( $config['kind'] ) ? $config['kind'] : null;
			$config['name'] = ucfirst( $type_name );

			switch ( $kind ) {
				case 'enum':
					$prepared_type = new WPEnumType( $config );
					break;
				case 'input':
					if ( ! empty( $config['fields'] ) && is_array( $config['fields'] ) ) {
						$config['fields'] = function() use ( $config ) {
							$fields = WPInputObjectType::prepare_fields( $config['fields'], $config['name'], $config, $this );
							$fields = $this->prepare_fields( $fields, $config['name'] );

							return $fields;
						};
					}

					$prepared_type = new WPInputObjectType( $config );
					break;
				case 'union':
					$prepared_type = new WPUnionType( $config, $this );
					break;
				case 'interface':
					$prepared_type = new WPInterfaceType( $config, $this );
					break;
				case 'object':
				default:
					$prepared_type = new WPObjectType( $config, $this );
			}
		}

		return $prepared_type;

	}

	public function get_type( $type_name ) {
		return isset( $this->types[ $this->format_key( $type_name ) ] ) ? ( $this->types[ $this->format_key( $type_name ) ] ) : null;
	}

	public function get_types() {
		return $this->types;
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
	public function prepare_fields( $fields, $type_name ) {
		$prepared_fields = [];
		$prepared_field  = null;
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $field_config ) {
				if ( isset( $field_config['type'] ) ) {
					$prepared_field = $this->prepare_field( $field_name, $field_config, $type_name );
					if ( ! empty( $prepared_field ) ) {
						$prepared_fields[ $this->format_key( $field_name ) ] = $prepared_field;
					} else {
						unset( $prepared_fields[ $this->format_key( $field_name ) ] );
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
	protected function prepare_field( $field_name, $field_config, $type_name ) {

		/**
		 * If the Field is a Type definition and not a config
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

			$type = $this->get_type( $field_config['type'] );
			if ( ! empty( $type ) ) {
				$field_config['type'] = $type;
			} else {
				return null;
			}
		}

		$field_config['type'] = $this->setup_type_modifiers( $field_config['type'] );

		if ( ! empty( $field_config['args'] ) && is_array( $field_config['args'] ) ) {
			foreach ( $field_config['args'] as $arg_name => $arg_config ) {
				$field_config['args'][ $arg_name ] = $this->prepare_field( $arg_name, $arg_config, $type_name );
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
	public function setup_type_modifiers( $type ) {

		if ( ! is_array( $type ) ) {
			return $type;
		}
		if ( isset( $type['non_null'] ) ) {
			if ( is_array( $type['non_null'] ) ) {
				$non_null_type = $this->setup_type_modifiers( $type['non_null'] );
			} elseif ( is_string( $type['non_null'] ) ) {
				$non_null_type = $this->get_type( $type['non_null'] );
			}
			$type = isset( $non_null_type ) ? Type::nonNull( $non_null_type ) : Type::nonNull( Type::string() );
		} elseif ( isset( $type['list_of'] ) ) {
			if ( is_array( $type['list_of'] ) ) {
				$list_of_type = $this->setup_type_modifiers( $type['list_of'] );
			} elseif ( is_string( $type['list_of'] ) ) {
				$list_of_type = $this->get_type( $type['list_of'] );
			}

			$type = isset( $list_of_type ) ? Type::listOf( $list_of_type ) : Type::listOf( Type::string() );
		}

		return $type;

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
	public function register_fields( $type_name, $fields ) {
		if ( isset( $type_name ) && is_string( $type_name ) && ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $config ) {
				if ( isset( $field_name ) && is_string( $field_name ) && ! empty( $config ) && is_array( $config ) ) {
					$this->register_field( $type_name, $field_name, $config );
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
	public function register_field( $type_name, $field_name, $config ) {

		add_filter(
			'graphql_' . $type_name . '_fields',
			function( $fields ) use ( $type_name, $field_name, $config ) {

				if ( isset( $fields[ $field_name ] ) ) {
					if ( true === GRAPHQL_DEBUG ) {
						 throw new InvariantViolation( sprintf( __( 'You cannot register duplicate fields on the same Type. The field \'%1$s\' already exists on the type \'%2$s\'. Make sure to give the field a unique name.' ), $field_name, $type_name ) );
					}

					return $fields;
				}

				/**
				 * If the field returns a properly prepared field, add it the the field registry
				 */
				$field = $this->prepare_field( $field_name, $config, $type_name );

				if ( ! empty( $field ) ) {
					$fields[ $field_name ] = $this->prepare_field( $field_name, $config, $type_name );
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
	public function deregister_field( $type_name, $field_name ) {

		add_filter(
			'graphql_' . $type_name . '_fields',
			function( $fields ) use ( $type_name, $field_name ) {

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
	 * Utility method that formats the connection name given the name of the from Type and the to
	 * Type
	 *
	 * @param string $from_type Name of the Type the connection is coming from
	 * @param string $to_type   Name of the Type the connection is going to
	 *
	 * @access protected
	 * @return string
	 */
	protected function get_connection_name( $from_type, $to_type ) {
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
	 * @throws \Exception
	 */
	public function register_connection( $config ) {

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
		$resolve_connection = array_key_exists( 'resolve', $config ) && is_callable( $config['resolve'] ) ? $config['resolve'] : function() {
			return null;
		};
		$connection_name    = ! empty( $config['connectionTypeName'] ) ? $config['connectionTypeName'] : $this->get_connection_name( $from_type, $to_type );
		$where_args         = [];

		/**
		 * If there are any $connectionArgs,
		 * register their inputType and configure them as $where_args to be added to the connection
		 * field as arguments
		 */
		if ( ! empty( $connection_args ) ) {

			$this->register_input_type(
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
					'description' => __( 'Arguments for filtering the connection', 'wp-graphql' ),
					'type'        => $connection_name . 'WhereArgs',
				],
			];

		}

		$this->register_object_type(
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
							'resolve'     => function( $source, $args, $context, ResolveInfo $info ) use ( $resolve_node ) {
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

		$this->register_object_type(
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
							'resolve'     => function( $source, $args, $context, $info ) use ( $resolve_node ) {
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

		$this->register_field(
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
				'description' => ! empty( $config['description'] ) ? $config['description'] : sprintf( __( 'Connection between the %1$s type and the %2s type', 'wp-graphql' ), $from_type, $to_type ),
				'resolve'     => function( $root, $args, $context, $info ) use ( $resolve_connection, $connection_name ) {

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
	 * @throws \Exception
	 */
	public function register_mutation( $mutation_name, $config ) {

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

		$this->register_object_type(
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

		$this->register_input_type(
			$mutation_name . 'Input',
			[
				'description' => __( sprintf( 'Input for the %s mutation', $mutation_name ) ),
				'fields'      => $input_fields,
			]
		);

		$mutateAndGetPayload = ! empty( $config['mutateAndGetPayload'] ) ? $config['mutateAndGetPayload'] : null;

		$this->register_field(
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
				'resolve'     => function( $root, $args, $context, ResolveInfo $info ) use ( $mutateAndGetPayload, $mutation_name ) {
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

	/**
	 * Given a Type, this returns an instance of a NonNull of that type
	 *
	 * @param mixed string|ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType $type
	 * @return NonNull
	 */
	public function non_null( $type ) {
		if ( is_string( $type ) ) {
			$type_def = $this->get_type( $type );
			return Type::nonNull( $type_def );
		}
		return Type::nonNull( $type );
	}

	/**
	 * Given a Type, this returns an instance of a listOf of that type
	 *
	 * @param mixed string|ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType|ListOfType $type
	 * @return ListOfType
	 */
	public function list_of( $type ) {
		if ( is_string( $type ) ) {
			$type_def = $this->get_type( $type );
			return Type::listOf( $type_def );
		}
		return Type::listOf( $type );
	}

}
