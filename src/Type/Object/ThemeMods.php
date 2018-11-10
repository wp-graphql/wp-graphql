<?php

namespace WPGraphQL\Type;

use WPGraphQL\Data\DataSource;

register_graphql_object_type( 'ThemeMods', [
	'description' => __( 'All of registered theme modifications', 'wp-graphql' ),
	'interfaces'  => [ WPObjectType::node_interface() ],
	'fields'  => [
		'background' => [ 
			'type' 			=> 'MediaItem',
			'description'	=> __( 'custom background', 'wp-graphql' ),
			'resolve'		=> function( $root, $args, $context, $info ) {	
				if( ! empty( $root['background'] ) ) { 
					return ( ! empty( $root['background']['id'] ) ) ?
					DataSource::resolve_post_object( absint( $root['background']['id'] ), 'attachment' ) :
					null;
				}

				return null;
			}
		],
		'backgroundColor' => [ 
			'type' 			=> 'String',
			'description'	=> __( 'background color', 'wp-graphql' ),
			'resolve'		=> function( $root, $args, $context, $info ) {
				return ( ! empty( $root['background_color'] ) ) ? $root['background_color'] : null;
			}
		],
		'customCssPostId' => [ 
			'type' 			=> 'Int',
			'description'	=> __( 'WP ID of WP Post storing theme custom CSS', 'wp-graphql' ),
			'resolve'		=> function( $root, $args, $context, $info ) {
				return ( ! empty( $root['custom_css_post_id'] ) ) ? 
					absint( $root['custom_css_post_id'] ) :
					null;
			}
		],
		'customCss' => [ 
			'type' 			=> 'Post',
			'description'	=> __( 'WP Post storing theme custom CSS (temporary)', 'wp-graphql' ),
			'resolve'		=> function( $root, $args, $context, $info ) {
				return ( ! empty( $root['custom_css_post_id'] ) ) ? 
					DataSource::resolve_post_object( absint( $root['custom_css_post_id'] ), 'post' ) :
					null;
			}
		],
		'customLogo' => [ 
			'type' 			=> 'MediaItem',
			'description'	=> __( 'Site Custom Logo', 'wp-graphql' ),
			'resolve'		=> function( $root, $args, $context, $info ){
				return ( ! empty( $root['custom_logo'] ) ) ?
					DataSource::resolve_post_object( absint( $root['custom_logo'] ), 'attachment' ) :
					null;
			}
		],
		'headerImage' => [ 
			'type' 			=> 'MediaItem',
			'description'	=> __( 'custom header image', 'wp-graphql' ),
			'resolve'		=> function( $root, $args, $context, $info ){
				if( ! empty ( $root['header_image'] ) ) {
					return ( ! empty( $root['header_image']['id'] ) ) ?
					DataSource::resolve_post_object( absint( $root['header_image']['id'] ), 'attachment' ) :
					null;
				}
				
				return null;
			},
		],
		'navMenu' => [
			'type' 			=> 'Menu',
			'description'	=> __( 'Menus are the containers for navigation items. Menus can be assigned to menu locations, which are typically registered by the active theme.', 'wp-graphql' ),
			'args'			=> [
				'location' => [
					'type'			=> ['non_null' => 'MenuLocationEnum'],
					'description' 	=> __( 'The menu location for the menu being queried', 'wp-graphql' )
				],
			],
			'resolve'		=> function($root, $args, $context, $info ) {
			if ( empty ( $root['nav_menu_locations'] ) ) return null;
			
			$location = $args[ 'location' ];
			return ( ! empty( $root['nav_menu_locations'][ $location ] ) ) ?
				DataSource::resolve_term_object( absint( $root['nav_menu_locations'][ $location ] ), 'nav_menu' ) :
				null;
			}
		]
	]
] );

register_graphql_field( 'RootQuery', 'themeMods', [
	'type'        => 'ThemeMods',
	'resolve'     => function () {
		return DataSource::resolve_theme_mods_data();
	}
] );