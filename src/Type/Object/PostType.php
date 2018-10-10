<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;

$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

register_graphql_object_type( 'PostType', [
	'description' => __( 'An Post Type object', 'wp-graphql' ),
	'interfaces'  => [ WPObjectType::node_interface() ],
	'fields'      => [
		'id'                     => [
			'type'    => [
				'non_null' => 'ID',
			],
			'resolve' => function( \WP_Post_Type $post_type, $args, $context, $info ) {
				return ( ! empty( $post_type->name ) && ! empty( $post_type->name ) ) ? Relay::toGlobalId( 'postType', $post_type->name ) : null;
			},
		],
		'name'                   => [
			'type'        => 'String',
			'description' => __( 'The internal name of the post type. This should not be used for display purposes.', 'wp-graphql' ),
		],
		'label'                  => [
			'type'        => 'String',
			'description' => __( 'Display name of the content type.', 'wp-graphql' ),
		],
		'labels'                 => [
			'type'        => 'PostTypeLabelDetails',
			'description' => __( 'Details about the post type labels.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, $args, $context, $info ) {
				return get_post_type_labels( $post_type );
			},
		],
		'description'            => [
			'type'        => 'String',
			'description' => __( 'Description of the content type.', 'wp-graphql' ),
		],
		'public'                 => [
			'type'        => 'Boolean',
			'description' => __( 'Whether a post type is intended for use publicly either via the admin interface or by front-end users. While the default settings of exclude_from_search, publicly_queryable, show_ui, and show_in_nav_menus are inherited from public, each does not rely on this relationship and controls a very specific intention.', 'wp-graphql' ),
		],
		'hierarchical'           => [
			'type'        => 'Boolean',
			'description' => __( 'Whether the post type is hierarchical, for example pages.', 'wp-graphql' ),
		],
		'excludeFromSearch'      => [
			'type'        => 'Boolean',
			'description' => __( 'Whether to exclude posts with this post type from front end search results.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, $args, $context, $info ) {
				return ( true === $post_type->exclude_from_search ) ? true : false;
			},
		],
		'publiclyQueryable'      => [
			'type'        => 'Boolean',
			'description' => __( 'Whether queries can be performed on the front end for the post type as part of parse_request().', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->publicly_queryable ) ? true : false;
			},
		],
		'showUi'                 => [
			'type'        => 'Boolean',
			'description' => __( 'Whether to generate and allow a UI for managing this post type in the admin.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->show_ui ) ? true : false;
			},
		],
		'showInMenu'             => [
			'type'        => 'Boolean',
			'description' => __( 'Where to show the post type in the admin menu. To work, $show_ui must be true. If true, the post type is shown in its own top level menu. If false, no menu is shown. If a string of an existing top level menu (eg. "tools.php" or "edit.php?post_type=page"), the post type will be placed as a sub-menu of that.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->show_in_menu ) ? true : false;
			},
		],
		'showInNavMenus'         => [
			'type'        => 'Boolean',
			'description' => __( 'Makes this post type available for selection in navigation menus.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->show_in_nav_menus ) ? true : false;
			},
		],
		'showInAdminBar'         => [
			'type'        => 'Boolean',
			'description' => __( 'Makes this post type available via the admin bar.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return empty( true === $post_type->show_in_admin_bar ) ? true : false;
			},
		],
		'menuPosition'           => [
			'type'        => 'Int',
			'description' => __( 'The position of this post type in the menu. Only applies if show_in_menu is true.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ! empty( $post_type->menu_position ) ? $post_type->menu_position : null;
			},
		],
		'menuIcon'               => [
			'type'        => 'String',
			'description' => __( 'The name of the icon file to display as a menu icon.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ! empty( $post_type->menu_icon ) ? $post_type->menu_icon : null;
			},
		],
		'hasArchive'             => [
			'type'        => 'Boolean',
			'description' => __( 'Whether this content type should have archives. Content archives are generated by type and by date.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->has_archive ) ? true : false;
			},
		],
		'canExport'              => [
			'type'        => 'Boolean',
			'description' => __( 'Whether this content type should can be exported.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->can_export ) ? true : false;
			},
		],
		'deleteWithUser'         => [
			'type'        => 'Boolean',
			'description' => __( 'Whether delete this type of content when the author of it is deleted from the system.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->delete_with_user ) ? true : false;
			},
		],
		'showInRest'             => [
			'type'        => 'Boolean',
			'description' => __( 'Whether to add the post type route in the REST API "wp/v2" namespace.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->show_in_rest ) ? true : false;
			},
		],
		'restBase'               => [
			'type'        => 'String',
			'description' => __( 'Name of content type to diplay in REST API "wp/v2" namespace.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ! empty( $post_type->rest_base ) ? $post_type->rest_base : null;
			},
		],
		'restControllerClass'    => [
			'type'        => 'String',
			'description' => __( 'The REST Controller class assigned to handling this content type.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : null;
			},
		],
		'showInGraphql'          => [
			'type'        => 'Boolean',
			'description' => __( 'Whether to add the post type to the GraphQL Schema.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ( true === $post_type->show_in_graphql ) ? true : false;
			},
		],
		'graphqlSingleName'      => [
			'type'        => 'String',
			'description' => __( 'The singular name of the post type within the GraphQL Schema.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ! empty( $post_type->graphql_single_name ) ? $post_type->graphql_single_name : null;
			},
		],
		'graphqlPluralName'      => [
			'type'        => 'String',
			'description' => __( 'The plural name of the post type within the GraphQL Schema.', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type, array $args, $context, $info ) {
				return ! empty( $post_type->graphql_plural_name ) ? $post_type->graphql_plural_name : null;
			},
		],
		'connectedTaxonomyNames' => [
			'type'        => [
				'list_of' => 'String'
			],
			'args'        => [
				'taxonomies' => [
					'type'        => [
						'list_of' => 'TaxonomyEnum'
					],
					'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
				],
			],
			'description' => __( 'A list of Taxonomies associated with the post type', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type_object, array $args, $context, $info ) use ( $allowed_taxonomies ) {

				$object_taxonomies = get_object_taxonomies( $post_type_object->name );

				$taxonomy_names = [];

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$allowed_taxonomies = ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ? $args['taxonomies'] : $allowed_taxonomies;

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
		'connectedTaxonomies'    => [
			'type'        => [
				'list_of' => 'Taxonomy'
			],
			'args'        => [
				'taxonomies' => [
					'type'        => [
						'list_of' => 'TaxonomyEnum'
					],
					'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
				],
			],
			'description' => __( 'List of Taxonomies connected to the Post Type', 'wp-graphql' ),
			'resolve'     => function( \WP_Post_Type $post_type_object, array $args, $context, $info ) use ( $allowed_taxonomies ) {

				$tax_objects = [];

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$allowed_taxonomies = ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ? $args['taxonomies'] : $allowed_taxonomies;

				if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
					foreach ( $allowed_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, get_object_taxonomies( $post_type_object->name ), true ) ) {
							$tax_object                                      = get_taxonomy( $taxonomy );
							$tax_objects[ $tax_object->graphql_single_name ] = $tax_object;
						}
					}
				}

				return ! empty( $tax_objects ) ? $tax_objects : null;
			},
		],
	],

] );
