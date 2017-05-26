<?php
namespace WPGraphQL\Type\PostType;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\TermObject\Connection\TermObjectConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class PostTypeType
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class PostTypeType extends WPObjectType {

	/**
	 * Holds the type name
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * This holds the field definitions
	 * @var array $fields
	 * @since 0.0.5
	 */
	private static $fields;

	/**
	 * PostTypeType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		/**
		 * Set the type_name
		 * @since 0.0.5
		 */
		self::$type_name = 'postType';

		$config = [
			'name' => self::$type_name,
			'description' => __( 'An Post Type object', 'wp-graphql' ),
			'fields' => self::fields(),
			'interfaces' => [ self::node_interface() ],
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields that make up the PostTypeType
	 *
	 * @return array
	 * @since 0.0.5
	 */
	private static function fields() {

		if ( null === self::$fields ) :
			/**
			 * Get the taxonomies that are allowed in WPGraphQL
			 * @since 0.0.6
			 */
			$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

			self::$fields = function() use ( $allowed_taxonomies ) {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( \WP_Post_Type $post_type, $args, AppContext $context, ResolveInfo $info ) {
							return ( ! empty( $post_type->name ) && ! empty( $post_type->name ) ) ? Relay::toGlobalId( 'post_type', $post_type->name ) : null;
						},
					],
					'name' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The internal name of the post type. This should not be used for display purposes.', 'wp-graphql' ),
					],
					'label' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Display name of the content type.', 'wp-graphql' ),
					],
					//@todo: add "labels" field
					'description' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Description of the content type.', 'wp-graphql' ),
					],
					'public' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether a post type is intended for use publicly either via the admin interface or by front-end users. While the default settings of exclude_from_search, publicly_queryable, show_ui, and show_in_nav_menus are inherited from public, each does not rely on this relationship and controls a very specific intention.', 'wp-graphql' ),
					],
					'hierarchical' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether the post type is hierarchical, for example pages.', 'wp-graphql' ),
					],
					'excludeFromSearch' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to exclude posts with this post type from front end search results.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->exclude_from_search ) ? $post_type->exclude_from_search : false;
						},
					],
					'publiclyQueryable' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether queries can be performed on the front end for the post type as part of parse_request().', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->publicly_queryable ) ? $post_type->publicly_queryable : null;
						},
					],
					'showUi' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to generate and allow a UI for managing this post type in the admin.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->show_ui ) ? $post_type->show_ui : null;
						},
					],
					'showInMenu' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Where to show the post type in the admin menu. To work, $show_ui must be true. If true, the post type is shown in its own top level menu. If false, no menu is shown. If a string of an existing top level menu (eg. "tools.php" or "edit.php?post_type=page"), the post type will be placed as a sub-menu of that.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->show_in_menu ) ? $post_type->show_in_menu : null;
						},
					],
					'showInNavMenus' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Makes this post type available for selection in navigation menus.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->show_in_nav_menus ) ? $post_type->show_in_nav_menus : null;
						},
					],
					'showInAdminBar' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Makes this post type available via the admin bar.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->show_in_admin_bar ) ? $post_type->show_in_admin_bar : null;
						},
					],
					'menuPosition' => [
						'type' => Types::int(),
						'description' => esc_html__( 'The position of this post type in the menu. Only applies if show_in_menu is true.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->menu_position ) ? $post_type->menu_position : null;
						},
					],
					'menuIcon' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The name of the icon file to display as a menu icon.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->menu_icon ) ? $post_type->menu_icon : null;
						},
					],
					'hasArchive' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether this content type should have archives. Content archives are generated by type and by date.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->has_archive ) ? $post_type->has_archive : null;
						},
					],
					'canExport' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether this content type should can be exported.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->can_export ) ? $post_type->can_export : null;
						},
					],
					'deleteWithUser' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether delete this type of content when the author of it is deleted from the system.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->delete_with_user ) ? $post_type->delete_with_user : null;
						},
					],
					'showInRest' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to add the post type route in the REST API `wp/v2` namespace.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->show_in_rest ) ? $post_type->show_in_rest : null;
						},
					],
					'restBase' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Name of content type to diplay in REST API `wp/v2` namespace.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->rest_base ) ? $post_type->rest_base : null;
						},
					],
					'restControllerClass' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The REST Controller class assigned to handling this content type.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : null;
						},
					],
					'showInGraphql' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Whether to add the post type to the GraphQL Schema.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->show_in_graphql ) ? $post_type->show_in_graphql : null;
						},
					],
					'graphqlSingleName' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The singular name of the post type within the GraphQL Schema.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->graphql_single_name ) ? $post_type->graphql_single_name : null;
						},
					],
					'graphqlPluralName' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The plural name of the post type within the GraphQL Schema.', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post_type->graphql_plural_name ) ? $post_type->graphql_plural_name : null;
						},
					],
					'connectedTaxonomyNames' => [
						'type' => Types::list_of( Types::string() ),
						'description' => __( 'A list of Taxonomies associated with the post type', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type_object, array $args, $context, ResolveInfo $info ) use ( $allowed_taxonomies ) {

							$object_taxonomies = get_object_taxonomies( $post_type_object->name );
							$taxonomy_names = [];
							if ( ! empty( $object_taxonomies ) && is_array( $object_taxonomies ) ) {
								foreach ( $object_taxonomies as $taxonomy ) {
									if ( in_array( $taxonomy, $allowed_taxonomies, true ) ) {
										$taxonomy_names[] = $taxonomy;
									}
								}
							}
							return ! empty( $taxonomy_names ) ? $taxonomy_names : null;
						},
					],
					'connectedTaxonomies' => [
						'type' => Types::list_of( Types::taxonomy() ),
						'description' => esc_html__( 'List of Taxonomies connected to the Post Type', 'wp-graphql' ),
						'resolve' => function( \WP_Post_Type $post_type_object, array $args, AppContext $context, ResolveInfo $info ) use ( $allowed_taxonomies ) {
							$tax_objects = [];
							if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
								foreach ( $allowed_taxonomies as $taxonomy ) {
									if ( in_array( $taxonomy, get_object_taxonomies( $post_type_object->name ), true ) ) {
										$tax_object = get_taxonomy( $taxonomy );
										$tax_objects[ $tax_object->graphql_single_name ] = $tax_object;
									}
								}
							}
							return ! empty( $tax_objects ) ? $tax_objects : null;
						},
					],
				];

				/**
				 * Add connections for post_types that are registered to the taxonomy
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
					foreach ( $allowed_taxonomies as $taxonomy ) {
						$tax_object = get_taxonomy( $taxonomy );
						$fields[ $tax_object->graphql_plural_name ] = TermObjectConnectionDefinition::connection( $tax_object );
					}
				}


				/**
				 * Pass the fields through a filter to allow for hooking in and adjusting the shape
				 * of the type's schema
				 * @since 0.0.5
				 */
				return self::prepare_fields( $fields, self::$type_name );

			};
		endif;
		return self::$fields;
	}

}
