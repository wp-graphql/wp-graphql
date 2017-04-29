<?php
namespace WPGraphQL\Type\Taxonomy;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class TaxonomyType
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class TaxonomyType extends WPObjectType {

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
	 * TaxonomyType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		/**
		 * Set the type_name
		 * @since 0.0.5
		 */
		self::$type_name = 'taxonomy';

		$config = [
			'name' => self::$type_name,
			'description' => __( 'A taxonomy object', 'wp-graphql' ),
			'fields' => self::fields(),
			'interfaces' => [ self::node_interface() ],
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields for the TaxonomyType. The fields are passed through a filter so the shape of the schema
	 * can be modified
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 * @since 0.0.5
	 */
	private function fields() {

		if ( null === self::$fields ) {

			/**
			 * Get the post_types that are allowed in WPGraphQL
			 * @since 0.0.6
			 */
			$allowed_post_types = \WPGraphQL::$allowed_post_types;

			self::$fields = function() use ( $allowed_post_types ) {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( $taxonomy, $args, AppContext $context, ResolveInfo $info ) {
							return ( ! empty( $info->parentType ) && ! empty( $taxonomy->name ) ) ? Relay::toGlobalId( $info->parentType, $taxonomy->name ) : null;
						},
					],
					'name' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The display name of the taxonomy. This field is equivalent to WP_Taxonomy->label', 'wp-graphql' ),
					],
					'label' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Name of the taxonomy shown in the menu. Usually plural.', 'wp-graphql' ),
					],
					//@todo: add "labels" field
					'description' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Description of the taxonomy. This field is equivalent to WP_Taxonomy->description', 'wp-graphql' ),
					],
					'public' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether the taxonomy is publicly queryable', 'wp-graphql' ),
					],
					'hierarchical' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether the taxonomy is hierarchical', 'wp-graphql' ),
					],
					'showUi' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to generate and allow a UI for managing terms in this taxonomy in the admin', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_ui ) ? $taxonomy->show_ui : null;
						},
					],
					'showInMenu' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to show the taxonomy in the admin menu', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_in_menu ) ? $taxonomy->show_in_menu : null;
						},
					],
					'showInNavMenus' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether the taxonomy is available for selection in navigation menus.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_in_nav_menus ) ? $taxonomy->show_in_nav_menus : null;
						},
					],
					'showCloud' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to show the taxonomy as part of a tag cloud widget. This field is equivalent to WP_Taxonomy->show_tagcloud', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_tagcloud ) ? $taxonomy->show_tagcloud : null;
						},
					],
					'showInQuickEdit' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to show the taxonomy in the quick/bulk edit panel.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_in_quick_edit ) ? $taxonomy->show_in_quick_edit : null;
						},
					],
					'showInAdminColumn' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to display a column for the taxonomy on its post type listing screens.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_admin_column ) ? $taxonomy->show_admin_column : null;
						},
					],
					'showInRest' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to add the post type route in the REST API `wp/v2` namespace.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_in_rest ) ? $taxonomy->show_in_rest : null;
						},
					],
					'restBase' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Name of content type to diplay in REST API `wp/v2` namespace.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : null;
						},
					],
					'restControllerClass' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The REST Controller class assigned to handling this content type.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->rest_controller_class ) ? $taxonomy->rest_controller_class : null;
						},
					],
					'showInGraphql' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Whether to add the post type to the GraphQL Schema.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->show_in_graphql ) ? $taxonomy->show_in_graphql : null;
						},
					],
					'graphqlSingleName' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The singular name of the post type within the GraphQL Schema.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->graphql_single_name ) ? $taxonomy->graphql_single_name : null;
						},
					],
					'graphqlPluralName' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The plural name of the post type within the GraphQL Schema.', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $taxonomy->graphql_plural_name ) ? $taxonomy->graphql_plural_name : null;
						},
					],
					'connectedPostTypeNames' => [
						'type' => Types::list_of( Types::string() ),
						'description' => esc_html__( 'A list of Post Types associated with the taxonomy', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) use ( $allowed_post_types ) {
							$post_type_names = [];
							$connected_post_types = $taxonomy->object_type;
							if ( ! empty( $connected_post_types ) && is_array( $connected_post_types ) ) {
								foreach ( $connected_post_types as $post_type ) {
									if ( in_array( $post_type, $allowed_post_types, true ) ) {
										$post_type_names[] = $post_type;
									}
								}
							}
							return ! empty( $post_type_names ) ? $post_type_names : null;
						}
					],
					'connectedPostTypes' => [
						'type' => Types::list_of( Types::post_type() ),
						'description' => esc_html__( 'List of Post Types connected to the Taxonomy', 'wp-graphql' ),
						'resolve' => function( \WP_Taxonomy $taxonomy, array $args, AppContext $context, ResolveInfo $info ) use ( $allowed_post_types ) {
							$post_type_objects = [];
							$connected_post_types = ! empty( $taxonomy->object_type ) ? $taxonomy->object_type : [];
							if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
								foreach ( $allowed_post_types as $post_type ) {
									if ( in_array( $post_type, $connected_post_types, true ) ) {
										$post_type_object = get_post_type_object( $post_type );
										$post_type_objects[ $post_type_object->graphql_single_name ] = $post_type_object;
									}
								}
							}
							return ! empty( $post_type_objects ) ? $post_type_objects : null;
						},
					],
				];

				/**
				 * Add connections for post_types that are registered to the taxonomy
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
					foreach ( $allowed_post_types as $post_type ) {
						$post_type_object = get_post_type_object( $post_type );
						$fields[ $post_type_object->graphql_plural_name ] = PostObjectConnectionDefinition::connection( $post_type_object );
					}
				}

				/**
				 * This prepares the fields by sorting them and applying a filter for adjusting the schema.
				 * Because these fields are implemented via a closure the prepare_fields needs to be applied
				 * to the fields directly instead of being applied to all objects extending
				 * the WPObjectType class.
				 */
				return self::prepare_fields( $fields, self::$type_name );

			};
		}

		return self::$fields;

	}
}
