<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;

register_graphql_object_type( 'Menu', [
	'description' => __( 'Menus are the containers for navigation items. Menus can be assigned to menu locations, which are typically registered by the active theme.', 'wp-graphql' ),
	'fields'      => [
		'id'     => [
			'type'        => [
				'non_null' => 'ID',
			],
			'description' => __( 'ID of the nav menu.', 'wp-graphql' ),
			'resolve'     => function( \WP_Term $menu ) {
				return ! empty( $menu->term_id ) ? Relay::toGlobalId( 'Menu', $menu->term_id ) : null;
			},
		],
		'count'  => [
			'type'        => 'Int',
			'description' => __( 'The number of items in the menu', 'wp-graphql' ),
		],
		'menuId' => [
			'type'        => 'Int',
			'description' => __( 'WP ID of the nav menu.', 'wp-graphql' ),
			'resolve'     => function( \WP_Term $menu ) {
				return ! empty( $menu->term_id ) ? $menu->term_id : null;
			},
		],
		'name'   => [
			'type'        => 'String',
			'description' => esc_html__( 'Display name of the menu. Equivalent to WP_Term->name.', 'wp-graphql' ),
		],
		'slug'   => [
			'type'        => 'String',
			'description' => esc_html__( 'The url friendly name of the menu. Equivalent to WP_Term->slug', 'wp-graphql' ),
		],
	]
] );
