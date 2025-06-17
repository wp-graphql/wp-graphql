<?php

namespace WPGraphQL\Registry;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\Type;
use WPGraphQL;
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
use WPGraphQL\Registry\Utils\PostObject;
use WPGraphQL\Registry\Utils\TermObject;
use WPGraphQL\Type\Connection\Comments;
use WPGraphQL\Type\Connection\MenuItems;
use WPGraphQL\Type\Connection\PostObjects;
use WPGraphQL\Type\Connection\Taxonomies;
use WPGraphQL\Type\Connection\TermObjects;
use WPGraphQL\Type\Connection\Users;
use WPGraphQL\Type\Enum\AvatarRatingEnum;
use WPGraphQL\Type\Enum\CommentNodeIdTypeEnum;
use WPGraphQL\Type\Enum\CommentStatusEnum;
use WPGraphQL\Type\Enum\CommentsConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\ContentNodeIdTypeEnum;
use WPGraphQL\Type\Enum\ContentTypeEnum;
use WPGraphQL\Type\Enum\ContentTypeIdTypeEnum;
use WPGraphQL\Type\Enum\MediaItemSizeEnum;
use WPGraphQL\Type\Enum\MediaItemStatusEnum;
use WPGraphQL\Type\Enum\MenuItemNodeIdTypeEnum;
use WPGraphQL\Type\Enum\MenuLocationEnum;
use WPGraphQL\Type\Enum\MenuNodeIdTypeEnum;
use WPGraphQL\Type\Enum\MimeTypeEnum;
use WPGraphQL\Type\Enum\OrderEnum;
use WPGraphQL\Type\Enum\PluginStatusEnum;
use WPGraphQL\Type\Enum\PostObjectFieldFormatEnum;
use WPGraphQL\Type\Enum\PostObjectsConnectionDateColumnEnum;
use WPGraphQL\Type\Enum\PostObjectsConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\PostStatusEnum;
use WPGraphQL\Type\Enum\RelationEnum;
use WPGraphQL\Type\Enum\ScriptLoadingGroupLocationEnum;
use WPGraphQL\Type\Enum\ScriptLoadingStrategyEnum;
use WPGraphQL\Type\Enum\TaxonomyEnum;
use WPGraphQL\Type\Enum\TaxonomyIdTypeEnum;
use WPGraphQL\Type\Enum\TermNodeIdTypeEnum;
use WPGraphQL\Type\Enum\TermObjectsConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\TimezoneEnum;
use WPGraphQL\Type\Enum\UserNodeIdTypeEnum;
use WPGraphQL\Type\Enum\UserRoleEnum;
use WPGraphQL\Type\Enum\UsersConnectionOrderbyEnum;
use WPGraphQL\Type\Enum\UsersConnectionSearchColumnEnum;
use WPGraphQL\Type\Input\DateInput;
use WPGraphQL\Type\Input\DateQueryInput;
use WPGraphQL\Type\Input\PostObjectsConnectionOrderbyInput;
use WPGraphQL\Type\Input\UsersConnectionOrderbyInput;
use WPGraphQL\Type\InterfaceType\Commenter;
use WPGraphQL\Type\InterfaceType\Connection;
use WPGraphQL\Type\InterfaceType\ContentNode;
use WPGraphQL\Type\InterfaceType\ContentTemplate;
use WPGraphQL\Type\InterfaceType\DatabaseIdentifier;
use WPGraphQL\Type\InterfaceType\Edge;
use WPGraphQL\Type\InterfaceType\EnqueuedAsset;
use WPGraphQL\Type\InterfaceType\HierarchicalContentNode;
use WPGraphQL\Type\InterfaceType\HierarchicalNode;
use WPGraphQL\Type\InterfaceType\HierarchicalTermNode;
use WPGraphQL\Type\InterfaceType\MenuItemLinkable;
use WPGraphQL\Type\InterfaceType\Node;
use WPGraphQL\Type\InterfaceType\NodeWithAuthor;
use WPGraphQL\Type\InterfaceType\NodeWithComments;
use WPGraphQL\Type\InterfaceType\NodeWithContentEditor;
use WPGraphQL\Type\InterfaceType\NodeWithExcerpt;
use WPGraphQL\Type\InterfaceType\NodeWithFeaturedImage;
use WPGraphQL\Type\InterfaceType\NodeWithPageAttributes;
use WPGraphQL\Type\InterfaceType\NodeWithRevisions;
use WPGraphQL\Type\InterfaceType\NodeWithTemplate;
use WPGraphQL\Type\InterfaceType\NodeWithTitle;
use WPGraphQL\Type\InterfaceType\NodeWithTrackbacks;
use WPGraphQL\Type\InterfaceType\OneToOneConnection;
use WPGraphQL\Type\InterfaceType\PageInfo;
use WPGraphQL\Type\InterfaceType\Previewable;
use WPGraphQL\Type\InterfaceType\TermNode;
use WPGraphQL\Type\InterfaceType\UniformResourceIdentifiable;
use WPGraphQL\Type\ObjectType\Avatar;
use WPGraphQL\Type\ObjectType\Comment;
use WPGraphQL\Type\ObjectType\CommentAuthor;
use WPGraphQL\Type\ObjectType\ContentType;
use WPGraphQL\Type\ObjectType\EnqueuedScript;
use WPGraphQL\Type\ObjectType\EnqueuedStylesheet;
use WPGraphQL\Type\ObjectType\MediaDetails;
use WPGraphQL\Type\ObjectType\MediaItemMeta;
use WPGraphQL\Type\ObjectType\MediaSize;
use WPGraphQL\Type\ObjectType\Menu;
use WPGraphQL\Type\ObjectType\MenuItem;
use WPGraphQL\Type\ObjectType\Plugin;
use WPGraphQL\Type\ObjectType\PostTypeLabelDetails;
use WPGraphQL\Type\ObjectType\RootMutation;
use WPGraphQL\Type\ObjectType\RootQuery;
use WPGraphQL\Type\ObjectType\SettingGroup;
use WPGraphQL\Type\ObjectType\Settings;
use WPGraphQL\Type\ObjectType\Taxonomy;
use WPGraphQL\Type\ObjectType\Theme;
use WPGraphQL\Type\ObjectType\User;
use WPGraphQL\Type\ObjectType\UserRole;
use WPGraphQL\Type\Union\MenuItemObjectUnion;
use WPGraphQL\Type\Union\PostObjectUnion;
use WPGraphQL\Type\Union\TermObjectUnion;
use WPGraphQL\Type\WPConnectionType;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Type\WPInterfaceType;
use WPGraphQL\Type\WPMutationType;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Type\WPScalar;
use WPGraphQL\Type\WPUnionType;
use WPGraphQL\Utils\Utils;

/**
 * Class TypeRegistry
 *
 * This class maintains the registry of Types used in the GraphQL Schema
 *
 * @phpstan-import-type InputObjectConfig from \GraphQL\Type\Definition\InputObjectType
 * @phpstan-import-type InterfaceConfig from \GraphQL\Type\Definition\InterfaceType
 * @phpstan-import-type ObjectConfig from \GraphQL\Type\Definition\ObjectType
 * @phpstan-import-type WPEnumTypeConfig from \WPGraphQL\Type\WPEnumType
 * @phpstan-import-type WPScalarConfig from \WPGraphQL\Type\WPScalar
 *
 * @phpstan-type TypeDef \GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType
 *
 * @package WPGraphQL\Registry
 */
class TypeRegistry {

	/**
	 * The registered Types
	 *
	 * @var array<string,?TypeDef>
	 */
	protected $types;

	/**
	 * The keys that are prepared for introspection.
	 *
	 * @var array<string>|null
	 */
	protected static ?array $introspection_keys = null;

	/**
	 * The loaders needed to register types
	 *
	 * @var array<string,callable(): ?TypeDef>
	 */
	protected $type_loaders;

	/**
	 * Stores a list of Types that need to be eagerly loaded instead of lazy loaded.
	 *
	 * Types that exist in the Schema but are only part of a Union/Interface ResolveType but not
	 * referenced directly need to be eagerly loaded.
	 *
	 * @var array<string,string>
	 */
	protected $eager_type_map;

	/**
	 * Stores a list of Types that should be excluded from the schema.
	 *
	 * Type names are filtered by `graphql_excluded_types` and normalized using strtolower(), to avoid case sensitivity issues.
	 *
	 * @var string[]
	 */
	protected $excluded_types = null;

	/**
	 * Stores a list of mutation names that should be excluded from the schema, along with their generated input and payload types.
	 *
	 * Type names are filtered by `graphql_excluded_mutations` and normalized using strtolower(), to avoid case sensitivity issues.
	 *
	 * @var string[]
	 */
	protected $excluded_mutations = null;

	/**
	 * Stores a list of connection Type names that should be excluded from the schema, along with their generated types.
	 *
	 * Type names are filtered by `graphql_excluded_connections` and normalized using strtolower(), to avoid case sensitivity issues.
	 *
	 * Type name
	 *
	 * @var string[]
	 */
	protected $excluded_connections = null;

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
	 * @return array<string,?TypeDef>
	 */
	protected function get_eager_type_map() {
		if ( empty( $this->eager_type_map ) ) {
			return [];
		}

		$resolved_types = [];
		foreach ( $this->eager_type_map as $type_name ) {
			$resolved_types[ $type_name ] = $this->get_type( $type_name );
		}

		return $resolved_types;
	}

	/**
	 * Initialize the TypeRegistry
	 *
	 * @throws \Exception
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
		 * @param \WPGraphQL\Registry\TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'init_graphql_type_registry', $this );
	}

	/**
	 * Initialize the Type Registry
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function init_type_registry( self $type_registry ) {

		/**
		 * Fire an action as the type registry is initialized. This executes
		 * before the `graphql_register_types` action to allow for earlier hooking
		 *
		 * @param \WPGraphQL\Registry\TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_initial_types', $type_registry );

		// Register Interfaces.
		Node::register_type();
		Commenter::register_type( $type_registry );
		Connection::register_type( $type_registry );
		ContentNode::register_type( $type_registry );
		ContentTemplate::register_type();
		DatabaseIdentifier::register_type();
		Edge::register_type( $type_registry );
		EnqueuedAsset::register_type( $type_registry );
		HierarchicalContentNode::register_type( $type_registry );
		HierarchicalNode::register_type( $type_registry );
		HierarchicalTermNode::register_type( $type_registry );
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
		PageInfo::register_type( $type_registry );
		Previewable::register_type( $type_registry );
		OneToOneConnection::register_type( $type_registry );
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
		Plugin::register_type();
		ContentType::register_type();
		PostTypeLabelDetails::register_type();
		Settings::register_type( $this );
		Taxonomy::register_type();
		Theme::register_type();
		User::register_type();
		UserRole::register_type();

		AvatarRatingEnum::register_type();
		CommentNodeIdTypeEnum::register_type();
		CommentsConnectionOrderbyEnum::register_type();
		CommentStatusEnum::register_type();
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
		ScriptLoadingStrategyEnum::register_type();
		ScriptLoadingGroupLocationEnum::register_type();
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

		// Deprecated types.
		MenuItemObjectUnion::register_type( $this ); /* @phpstan-ignore staticMethod.deprecatedClass */
		PostObjectUnion::register_type( $this ); /* @phpstan-ignore staticMethod.deprecatedClass */
		TermObjectUnion::register_type( $this ); /* @phpstan-ignore staticMethod.deprecatedClass */

		/**
		 * Register core connections
		 */
		Comments::register_connections();
		MenuItems::register_connections();
		PostObjects::register_connections();
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
		 * Register PostObject types based on post_types configured to show_in_graphql.
		 */
		$allowed_post_types = WPGraphQL::get_allowed_post_types( 'objects' );
		$allowed_taxonomies = WPGraphQL::get_allowed_taxonomies( 'objects' );

		foreach ( $allowed_post_types as $post_type_object ) {
			PostObject::register_types( $post_type_object );

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
					if ( empty( $post_type_object->graphql_exclude_mutations ) || ! in_array( 'create', $post_type_object->graphql_exclude_mutations, true ) ) {
						PostObjectCreate::register_mutation( $post_type_object );
					}

					if ( empty( $post_type_object->graphql_exclude_mutations ) || ! in_array( 'update', $post_type_object->graphql_exclude_mutations, true ) ) {
						PostObjectUpdate::register_mutation( $post_type_object );
					}
				}

				if ( empty( $post_type_object->graphql_exclude_mutations ) || ! in_array( 'delete', $post_type_object->graphql_exclude_mutations, true ) ) {
					PostObjectDelete::register_mutation( $post_type_object );
				}
			}

			foreach ( $allowed_taxonomies as $tax_object ) {
				// If the taxonomy is in the array of taxonomies registered to the post_type
				if ( in_array( $tax_object->name, get_object_taxonomies( $post_type_object->name ), true ) ) {
					register_graphql_input_type(
						$post_type_object->graphql_single_name . ucfirst( $tax_object->graphql_plural_name ) . 'NodeInput',
						[
							'description' => static function () use ( $tax_object, $post_type_object ) {
								return sprintf(
										// translators: %1$s is the GraphQL plural name of the taxonomy, %2$s is the GraphQL singular name of the post type.
									__( 'List of %1$s to connect the %2$s to. If an ID is set, it will be used to create the connection. If not, it will look for a slug. If neither are valid existing terms, and the site is configured to allow terms to be created during post mutations, a term will be created using the Name if it exists in the input, then fallback to the slug if it exists.', 'wp-graphql' ),
									$tax_object->graphql_plural_name,
									$post_type_object->graphql_single_name
								);
							},
							'fields'      => [
								'id'          => [
									'type'        => 'Id',
									'description' => static function () use ( $tax_object, $post_type_object ) {
										return sprintf(
												// translators: %1$s is the GraphQL name of the taxonomy, %2$s is the GraphQL name of the post type.
											__( 'The ID of the %1$s. If present, this will be used to connect to the %2$s. If no existing %1$s exists with this ID, no connection will be made.', 'wp-graphql' ),
											$tax_object->graphql_single_name,
											$post_type_object->graphql_single_name
										);
									},
								],
								'slug'        => [
									'type'        => 'String',
									'description' => static function () use ( $tax_object ) {
										return sprintf(
											// translators: %1$s is the GraphQL name of the taxonomy.
											__( 'The slug of the %1$s. If no ID is present, this field will be used to make a connection. If no existing term exists with this slug, this field will be used as a fallback to the Name field when creating a new term to connect to, if term creation is enabled as a nested mutation.', 'wp-graphql' ),
											$tax_object->graphql_single_name
										);
									},
								],
								'description' => [
									'type'        => 'String',
									'description' => static function () use ( $tax_object ) {
										return sprintf(
											// translators: %1$s is the GraphQL name of the taxonomy.
											__( 'The description of the %1$s. This field is used to set a description of the %1$s if a new one is created during the mutation.', 'wp-graphql' ),
											$tax_object->graphql_single_name
										);
									},
								],
								'name'        => [
									'type'        => 'String',
									'description' => static function () use ( $tax_object ) {
										return sprintf(
											// translators: %1$s is the GraphQL name of the taxonomy.
											__( 'The name of the %1$s. This field is used to create a new term, if term creation is enabled in nested mutations, and if one does not already exist with the provided slug or ID or if a slug or ID is not provided. If no name is included and a term is created, the creation will fallback to the slug field.', 'wp-graphql' ),
											$tax_object->graphql_single_name
										);
									},
								],
							],
						]
					);

					register_graphql_input_type(
						ucfirst( $post_type_object->graphql_single_name ) . ucfirst( $tax_object->graphql_plural_name ) . 'Input',
						[
							'description' => static function () use ( $tax_object, $post_type_object ) {
								return sprintf(
									// translators: %1$s is the GraphQL name of the post type, %2$s is the plural GraphQL name of the taxonomy.
									__( 'Set relationships between the %1$s to %2$s', 'wp-graphql' ),
									$post_type_object->graphql_single_name,
									$tax_object->graphql_plural_name
								);
							},
							'fields'      => [
								'append' => [
									'type'        => 'Boolean',
									'description' => static function () use ( $tax_object ) {
										return sprintf(
											// translators: %1$s is the GraphQL name of the taxonomy, %2$s is the plural GraphQL name of the taxonomy.
											__( 'If true, this will append the %1$s to existing related %2$s. If false, this will replace existing relationships. Default true.', 'wp-graphql' ),
											$tax_object->graphql_single_name,
											$tax_object->graphql_plural_name
										);
									},
								],
								'nodes'  => [
									'type'        => [
										'list_of' => $post_type_object->graphql_single_name . ucfirst( $tax_object->graphql_plural_name ) . 'NodeInput',
									],
									'description' => static function () {
										return __( 'The input list of items to set.', 'wp-graphql' );
									},
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
			TermObject::register_types( $tax_object );

			if ( empty( $tax_object->graphql_exclude_mutations ) || ! in_array( 'create', $tax_object->graphql_exclude_mutations, true ) ) {
				TermObjectCreate::register_mutation( $tax_object );
			}

			if ( empty( $tax_object->graphql_exclude_mutations ) || ! in_array( 'update', $tax_object->graphql_exclude_mutations, true ) ) {
				TermObjectUpdate::register_mutation( $tax_object );
			}

			if ( empty( $tax_object->graphql_exclude_mutations ) || ! in_array( 'delete', $tax_object->graphql_exclude_mutations, true ) ) {
				TermObjectDelete::register_mutation( $tax_object );
			}
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
			$this->register_field(
				'GeneralSettings',
				'url',
				[
					'type'        => 'String',
					'description' => static function () {
						return __( 'Site URL.', 'wp-graphql' );
					},
					'resolve'     => static function () {
						return get_site_url();
					},
				]
			);
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
						'description' => static function () use ( $group_name ) {
							return sprintf(
								// translators: %s is the GraphQL name of the settings group.
								__( "Fields of the '%s' settings group", 'wp-graphql' ),
								ucfirst( $group_name ) . 'Settings'
							);
						},
						'resolve'     => static function () use ( $setting_type ) {
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
		 * @param \WPGraphQL\Registry\TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_types', $type_registry );

		/**
		 * Fire an action as the type registry is initialized. This executes
		 * during the `graphql_register_types` action to allow for earlier hooking
		 *
		 * @param \WPGraphQL\Registry\TypeRegistry $registry Instance of the TypeRegistry
		 */
		do_action( 'graphql_register_types_late', $type_registry );
	}

	/**
	 * Given a config for a custom Scalar, this adds the Scalar for use in the Schema.
	 *
	 * @param string              $type_name The name of the Type to register
	 * @param array<string,mixed> $config    The config for the scalar type to register
	 *
	 * @phpstan-param WPScalarConfig $config
	 *
	 * @throws \Exception
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
	 * @param array<string,mixed> $config Type config
	 *
	 * @return void
	 *
	 * @throws \Exception
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
	 * @param string                      $type_name The name of the type to register
	 * @param array<string,mixed>|TypeDef $config The config for the type
	 *
	 * @throws \Exception
	 */
	public function register_type( string $type_name, $config ): void {
		/**
		 * If the type should be excluded from the schema, skip it.
		 */
		if ( in_array( strtolower( $type_name ), $this->get_excluded_types(), true ) ) {
			return;
		}
		/**
		 * If the Type Name starts with a number, skip it.
		 */
		if ( ! is_valid_graphql_name( $type_name ) ) {
			graphql_debug(
				sprintf(
					// translators: %s is the name of the type.
					__( 'The Type name \'%1$s\' is invalid and has not been added to the GraphQL Schema.', 'wp-graphql' ),
					$type_name
				),
				[
					'type'      => 'INVALID_TYPE_NAME',
					'type_name' => $type_name,
				]
			);
			return;
		}

		$type_key = $this->format_key( $type_name );

		// If the Type Name is already registered, skip it.
		if ( isset( $this->types[ $type_key ] ) || isset( $this->type_loaders[ $type_key ] ) ) {
			graphql_debug(
				sprintf(
					// translators: %s is the name of the type.
					__( 'You cannot register duplicate Types to the Schema. The Type \'%1$s\' already exists in the Schema. Make sure to give new Types a unique name.', 'wp-graphql' ),
					$type_name
				),
				[
					'type'      => 'DUPLICATE_TYPE',
					'type_name' => $type_name,
				]
			);
			return;
		}

		// Register the type loader.
		$this->type_loaders[ $type_key ] = function () use ( $type_name, $config ) {
			return $this->prepare_type( $type_name, $config );
		};

		// If the config isn't an array, there's nothing left to do.
		if ( ! is_array( $config ) ) {
			return;
		}

		// Register any connections that were passed through the Type config
		if ( isset( $config['connections'] ) ) {
			$config['name'] = ucfirst( $type_name ); // Other types are capitalized in the prepare_type method.
			$this->register_connections_from_config( $config );
		}

		// Load eager types if this is an introspection query.
		$should_load_eagerly = WPGraphQL::is_introspection_query() && ! empty( $config['eagerlyLoadType'] );
		if ( ! $should_load_eagerly || isset( $this->eager_type_map[ $type_key ] ) ) {
			return;
		}

		$this->eager_type_map[ $type_key ] = $type_key;
	}

	/**
	 * Add an Object Type to the Registry
	 *
	 * @param string              $type_name The name of the type to register
	 * @param array<string,mixed> $config The configuration of the type
	 *
	 * @throws \Exception
	 */
	public function register_object_type( string $type_name, array $config ): void {
		$config['kind'] = 'object';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add an Interface Type to the registry
	 *
	 * @param string              $type_name The name of the type to register
	 * @param array<string,mixed> $config The configuration of the type
	 *
	 * @throws \Exception
	 */
	public function register_interface_type( string $type_name, $config ): void {
		$config['kind'] = 'interface';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add an Enum Type to the registry
	 *
	 * @param string              $type_name The name of the type to register
	 * @param array<string,mixed> $config he configuration of the type
	 *
	 * @phpstan-param WPEnumTypeConfig $config
	 *
	 * @throws \Exception
	 */
	public function register_enum_type( string $type_name, array $config ): void {
		$config['kind'] = 'enum';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add an Input Type to the Registry
	 *
	 * @param string              $type_name The name of the type to register
	 * @param array<string,mixed> $config he configuration of the type
	 *
	 * @throws \Exception
	 */
	public function register_input_type( string $type_name, array $config ): void {
		$config['kind'] = 'input';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Add a Union Type to the Registry
	 *
	 * @param string              $type_name The name of the type to register
	 * @param array<string,mixed> $config he configuration of the type
	 *
	 * @throws \Exception
	 */
	public function register_union_type( string $type_name, array $config ): void {
		$config['kind'] = 'union';
		$this->register_type( $type_name, $config );
	}

	/**
	 * Get the keys that are prepared for introspection.
	 *
	 * @return array<string>
	 */
	protected static function get_introspection_keys(): array {

		if ( null === self::$introspection_keys ) {
			/**
			 * Filter the keys that are prepared for introspection.
			 *
			 * @param array<string> $introspection_keys The keys to prepare for introspection.
			 */
			$introspection_keys       = \apply_filters( 'graphql_introspection_keys', [ 'description', 'deprecationReason' ] );
			self::$introspection_keys = $introspection_keys;
		}

		return self::$introspection_keys;
	}

	/**
	 * Prepare the config for introspection. This is used to resolve callable values for description and deprecationReason for
	 * introspection queries.
	 *
	 * @template T of array<string,mixed>
	 * @param T $config The config to prepare.
	 *
	 * @return array<string,mixed> The prepared config.
	 * @phpstan-return T|array{description?: string|null, deprecationReason?: string|null}
	 *
	 * @internal
	 */
	public static function prepare_config_for_introspection( array $config ): array {

		// Get the keys that are prepared for introspection.
		$introspection_keys = self::get_introspection_keys();

		foreach ( $introspection_keys as $key ) {
			if ( ! isset( $config[ $key ] ) || ! is_callable( $config[ $key ] ) ) {
				continue;
			}

			if ( ! WPGraphQL::is_introspection_query() ) {
				// If not introspection, set to null.
				$config[ $key ] = null;
				continue;
			}

			$config[ $key ] = is_callable( $config[ $key ] ) ? $config[ $key ]() : '';
		}

		return $config;
	}

	/**
	 * Prepare the type for registration.
	 *
	 * @template T of WPEnumTypeConfig|WPScalarConfig|array<string,mixed>
	 *
	 * @param string    $type_name The name of the type to prepare
	 * @param T|TypeDef $config    The config for the type
	 *
	 * @return ?TypeDef The prepared type
	 */
	protected function prepare_type( string $type_name, $config ) {
		if ( ! is_array( $config ) ) {
			return $config;
		}

		if ( empty( $config ) ) {
			return null;
		}

		$config         = self::prepare_config_for_introspection( $config );
		$config['name'] = ucfirst( $type_name );

		$kind = isset( $config['kind'] ) ? $config['kind'] : null;
		switch ( $kind ) {
			case 'enum':
				/** @var WPEnumTypeConfig $config */
				$prepared_type = new WPEnumType( $config );
				break;
			case 'input':
				/** @var InputObjectConfig $config */
				$prepared_type = new WPInputObjectType( $config, $this );
				break;
			case 'scalar':
				$prepared_type = new WPScalar( $config, $this );
				break;
			case 'union':
				$prepared_type = new WPUnionType( $config, $this );
				break;
			case 'interface':
				/** @var InterfaceConfig $config */
				$prepared_type = new WPInterfaceType( $config, $this );
				break;
			case 'object':
			default:
				/** @var ObjectConfig $config */
				$prepared_type = new WPObjectType( $config, $this );
		}

		return $prepared_type;
	}

	/**
	 * Given a type name, returns the type or null if not found
	 *
	 * @param string $type_name The name of the Type to get from the registry
	 *
	 * @return ?TypeDef
	 */
	public function get_type( string $type_name ) {
		$key = $this->format_key( $type_name );

		if ( isset( $this->type_loaders[ $key ] ) ) {
			$type = $this->type_loaders[ $key ]();
			/**
			 * Filter the type before it is loaded into the registry.
			 *
			 * @param ?TypeDef $type The type to load.
			 * @param string   $type_name The name of the type.
			 */
			$this->types[ $key ] = apply_filters( 'graphql_get_type', $type, $type_name );
			unset( $this->type_loaders[ $key ] );
		}

		return $this->types[ $key ] ?? null;
	}

	/**
	 * Given a type name, determines if the type is already present in the Type Loader
	 *
	 * @param string $type_name The name of the type to check the registry for
	 */
	public function has_type( string $type_name ): bool {
		return isset( $this->type_loaders[ $this->format_key( $type_name ) ] );
	}

	/**
	 * Return the Types in the registry
	 *
	 * @return TypeDef[]
	 */
	public function get_types(): array {
		// The full map of types is merged with eager types to support the
		// rename_graphql_type API.
		//
		// All of the types are closures, but eager Types are the full
		// Type definitions up front
		return array_filter( array_merge( $this->types, $this->get_eager_type_map() ) );
	}

	/**
	 * Wrapper for prepare_field to prepare multiple fields for registration at once
	 *
	 * @param array<string,mixed> $fields    Array of fields and their settings to register on a Type
	 * @param string              $type_name Name of the Type to register the fields to
	 *
	 * @return array<string,mixed>
	 * @throws \Exception
	 */
	public function prepare_fields( array $fields, string $type_name ): array {
		$prepared_fields = [];
		foreach ( $fields as $field_name => $field_config ) {
			if ( is_array( $field_config ) && isset( $field_config['type'] ) ) {
				$prepared_field = $this->prepare_field( $field_name, $field_config, $type_name );
				if ( ! empty( $prepared_field ) ) {
					$prepared_fields[ $this->format_key( $field_name ) ] = $prepared_field;
				}
			}
		}

		return $prepared_fields;
	}

	/**
	 * Prepare the field to be registered on the type
	 *
	 * @param string              $field_name   Friendly name of the field
	 * @param array<string,mixed> $field_config Config data about the field to prepare
	 * @param string              $type_name    Name of the type to prepare the field for
	 *
	 * @return ?array<string,mixed>
	 * @throws \Exception
	 */
	protected function prepare_field( string $field_name, array $field_config, string $type_name ): ?array {
		if ( ! isset( $field_config['name'] ) ) {
			$field_config['name'] = lcfirst( $field_name );
		}

		if ( ! isset( $field_config['type'] ) ) {
			graphql_debug(
				sprintf(
					/* translators: %s is the Field name. */
					__( 'The registered field \'%s\' does not have a Type defined. Make sure to define a type for all fields.', 'wp-graphql' ),
					$field_name
				),
				[
					'type'       => 'INVALID_FIELD_TYPE',
					'type_name'  => $type_name,
					'field_name' => $field_name,
				]
			);
			return null;
		}

		/**
		 * If the type is a string, create a callable wrapper to get the type from
		 * type registry. This preserves lazy-loading and prevents a bug where a type
		 * has the same name as a function in the global scope (e.g., `header()`) and
		 * is called since it passes `is_callable`.
		 */
		if ( is_string( $field_config['type'] ) ) {
			// Bail if the type is excluded from the Schema.
			if ( in_array( strtolower( $field_config['type'] ), $this->get_excluded_types(), true ) ) {
				return null;
			}

			$field_config['type'] = function () use ( $field_config, $type_name ) {
				$type = $this->get_type( $field_config['type'] );
				if ( ! $type ) {
					$message = sprintf(
					/* translators: %1$s is the Field name, %2$s is the type name the field belongs to. %3$s is the non-existent type name being referenced. */
						__( 'The field \'%1$s\' on Type \'%2$s\' is configured to return \'%3$s\' which is a non-existent Type in the Schema. Make sure to define a valid type for all fields. This might occur if there was a typo with \'%3$s\', or it needs to be registered to the Schema.', 'wp-graphql' ),
						$field_config['name'],
						$type_name,
						$field_config['type']
					);
					// We throw an error here instead of graphql_debug message, as an error would already be thrown if a type didn't exist at this point,
					// but now it will have a more helpful error message.
					throw new Error( esc_html( $message ) );
				}
				return $type;
			};
		}

		/**
		 * If the type is an array, it contains type modifiers (e.g., "non_null").
		 * Create a callable wrapper to preserve lazy-loading.
		 */
		if ( is_array( $field_config['type'] ) ) {
			// Bail if the type is excluded from the Schema.
			$unmodified_type_name = $this->get_unmodified_type_name( $field_config['type'] );

			if ( empty( $unmodified_type_name ) || in_array( strtolower( $unmodified_type_name ), $this->get_excluded_types(), true ) ) {
				return null;
			}

			$field_config['type'] = function () use ( $field_config ) {
				return $this->setup_type_modifiers( $field_config['type'] );
			};
		}

		/**
		 * If the field has arguments, each one must be prepared.
		 */
		if ( isset( $field_config['args'] ) && is_array( $field_config['args'] ) ) {
			foreach ( $field_config['args'] as $arg_name => $arg_config ) {
				$arg = $this->prepare_field( $arg_name, $arg_config, $type_name );

				// Remove the arg if the field could not be prepared.
				if ( empty( $arg ) ) {
					unset( $field_config['args'][ $arg_name ] );
					continue;
				}

				$field_config['args'][ $arg_name ] = $arg;
			}
		}

		/**
		 * If the field has no (remaining) valid arguments, unset the key.
		 */
		if ( empty( $field_config['args'] ) ) {
			unset( $field_config['args'] );
		}

		$field_config = self::prepare_config_for_introspection( $field_config );

		return $field_config;
	}

	/**
	 * Processes type modifiers (e.g., "non-null"). Loads types immediately, so do
	 * not call before types are ready to be loaded.
	 *
	 * @template WrappedType of array{non_null:mixed}|array{list_of:mixed}
	 * @param WrappedType|array<string,mixed>|string|\GraphQL\Type\Definition\Type $type The type to process.
	 *
	 * @return ($type is WrappedType ? \GraphQL\Type\Definition\Type : (array<string,mixed>|string|\GraphQL\Type\Definition\Type))
	 * @throws \Exception
	 */
	public function setup_type_modifiers( $type ) {
		if ( ! is_array( $type ) ) {
			return $type;
		}

		if ( isset( $type['non_null'] ) ) {
			/** @var TypeDef inner_type */
			$inner_type = $this->setup_type_modifiers( $type['non_null'] );
			return $this->non_null( $inner_type );
		}

		if ( isset( $type['list_of'] ) ) {
			/** @var TypeDef $inner_type */
			$inner_type = $this->setup_type_modifiers( $type['list_of'] );
			return $this->list_of( $inner_type );
		}

		return $type;
	}

	/**
	 * Wrapper for the register_field method to register multiple fields at once
	 *
	 * @param string                            $type_name Name of the type in the Type Registry to add the fields to
	 * @param array<string,array<string,mixed>> $fields    Fields to register
	 *
	 * @throws \Exception
	 */
	public function register_fields( string $type_name, array $fields = [] ): void {
		if ( ! empty( $fields ) ) {
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
	 * @param string              $type_name  Name of the type in the Type Registry to add the fields to
	 * @param string              $field_name Name of the field to add to the type
	 * @param array<string,mixed> $config     Info about the field to register to the type
	 *
	 * @throws \Exception
	 */
	public function register_field( string $type_name, string $field_name, array $config ): void {
		add_filter(
			'graphql_' . $type_name . '_fields',
			function ( $fields ) use ( $type_name, $field_name, $config ) {

				// Whether the field should be allowed to have underscores in the field name
				$allow_field_underscores = isset( $config['allowFieldUnderscores'] ) && true === $config['allowFieldUnderscores'];

				$field_name = Utils::format_field_name( $field_name, $allow_field_underscores );

				if ( preg_match( '/^\d/', $field_name ) ) {
					graphql_debug(
						sprintf(
							// translators: %1$s is the field name, %2$s is the type name.
							__( 'The field \'%1$s\' on Type \'%2$s\' is invalid. Field names cannot start with a number.', 'wp-graphql' ),
							$field_name,
							$type_name
						),
						[
							'type'       => 'INVALID_FIELD_NAME',
							'field_name' => $field_name,
							'type_name'  => $type_name,
						]
					);
					return $fields;
				}

				// if a field has already been registered with the same name output a debug message
				if ( isset( $fields[ $field_name ] ) ) {

					// if the existing field is a connection type
					// and the new field is also a connection type
					// and the toType is the same for both
					// then we can allow the duplicate field
					if (
						isset(
							$fields[ $field_name ]['isConnectionField'],
							$config['isConnectionField'],
							$fields[ $field_name ]['toType'],
							$config['toType'],
							$fields[ $field_name ]['connectionTypeName'],
							$config['connectionTypeName']
						) &&
						$fields[ $field_name ]['toType'] === $config['toType'] &&
						$fields[ $field_name ]['connectionTypeName'] === $config['connectionTypeName']
					) {
						return $fields;
					}

					graphql_debug(
						sprintf(
							// translators: %1$s is the field name, %2$s is the type name.
							__( 'You cannot register duplicate fields on the same Type. The field \'%1$s\' already exists on the type \'%2$s\'. Make sure to give the field a unique name.', 'wp-graphql' ),
							$field_name,
							$type_name
						),
						[
							'type'            => 'DUPLICATE_FIELD',
							'field_name'      => $field_name,
							'type_name'       => $type_name,
							'existing_field'  => $fields[ $field_name ],
							'duplicate_field' => $config,
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
			static function ( $fields ) use ( $field_name ) {
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
	 * @param array<string,mixed> $config The info about the connection being registered
	 *
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function register_connection( array $config ): void {
		new WPConnectionType( $config, $this );
	}

	/**
	 * Handles registration of a mutation to the Type registry
	 *
	 * @param string              $mutation_name Name of the mutation being registered
	 * @param array<string,mixed> $config        Info about the mutation being registered
	 *
	 * @throws \Exception
	 */
	public function register_mutation( string $mutation_name, array $config ): void {
		// Bail if the mutation has been excluded from the schema.
		if ( in_array( strtolower( $mutation_name ), $this->get_excluded_mutations(), true ) ) {
			return;
		}

		$config['name'] = $mutation_name;
		new WPMutationType( $config, $this );
	}

	/**
	 * Removes a GraphQL mutation from the schema.
	 *
	 * This works by preventing the mutation from being registered in the first place.
	 *
	 * @uses 'graphql_excluded_mutations' filter.
	 *
	 * @param string $mutation_name Name of the mutation to remove from the schema.
	 *
	 * @since 1.14.0
	 */
	public function deregister_mutation( string $mutation_name ): void {
		// Prevent the mutation from being registered to the scheme directly.
		add_filter(
			'graphql_excluded_mutations',
			static function ( $excluded_mutations ) use ( $mutation_name ): array {
				// Normalize the types to prevent case sensitivity issues.
				$mutation_name = strtolower( $mutation_name );
				// If the type isn't already excluded, add it to the array.
				if ( ! in_array( $mutation_name, $excluded_mutations, true ) ) {
					$excluded_mutations[] = $mutation_name;
				}

				return $excluded_mutations;
			},
			10
		);
	}

	/**
	 * Removes a GraphQL connection from the schema.
	 *
	 * This works by preventing the connection from being registered in the first place.
	 *
	 * @uses 'graphql_excluded_connections' filter.
	 *
	 * @param string $connection_name The GraphQL connection name.
	 */
	public function deregister_connection( string $connection_name ): void {
		add_filter(
			'graphql_excluded_connections',
			static function ( $excluded_connections ) use ( $connection_name ) {
				$connection_name = strtolower( $connection_name );

				if ( ! in_array( $connection_name, $excluded_connections, true ) ) {
					$excluded_connections[] = $connection_name;
				}

				return $excluded_connections;
			}
		);
	}

	/**
	 * Given a Type, this returns an instance of a NonNull of that type.
	 *
	 * @template T of \GraphQL\Type\Definition\NullableType&\GraphQL\Type\Definition\Type
	 * @param T|string $type The Type being wrapped.
	 */
	public function non_null( $type ): \GraphQL\Type\Definition\NonNull {
		if ( is_string( $type ) ) {
			$type_def = $this->get_type( $type );

			/** @phpstan-var T&TypeDef $type_def */
			return Type::nonNull( $type_def );
		}

		return Type::nonNull( $type );
	}

	/**
	 * Given a Type, this returns an instance of a listOf of that type.
	 *
	 * @template T of \GraphQL\Type\Definition\Type
	 * @param T|string $type The Type being wrapped.
	 *
	 * @return \GraphQL\Type\Definition\ListOfType<\GraphQL\Type\Definition\Type>
	 */
	public function list_of( $type ): \GraphQL\Type\Definition\ListOfType {
		if ( is_string( $type ) ) {
			$resolved_type = $this->get_type( $type );

			if ( is_null( $resolved_type ) ) {
				$resolved_type = Type::string();
			}

			$type = $resolved_type;
		}

		return Type::listOf( $type );
	}

	/**
	 * Get the list of GraphQL type names to exclude from the schema.
	 *
	 * Type names are normalized using `strtolower()`, to avoid case sensitivity issues.
	 *
	 * @since 1.13.0
	 *
	 * @return string[]
	 */
	public function get_excluded_types(): array {
		if ( null === $this->excluded_types ) {
			/**
			 * Filter the list of GraphQL types to exclude from the schema.
			 *
			 * Note: using this filter directly will NOT remove the type from being referenced as a possible interface or a union type.
			 * To remove a GraphQL from the schema **entirely**, please use deregister_graphql_type();
			 *
			 * @param string[] $excluded_types The names of the GraphQL Types to exclude.
			 *
			 * @since 1.13.0
			 */
			$excluded_types = apply_filters( 'graphql_excluded_types', [] );

			// Normalize the types to be lowercase, to avoid case-sensitivity issue when comparing.
			$this->excluded_types = ! empty( $excluded_types ) ? array_map( 'strtolower', $excluded_types ) : [];
		}

		return $this->excluded_types;
	}

	/**
	 * Get the list of GraphQL connections to exclude from the schema.
	 *
	 * Type names are normalized using `strtolower()`, to avoid case sensitivity issues.
	 *
	 * @return string[]
	 *
	 * @since 1.14.0
	 */
	public function get_excluded_connections(): array {
		if ( null === $this->excluded_connections ) {
			/**
			 * Filter the list of GraphQL connections to excluded from the registry.
			 *
			 * @param string[] $excluded_connections The names of the GraphQL connections to exclude.
			 *
			 * @since 1.14.0
			 */
			$excluded_connections = apply_filters( 'graphql_excluded_connections', [] );

			// Normalize the types to be lowercase, to avoid case-sensitivity issue when comparing.
			$this->excluded_connections = ! empty( $excluded_connections ) ? array_map( 'strtolower', $excluded_connections ) : [];
		}

		return $this->excluded_connections;
	}

	/**
	 * Get the list of GraphQL mutation names to exclude from the schema.
	 *
	 * Mutation names are normalized using `strtolower()`, to avoid case sensitivity issues.
	 *
	 * @return string[]
	 * @since 1.14.0
	 */
	public function get_excluded_mutations(): array {
		if ( null === $this->excluded_mutations ) {
			/**
			 * Filter the list of GraphQL mutations to excluded from the registry.
			 *
			 * @param string[] $excluded_mutations The names of the GraphQL mutations to exclude.
			 *
			 * @since 1.14.0
			 */
			$excluded_mutations = apply_filters( 'graphql_excluded_mutations', [] );

			// Normalize the types to be lowercase, to avoid case-sensitivity issue when comparing.
			$this->excluded_mutations = ! empty( $excluded_mutations ) ? array_map( 'strtolower', $excluded_mutations ) : [];
		}

		return $this->excluded_mutations;
	}

	/**
	 * Gets the actual type name, stripped of possible NonNull and ListOf wrappers.
	 *
	 * Returns an empty string if the type modifiers are malformed.
	 *
	 * @param string|array<string|int,mixed> $type The (possibly-wrapped) type name.
	 */
	protected function get_unmodified_type_name( $type ): string {
		if ( ! is_array( $type ) ) {
			return $type;
		}

		$type = array_values( $type )[0] ?? '';

		return $this->get_unmodified_type_name( $type );
	}
}
