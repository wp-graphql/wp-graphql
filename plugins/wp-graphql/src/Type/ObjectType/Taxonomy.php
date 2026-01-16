<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Model\Taxonomy as TaxonomyModel;

class Taxonomy {

	/**
	 * Register the Taxonomy type to the GraphQL Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'Taxonomy',
			[
				'description' => static function () {
					return __( 'A taxonomy object', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node' ],
				'model'       => TaxonomyModel::class,
				'connections' => [
					'connectedContentTypes' => [
						'toType'      => 'ContentType',
						'description' => static function () {
							return __( 'List of Content Types associated with the Taxonomy', 'wp-graphql' );
						},
						'resolve'     => static function ( TaxonomyModel $taxonomy, $args, AppContext $context, ResolveInfo $info ) {
							$connected_post_types = ! empty( $taxonomy->object_type ) ? $taxonomy->object_type : [];
							$resolver             = new ContentTypeConnectionResolver( $taxonomy, $args, $context, $info );
							$resolver->set_query_arg( 'contentTypeNames', $connected_post_types );
							return $resolver->get_connection();
						},
					],
					'connectedTerms'        => [
						'toType'               => 'TermNode',
						'connectionInterfaces' => [ 'TermNodeConnection' ],
						'description'          => static function () {
							return __( 'List of Term Nodes associated with the Taxonomy', 'wp-graphql' );
						},
						'resolve'              => static function ( TaxonomyModel $source, $args, AppContext $context, ResolveInfo $info ) {
							$taxonomies = [ $source->name ];

							$resolver = new TermObjectConnectionResolver( $source, $args, $context, $info, $taxonomies );

							return $resolver->get_connection();
						},
					],
				],
				'fields'      => static function () {
					return [
						'id'                  => [
							'description' => static function () {
								return __( 'The globally unique identifier of the taxonomy object.', 'wp-graphql' );
							},
						],
						'name'                => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The display name of the taxonomy. This field is equivalent to WP_Taxonomy->label', 'wp-graphql' );
							},
						],
						'label'               => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Name of the taxonomy shown in the menu. Usually plural.', 'wp-graphql' );
							},
						],
						// @todo: add "labels" field
						'description'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Description of the taxonomy. This field is equivalent to WP_Taxonomy->description', 'wp-graphql' );
							},
						],
						'public'              => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the taxonomy is publicly queryable', 'wp-graphql' );
							},
						],
						'isRestricted'        => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
						'hierarchical'        => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the taxonomy is hierarchical', 'wp-graphql' );
							},
						],
						'showUi'              => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to generate and allow a UI for managing terms in this taxonomy in the admin', 'wp-graphql' );
							},
						],
						'showInMenu'          => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to show the taxonomy in the admin menu', 'wp-graphql' );
							},
						],
						'showInNavMenus'      => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the taxonomy is available for selection in navigation menus.', 'wp-graphql' );
							},
						],
						'showCloud'           => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to show the taxonomy as part of a tag cloud widget. This field is equivalent to WP_Taxonomy->show_tagcloud', 'wp-graphql' );
							},
						],
						'showInQuickEdit'     => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to show the taxonomy in the quick/bulk edit panel.', 'wp-graphql' );
							},
						],
						'showInAdminColumn'   => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to display a column for the taxonomy on its post type listing screens.', 'wp-graphql' );
							},
						],
						'showInRest'          => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to add the post type route in the REST API "wp/v2" namespace.', 'wp-graphql' );
							},
						],
						'restBase'            => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Name of content type to display in REST API "wp/v2" namespace.', 'wp-graphql' );
							},
						],
						'restControllerClass' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The REST Controller class assigned to handling this content type.', 'wp-graphql' );
							},
						],
						'showInGraphql'       => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to add the post type to the GraphQL Schema.', 'wp-graphql' );
							},
						],
						'graphqlSingleName'   => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The singular name of the post type within the GraphQL Schema.', 'wp-graphql' );
							},
						],
						'graphqlPluralName'   => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The plural name of the post type within the GraphQL Schema.', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
