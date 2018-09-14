<?php
namespace WPGraphQL\Type;
use GraphQLRelay\Relay;

class MenuItem {
	public static function register_type() {

		register_graphql_object_type( 'MenuItem', [
			'description' => __( 'Navigation menu items are the individual items assigned to a menu. These are rendered as the links in a navigation menu.', 'wp-graphql' ),
			'fields' => [
				'id'               => [
					'type'        => [
						'non_null' => 'ID',
					],
					'description' => __( 'Relay ID of the menu item.', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ! empty( $menu_item->ID ) ? Relay::toGlobalId( 'MenuItem', $menu_item->ID ) : null;
					},
				],
				'cssClasses'       => [
					'type'        => [
						'list_of' => 'String'
					],
					'description' => __( 'Class attribute for the menu item link', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {

						// If all we have is a non-array or an array with one empty
						// string, return an empty array.
						if ( ! is_array( $menu_item->classes ) || empty( $menu_item->classes ) || empty( $menu_item->classes[0] ) ) {
							return [];
						}

						return $menu_item->classes;
					},
				],
				'description'      => [
					'type'        => 'String',
					'description' => __( 'Description of the menu item.', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ( ! empty( $menu_item->description ) ) ? $menu_item->description : null;
					},
				],
				'label'            => [
					'type'        => 'String',
					'description' => __( 'Label or title of the menu item.', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ( ! empty( $menu_item->title ) ) ? $menu_item->title : null;
					},
				],
				'linkRelationship' => [
					'type'        => 'String',
					'description' => __( 'Link relationship (XFN) of the menu item.', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ! empty( $menu_item->xfn ) ? $menu_item->xfn : null;
					},
				],
				'menuItemId'       => [
					'type'        => 'Int',
					'description' => __( 'WP ID of the menu item.', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ! empty( $menu_item->ID ) ? $menu_item->ID : null;
					},
				],
				'target'           => [
					'type'        => 'String',
					'description' => __( 'Target attribute for the menu item link.', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ! empty( $menu_item->target ) ? $menu_item->target : null;
					},
				],
				'title'            => [
					'type'        => 'String',
					'description' => __( 'Title attribute for the menu item link', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ( ! empty( $menu_item->attr_title ) ) ? $menu_item->attr_title : null;
					},
				],
				'url'              => [
					'type'        => 'String',
					'description' => __( 'URL or destination of the menu item.', 'wp-graphql' ),
					'resolve'     => function ( \WP_Post $menu_item ) {
						return ! empty( $menu_item->url ) ? $menu_item->url : null;
					},
				],
			]
		]);

	}
}