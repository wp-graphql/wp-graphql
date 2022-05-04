<?php

namespace WPGraphQL\Registry;

use Exception;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use InvalidArgumentException;
use WPGraphQL\Connection\Comments;
use WPGraphQL\Connection\MenuItems;
use WPGraphQL\Connection\PostObjects;
use WPGraphQL\Connection\Revisions;
use WPGraphQL\Connection\Taxonomies;
use WPGraphQL\Connection\TermObjects;
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
use WPGraphQL\Type\Enum\ContentTypeIdTypeEnum;
use WPGraphQL\Type\Enum\MenuItemNodeIdTypeEnum;
use WPGraphQL\Type\Enum\MenuNodeIdTypeEnum;
use WPGraphQL\Type\Enum\TaxonomyIdTypeEnum;
use WPGraphQL\Type\Enum\TermNodeIdTypeEnum;
use WPGraphQL\Type\Enum\UserNodeIdTypeEnum;
use WPGraphQL\Type\Enum\UsersConnectionOrderbyEnum;
use WPGraphQL\Type\Input\UsersConnectionOrderbyInput;
use WPGraphQL\Type\InterfaceType\CommenterInterface;
use WPGraphQL\Type\InterfaceType\ContentNode;
use WPGraphQL\Type\InterfaceType\ContentTemplate;
use WPGraphQL\Type\InterfaceType\DatabaseIdentifier;
use WPGraphQL\Type\InterfaceType\EnqueuedAsset;
use WPGraphQL\Type\InterfaceType\HierarchicalContentNode;
use WPGraphQL\Type\InterfaceType\HierarchicalTermNode;
use WPGraphQL\Type\InterfaceType\MenuItemLinkable;
use WPGraphQL\Type\InterfaceType\NodeWithAuthor;
use WPGraphQL\Type\InterfaceType\NodeWithComments;
use WPGraphQL\Type\InterfaceType\NodeWithContentEditor;
use WPGraphQL\Type\InterfaceType\NodeWithExcerpt;
use WPGraphQL\Type\InterfaceType\NodeWithFeaturedImage;
use WPGraphQL\Type\InterfaceType\NodeWithPageAttributes;
use WPGraphQL\Type\InterfaceType\NodeWithRevisions;
use WPGraphQL\Type\InterfaceType\NodeWithTemplate;
use WPGraphQL\Type\InterfaceType\NodeWithTitle;
use WPGraphQL\Type\InterfaceType\Node;
use WPGraphQL\Type\InterfaceType\NodeWithTrackbacks;
use WPGraphQL\Type\InterfaceType\TermNode;
use WPGraphQL\Type\InterfaceType\UniformResourceIdentifiable;
use WPGraphQL\Type\ObjectType\EnqueuedScript;
use WPGraphQL\Type\ObjectType\EnqueuedStylesheet;
use WPGraphQL\Type\Union\ContentRevisionUnion;
use WPGraphQL\Type\Union\PostObjectUnion;
use WPGraphQL\Type\Union\MenuItemObjectUnion;
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
use WPGraphQL\Type\Enum\ContentTypeEnum;
use WPGraphQL\Type\Enum\PluginStatusEnum;
use WPGraphQL\Type\Enum\RelationEnum;
use WPGraphQL\Type\Enum\TaxonomyEnum;
use WPGraphQL\Type\Enum\TermObjectsConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\TimezoneEnum;
use WPGraphQL\Type\Enum\UserRoleEnum;
use WPGraphQL\Type\Enum\UsersConnectionSearchColumnEnum;
use WPGraphQL\Type\Input\DateInput;
use WPGraphQL\Type\Input\DateQueryInput;
use WPGraphQL\Type\Input\PostObjectsConnectionOrderbyInput;
use WPGraphQL\Type\ObjectType\Avatar;
use WPGraphQL\Type\ObjectType\Comment;
use WPGraphQL\Type\ObjectType\CommentAuthor;
use WPGraphQL\Type\ObjectType\MediaDetails;
use WPGraphQL\Type\ObjectType\MediaItemMeta;
use WPGraphQL\Type\ObjectType\MediaSize;
use WPGraphQL\Type\ObjectType\Menu;
use WPGraphQL\Type\ObjectType\MenuItem;
use WPGraphQL\Type\ObjectType\PageInfo;
use WPGraphQL\Type\ObjectType\Plugin;
use WPGraphQL\Type\ObjectType\PostObject;
use WPGraphQL\Type\ObjectType\ContentType;
use WPGraphQL\Type\ObjectType\PostTypeLabelDetails;
use WPGraphQL\Type\ObjectType\RootMutation;
use WPGraphQL\Type\ObjectType\RootQuery;
use WPGraphQL\Type\ObjectType\SettingGroup;
use WPGraphQL\Type\ObjectType\Taxonomy;
use WPGraphQL\Type\ObjectType\TermObject;
use WPGraphQL\Type\ObjectType\Theme;
use WPGraphQL\Type\ObjectType\User;
use WPGraphQL\Type\ObjectType\UserRole;
use WPGraphQL\Type\ObjectType\Settings;
use WPGraphQL\Type\Union\TermObjectUnion;
use WPGraphQL\Type\WPConnectionType;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Type\WPInterfaceType;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Type\WPScalar;
use WPGraphQL\Type\WPUnionType;
use WPGraphQL\Utils\Utils;

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
	 */
	protected $types;


	/**
	 * The loaders needed to register types
	 *
	 * @var array
	 */
	protected $type_loaders;

	/**
	 * Stores a list of Types that need to be eagerly loaded instead of lazy loaded.
	 *
	 * Types that exist in the Schema but are only part of a Union/Interface ResolveType but not
	 * referenced directly need to be eagerly loaded.
	 *
	 * @var array
	 */
	protected $eager_type_map;

	/**
	 * TypeRegistry constructor.
	 */
	public function __construct() {
		$this->types          = [];
		$this->type_loaders   = [];
		$this->eager_type_map = [];
	}

	/**
	 * Formats the array key to a more friendly format
	 *
	 * @param string $key Name of the array key to format
	 *
	 * @return string
	 */
	protected function format_key( string $key ) {
		return strtolower( $key );
	}

	/**
	 * Returns the eager type map, an array of Type definitions for Types that
	 * are not directly referenced in the schema.
	 *
	 * Types can add "eagerlyLoadType => true" when being registered to be included
	 * in the eager_type_map.
	 *
	 * @return array
	 */
	protected function get_eager_type_map() {

		if ( ! empty( $this->eager_type_map ) ) {
			return array_map( function ( $type_name ) {
				return $this->get_type( $type_name );
			}, $this->eager_type_map );

		}

		return [];
	}

	/**
	 * Initialize the TypeRegistry
	 *
	 * @throws Exception
	 *
	 * @return void
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
		add_action( 'init_graphql_type_registry', [ $this, 'init_type_registry' ], 5, 1 );

		/**
		 * Fire an action as the Type registry is being initiated
		 *
		 * @param TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'init_graphql_type_registry', $this );

	}

	/**
	 * Initialize the Type Registry
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public function init_type_registry( TypeRegistry $type_registry ) {

		/**
		 * Fire an action as the type registry is initialized. This executes
		 * before the `graphql_register_types` action to allow for earlier hooking
		 *
		 * @param TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_initial_types', $type_registry );

		// Register Interfaces.
		Node::register_type();
		CommenterInterface::register_type( $type_registry );
		ContentNode::register_type( $type_registry );
		ContentTemplate::register_type();
		DatabaseIdentifier::register_type();
		EnqueuedAsset::register_type( $type_registry );
		HierarchicalTermNode::register_type( $type_registry );
		HierarchicalContentNode::register_type( $type_registry );
		MenuItemLinkable::register_type( $type_registry );
		NodeWithAuthor::register_type( $type_registry );
		NodeWithComments::register_type( $type_registry );
		NodeWithContentEditor::register_type( $type_registry );
		NodeWithExcerpt::register_type( $type_registry );
		NodeWithFeaturedImage::register_type( $type_registry );
		NodeWithRevisions::register_type( $type_registry );
		NodeWithTitle::register_type( $type_registry );
		NodeWithTemplate::register_type( $type_registry );
		NodeWithTrackbacks::register_type( $type_registry );
		NodeWithPageAttributes::register_type( $type_registry );
		TermNode::register_type( $type_registry );
		UniformResourceIdentifiable::register_type( $type_registry );

		// register types
		RootQuery::register_type();
		RootQuery::register_post_object_fields();
		RootQuery::register_term_object_fields();
		RootMutation::register_type();
		Avatar::register_type();
		Comment::register_type();
		CommentAuthor::register_type();
		ContentTemplate::register_content_template_types();
		EnqueuedStylesheet::register_type();
		EnqueuedScript::register_type();
		MediaDetails::register_type();
		MediaItemMeta::register_type();
		MediaSize::register_type();
		Menu::register_type();
		MenuItem::register_type();
		PageInfo::register_type();
		Plugin::register_type();
		ContentType::register_type();
		PostTypeLabelDetails::register_type();
		Settings::register_type( $this );
		Taxonomy::register_type();
		Theme::register_type();
		User::register_type();
		UserRole::register_type();

		AvatarRatingEnum::register_type();
		CommentsConnectionOrderbyEnum::register_type();
		ContentNodeIdTypeEnum::register_type();
		ContentTypeEnum::register_type();
		ContentTypeIdTypeEnum::register_type();
		MediaItemSizeEnum::register_type();
		MediaItemStatusEnum::register_type();
		MenuLocationEnum::register_type();
		MenuItemNodeIdTypeEnum::register_type();
		MenuNodeIdTypeEnum::register_type();
		MimeTypeEnum::register_type();
		OrderEnum::register_type();
		PluginStatusEnum::register_type();
		PostObjectFieldFormatEnum::register_type();
		PostObjectsConnectionDateColumnEnum::register_type();
		PostObjectsConnectionOrderbyEnum::register_type();
		PostStatusEnum::register_type();
		RelationEnum::register_type();
		TaxonomyEnum::register_type();
		TaxonomyIdTypeEnum::register_type();
		TermNodeIdTypeEnum::register_type();
		TermObjectsConnectionOrderbyEnum::register_type();
		TimezoneEnum::register_type();
		UserNodeIdTypeEnum::register_type();
		UserRoleEnum::register_type();
		UsersConnectionOrderbyEnum::register_type();
		UsersConnectionSearchColumnEnum::register_type();

		DateInput::register_type();
		DateQueryInput::register_type();
		PostObjectsConnectionOrderbyInput::register_type();
		UsersConnectionOrderbyInput::register_type();

		ContentRevisionUnion::register_type( $this );
		MenuItemObjectUnion::register_type( $this );
		PostObjectUnion::register_type( $this );
		TermObjectUnion::register_type( $this );

		/**
		 * Register core connections
		 */
		Comments::register_connections();
		MenuItems::register_connections();
		PostObjects::register_connections();
		Revisions::register_connections( $this );
		Taxonomies::register_connections();
		TermObjects::register_connections();
		Users::register_connections();

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
		UpdateSettings::register_mutation( $this );

		/**
		 * Register PostObject types based on post_types configured to show_in_graphql
		 *
		 * @var \WP_Post_Type[] $allowed_post_types
		 */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types( 'objects' );

		/** @var \WP_Taxonomy[] $allowed_taxonomies */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		foreach ( $allowed_post_types as $post_type_object ) {
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

			foreach ( $allowed_taxonomies as $tax_object ) {
				// If the taxonomy is in the array of taxonomies registered to the post_type
				if ( in_array( $tax_object->name, get_object_taxonomies( $post_type_object->name ), true ) ) {
					register_graphql_input_type(
						$post_type_object->graphql_single_name . ucfirst( $tax_object->graphql_plural_name ) . 'NodeInput',
						[
							'description' => sprintf( __( 'List of %1$s to connect the %2$s to. If an ID is set, it will be used to create the connection. If not, it will look for a slug. If neither are valid existing terms, and the site is configured to allow terms to be created during post mutations, a term will be created using the Name if it exists in the input, then fallback to the slug if it exists.', 'wp-graphql' ), $tax_object->graphql_plural_name, $post_type_object->graphql_single_name ),
							'fields'      => [
								'id'          => [
									'type'        => 'Id',
									'description' => sprintf( __( 'The ID of the %1$s. If present, this will be used to connect to the %2$s. If no existing %1$s exists with this ID, no connection will be made.', 'wp-graphql' ), $tax_object->graphql_single_name, $post_type_object->graphql_single_name ),
								],
								'slug'        => [
									'type'        => 'String',
									'description' => sprintf( __( 'The slug of the %1$s. If no ID is present, this field will be used to make a connection. If no existing term exists with this slug, this field will be used as a fallback to the Name field when creating a new term to connect to, if term creation is enabled as a nested mutation.', 'wp-graphql' ), $tax_object->graphql_single_name ),
								],
								'description' => [
									'type'        => 'String',
									'description' => sprintf( __( 'The description of the %1$s. This field is used to set a description of the %1$s if a new one is created during the mutation.', 'wp-graphql' ), $tax_object->graphql_single_name ),
								],
								'name'        => [
									'type'        => 'String',
									'description' => sprintf( __( 'The name of the %1$s. This field is used to create a new term, if term creation is enabled in nested mutations, and if one does not already exist with the provided slug or ID or if a slug or ID is not provided. If no name is included and a term is created, the creation will fallback to the slug field.', 'wp-graphql' ), $tax_object->graphql_single_name ),
								],
							],
						]
					);

					register_graphql_input_type(
						ucfirst( $post_type_object->graphql_single_name ) . ucfirst( $tax_object->graphql_plural_name ) . 'Input',
						[
							'description' => sprintf( __( 'Set relationships between the %1$s to %2$s', 'wp-graphql' ), $post_type_object->graphql_single_name, $tax_object->graphql_plural_name ),
							'fields'      => [
								'append' => [
									'type'        => 'Boolean',
									'description' => sprintf( __( 'If true, this will append the %1$s to existing related %2$s. If false, this will replace existing relationships. Default true.', 'wp-graphql' ), $tax_object->graphql_single_name, $tax_object->graphql_plural_name ),
								],
								'nodes'  => [
									'type'        => [
										'list_of' => $post_type_object->graphql_single_name . ucfirst( $tax_object->graphql_plural_name ) . 'NodeInput',
									],
									'description' => __( 'The input list of items to set.', 'wp-graphql' ),
								],
							],
						]
					);
				}
			}
		}

		/**
		 * Register TermObject types based on taxonomies configured to show_in_graphql
		 */
		foreach ( $allowed_taxonomies as $tax_object ) {
			TermObject::register_taxonomy_object_type( $tax_object );
			TermObjectCreate::register_mutation( $tax_object );
			TermObjectUpdate::register_mutation( $tax_object );
			TermObjectDelete::register_mutation( $tax_object );
		}

		/**
		 * Create the root query fields for any setting type in
		 * the $allowed_setting_types array.
		 */
		$allowed_setting_types = DataSource::get_allowed_settings_by_group( $this );

		/**
		 * The url is not a registered setting for multisite, so this is a polyfill
		 * to expose the URL to the Schema for multisite sites
		 */
		if ( is_multisite() ) {
			$this->register_field( 'GeneralSettings', 'url', [
				'type'        => 'String',
				'description' => __( 'Site URL.', 'wp-graphql' ),
				'resolve'     => function () {
					return get_site_url();
				},
			] );
		}

		if ( ! empty( $allowed_setting_types ) && is_array( $allowed_setting_types ) ) {

			foreach ( $allowed_setting_types as $group_name => $setting_type ) {

				$group_name = DataSource::format_group_name( $group_name );
				$type_name  = SettingGroup::register_settings_group( $group_name, $group_name, $this );

				if ( ! $type_name ) {
					continue;
				}

				register_graphql_field(
					'RootQuery',
					Utils::format_field_name( $type_name ),
					[
						'type'        => $type_name,
						'description' => sprintf( __( "Fields of the '%s' settings group", 'wp-graphql' ), ucfirst( $group_name ) . 'Settings' ),
						'resolve'     => function () use ( $setting_type ) {
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
		 * @param TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_types', $type_registry );

		/**
		 * Fire an action as the type registry is initialized. This executes
		 * during the `graphql_register_types` action to allow for earlier hooking
		 *
		 * @param TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_types_late', $type_registry );

	}

	/**
	 * Given a config for a custom Scalar, this adds the Scalar for use in the Schema.
	 *
	 * @param string $type_name The name of the Type to register
	 * @param array  $config    The config for the scalar type to register
	 *
	 * @throws Exception
	 *
	 * @return void
	 */
	public function register_scalar( string $type_name, array $config ) {
		$config['kind'] = 'scalar';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Registers connections that were passed through the Type registration config
	 *
	 * @param array $config Type config
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	protected function register_connections_from_config( array $config ) {

		$connections = $config['connections'] ?? null;

		if ( ! is_array( $connections ) ) {
			return;
		}

		foreach ( $connections as $field_name => $connection_config ) {

			if ( ! is_array( $connection_config ) ) {
				continue;
			}

			$connection_config['fromType']      = $config['name'];
			$connection_config['fromFieldName'] = $field_name;
			register_graphql_connection( $connection_config );

		}

	}

	/**
	 * Add a Type to the Registry
	 *
	 * @param string $type_name The name of the type to register
	 * @param mixed|array|Type $config The config for the type
	 *
	 * @throws Exception
	 *
	 * @return void
	 */
	public function register_type( string $type_name, $config ) {

		if ( is_array( $config ) && isset( $config['connections'] ) ) {
			$config['name'] = ucfirst( $type_name );
			$this->register_connections_from_config( $config );
		}

		/**
		 * If the Type Name starts with a number, prefix it with an underscore to make it valid
		 */
		if ( ! is_valid_graphql_name( $type_name ) ) {
			graphql_debug(
				sprintf( __( 'The Type name \'%1$s\' is invalid and has not been added to the GraphQL Schema.', 'wp-graphql' ), $type_name ),
				[
					'type'      => 'INVALID_TYPE_NAME',
					'type_name' => $type_name,
				]
			);
			return;
		}

		if ( isset( $this->types[ $this->format_key( $type_name ) ] ) || isset( $this->type_loaders[ $this->format_key( $type_name ) ] ) ) {
			graphql_debug(
				sprintf( __( 'You cannot register duplicate Types to the Schema. The Type \'%1$s\' already exists in the Schema. Make sure to give new Types a unique name.', 'wp-graphql' ), $type_name ),
				[
					'type'      => 'DUPLICATE_TYPE',
					'type_name' => $type_name,
				]
			);
			return;
		}

		$this->type_loaders[ $this->format_key( $type_name ) ] = function () use ( $type_name, $config ) {
			return $this->prepare_type( $type_name, $config );
		};

		if ( is_array( $config ) && isset( $config['eagerlyLoadType'] ) && true === $config['eagerlyLoadType'] && ! isset( $this->eager_type_map[ $this->format_key( $type_name ) ] ) ) {
			$this->eager_type_map[ $this->format_key( $type_name ) ] = $this->format_key( $type_name );
		}
	}

	/**
	 * Add an Object Type to the Registry
	 *
	 * @param string $type_name The name of the type to register
	 * @param array $config The configuration of the type
	 *
	 * @throws Exception
	 * @return void
	 */
	public function register_object_type( string $type_name, array $config ) {
		$config['kind'] = 'object';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add an Interface Type to the registry
	 *
	 * @param string $type_name The name of the type to register
	 * @param array $config he configuration of the type
	 *
	 * @throws Exception
	 * @return void
	 */
	public function register_interface_type( string $type_name, array $config ) {
		$config['kind'] = 'interface';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add an Enum Type to the registry
	 *
	 * @param string $type_name The name of the type to register
	 * @param array $config he configuration of the type
	 *
	 * @return void
	 * @throws Exception
	 */
	public function register_enum_type( string $type_name, array $config ) {
		$config['kind'] = 'enum';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add an Input Type to the Registry
	 *
	 * @param string $type_name The name of the type to register
	 * @param array $config he configuration of the type
	 *
	 * @return void
	 * @throws Exception
	 */
	public function register_input_type( string $type_name, array $config ) {
		$config['kind'] = 'input';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add a Union Type to the Registry
	 *
	 * @param string $type_name The name of the type to register
	 * @param array $config he configuration of the type
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function register_union_type( string $type_name, array $config ) {
		$config['kind'] = 'union';
		$this->register_type( $type_name, $config );
	}

	/**
	 * @param string $type_name The name of the type to register
	 * @param mixed|array|Type $config he configuration of the type
	 *
	 * @return mixed|array|Type|null
	 * @throws Exception
	 */
	public function prepare_type( string $type_name, $config ) {
		/**
		 * Uncomment to help trace eagerly (not lazy) loaded types.
		 */
		// graphql_debug( "prepare_type: {$type_name}", [ 'type' => $type_name ] );

		if ( ! is_array( $config ) ) {
			return $config;
		}

		$prepared_type = null;

		if ( ! empty( $config ) ) {

			$kind           = isset( $config['kind'] ) ? $config['kind'] : null;
			$config['name'] = ucfirst( $type_name );

			switch ( $kind ) {
				case 'enum':
					$prepared_type = new WPEnumType( $config );
					break;
				case 'input':
					$prepared_type = new WPInputObjectType( $config, $this );
					break;
				case 'scalar':
					$prepared_type = new WPScalar( $config, $this );
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

	/**
	 * Given a type name, returns the type or null if not found
	 *
	 * @param string $type_name The name of the Type to get from the registry
	 *
	 * @return mixed
	 * |null
	 */
	public function get_type( string $type_name ) {

		$key = $this->format_key( $type_name );

		if ( isset( $this->type_loaders[ $key ] ) ) {
			$type                = $this->type_loaders[ $key ]();
			$this->types[ $key ] = apply_filters( 'graphql_get_type', $type, $type_name );
			unset( $this->type_loaders[ $key ] );
		}

		return $this->types[ $key ] ?? null;
	}

	/**
	 * Given a type name, determines if the type is already present in the Type Loader
	 *
	 * @param string $type_name The name of the type to check the registry for
	 *
	 * @return bool
	 */
	public function has_type( string $type_name ) {
		return isset( $this->type_loaders[ $this->format_key( $type_name ) ] );
	}

	/**
	 * Return the Types in the registry
	 *
	 * @return array
	 */
	public function get_types() {

		// The full map of types is merged with eager types to support the
		// rename_graphql_type API.
		//
		// All of the types are closures, but eager Types are the full
		// Type definitions up front
		return array_merge( $this->types, $this->get_eager_type_map() );
	}

	/**
	 * Wrapper for prepare_field to prepare multiple fields for registration at once
	 *
	 * @param array  $fields    Array of fields and their settings to register on a Type
	 * @param string $type_name Name of the Type to register the fields to
	 *
	 * @return array
	 * @throws Exception
	 */
	public function prepare_fields( array $fields, string $type_name ) {
		$prepared_fields = [];
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $field_config ) {
				if ( is_array( $field_config ) && isset( $field_config['type'] ) ) {
					$prepared_field = $this->prepare_field( $field_name, $field_config, $type_name );
					if ( ! empty( $prepared_field ) ) {
						$prepared_fields[ $this->format_key( $field_name ) ] = $prepared_field;
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
	 * @return array|null
	 * @throws Exception
	 */
	protected function prepare_field( $field_name, $field_config, $type_name ) {

		if ( ! isset( $field_config['name'] ) ) {
			$field_config['name'] = lcfirst( $field_name );
		}

		if ( ! isset( $field_config['type'] ) ) {
			graphql_debug( sprintf( __( 'The registered field \'%s\' does not have a Type defined. Make sure to define a type for all fields.', 'wp-graphql' ), $field_name ), [
				'type'       => 'INVALID_FIELD_TYPE',
				'type_name'  => $type_name,
				'field_name' => $field_name,
			] );
			return null;
		}

		/**
		 * If the type is a string, create a callable wrapper to get the type from
		 * type registry. This preserves lazy-loading and prevents a bug where a type
		 * has the same name as a function in the global scope (e.g., `header()`) and
		 * is called since it passes `is_callable`.
		 */
		if ( is_string( $field_config['type'] ) ) {
			$field_config['type'] = function () use ( $field_config ) {
				return $this->get_type( $field_config['type'] );
			};
		}

		/**
		 * If the type is an array, it contains type modifiers (e.g., "non_null").
		 * Create a callable wrapper to preserve lazy-loading.
		 */
		if ( is_array( $field_config['type'] ) ) {
			$field_config['type'] = function () use ( $field_config ) {
				return $this->setup_type_modifiers( $field_config['type'] );
			};
		}

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
	 * Processes type modifiers (e.g., "non-null"). Loads types immediately, so do
	 * not call before types are ready to be loaded.
	 *
	 * @param mixed|string|array $type The type definition
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function setup_type_modifiers( $type ) {
		if ( ! is_array( $type ) ) {
			return $type;
		}

		if ( isset( $type['non_null'] ) ) {
			return $this->non_null(
				$this->setup_type_modifiers( $type['non_null'] )
			);
		} elseif ( isset( $type['list_of'] ) ) {
			return $this->list_of(
				$this->setup_type_modifiers( $type['list_of'] )
			);
		}

		return $type;

	}

	/**
	 * Wrapper for the register_field method to register multiple fields at once
	 *
	 * @param string $type_name Name of the type in the Type Registry to add the fields to
	 * @param array  $fields    Fields to register
	 *
	 * @return void
	 */
	public function register_fields( string $type_name, array $fields = [] ) {
		if ( is_string( $type_name ) && ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $config ) {
				if ( is_string( $field_name ) && ! empty( $config ) && is_array( $config ) ) {
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
	 * @return void
	 */
	public function register_field( string $type_name, string $field_name, array $config ) {

		add_filter(
			'graphql_' . $type_name . '_fields',
			function ( $fields ) use ( $type_name, $field_name, $config ) {

				$field_name = Utils::format_field_name( $field_name );

				if ( preg_match( '/^\d/', $field_name ) ) {
					graphql_debug(
						sprintf( __( 'The field \'%1$s\' on Type \'%2$s\' is invalid. Field names cannot start with a number.', 'wp-graphql' ), $field_name, $type_name ),
						[
							'type'       => 'INVALID_FIELD_NAME',
							'field_name' => $field_name,
							'type_name'  => $type_name,
						]
					);
					return $fields;
				};

				if ( isset( $fields[ $field_name ] ) ) {
					graphql_debug(
						sprintf( __( 'You cannot register duplicate fields on the same Type. The field \'%1$s\' already exists on the type \'%2$s\'. Make sure to give the field a unique name.', 'wp-graphql' ), $field_name, $type_name ),
						[
							'type'       => 'DUPLICATE_FIELD',
							'field_name' => $field_name,
							'type_name'  => $type_name,
						]
					);
					return $fields;
				}

				/**
				 * If the field returns a properly prepared field, add it the the field registry
				 */
				$field = $this->prepare_field( $field_name, $config, $type_name );

				if ( ! empty( $field ) ) {
					$fields[ $field_name ] = $field;
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
	 * @return void
	 */
	public function deregister_field( string $type_name, string $field_name ) {

		add_filter(
			'graphql_' . $type_name . '_fields',
			function ( $fields ) use ( $field_name ) {

				if ( isset( $fields[ $field_name ] ) ) {
					unset( $fields[ $field_name ] );
				}

				return $fields;

			}
		);

	}

	/**
	 * Method to register a new connection in the Type registry
	 *
	 * @param array $config The info about the connection being registered
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 * @throws Exception
	 */
	public function register_connection( array $config ) {

		$connection = new WPConnectionType( $config, $this );
		$connection->register_connection();

	}

	/**
	 * Handles registration of a mutation to the Type registry
	 *
	 * @param string $mutation_name Name of the mutation being registered
	 * @param array  $config        Info about the mutation being registered
	 *
	 * @return void
	 * @throws Exception
	 */
	public function register_mutation( string $mutation_name, array $config ) {

		$output_fields = [
			'clientMutationId' => [
				'type'        => 'String',
				'description' => __( 'If a \'clientMutationId\' input is provided to the mutation, it will be returned as output on the mutation. This ID can be used by the client to track the progress of mutations and catch possible duplicate mutation submissions.', 'wp-graphql' ),
			],
		];

		if ( ! empty( $config['outputFields'] ) && is_array( $config['outputFields'] ) ) {
			$output_fields = array_merge( $config['outputFields'], $output_fields );
		}

		$this->register_object_type(
			$mutation_name . 'Payload',
			[
				'description' => sprintf( __( 'The payload for the %s mutation', 'wp-graphql' ), $mutation_name ),
				'fields'      => $output_fields,
			]
		);

		$input_fields = [
			'clientMutationId' => [
				'type'        => 'String',
				'description' => __( 'This is an ID that can be passed to a mutation by the client to track the progress of mutations and catch possible duplicate mutation submissions.', 'wp-graphql' ),
			],
		];

		if ( ! empty( $config['inputFields'] ) && is_array( $config['inputFields'] ) ) {
			$input_fields = array_merge( $config['inputFields'], $input_fields );
		}

		$this->register_input_type(
			$mutation_name . 'Input',
			[
				'description' => sprintf( __( 'Input for the %s mutation', 'wp-graphql' ), $mutation_name ),
				'fields'      => $input_fields,
			]
		);

		$mutateAndGetPayload = ! empty( $config['mutateAndGetPayload'] ) ? $config['mutateAndGetPayload'] : null;

		$this->register_field(
			'rootMutation',
			$mutation_name,
			array_merge( $config, [
				'description' => sprintf( __( 'The payload for the %s mutation', 'wp-graphql' ), $mutation_name ),
				'args'        => [
					'input' => [
						'type'        => [
							'non_null' => $mutation_name . 'Input',
						],
						'description' => sprintf( __( 'Input for the %s mutation', 'wp-graphql' ), $mutation_name ),
					],
				],
				'type'        => $mutation_name . 'Payload',
				'resolve'     => function ( $root, $args, $context, ResolveInfo $info ) use ( $mutateAndGetPayload, $mutation_name ) {
					if ( ! is_callable( $mutateAndGetPayload ) ) {
						// Translators: The placeholder is the name of the mutation
						throw new Exception( sprintf( __( 'The resolver for the mutation %s is not callable', 'wp-graphql' ), $mutation_name ) );
					}

					$filtered_input = apply_filters( 'graphql_mutation_input', $args['input'], $context, $info, $mutation_name );

					$payload = $mutateAndGetPayload( $filtered_input, $context, $info );

					do_action( 'graphql_mutation_response', $payload, $filtered_input, $args['input'], $context, $info, $mutation_name );

					if ( isset( $args['input']['clientMutationId'] ) && ! empty( $args['input']['clientMutationId'] ) ) {
						$payload['clientMutationId'] = $args['input']['clientMutationId'];
					}

					return $payload;
				},
			])
		);

	}

	/**
	 * Given a Type, this returns an instance of a NonNull of that type
	 *
	 * @param mixed $type The Type being wrapped
	 *
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
	 * @param mixed $type The Type being wrapped
	 *
	 * @return ListOfType
	 */
	public function list_of( $type ) {
		if ( is_string( $type ) ) {
			$type_def = $this->get_type( $type );

			if ( is_null( $type_def ) ) {
				return Type::listOf( Type::string() );
			}

			return Type::listOf( $type_def );
		}

		return Type::listOf( $type );
	}

}
