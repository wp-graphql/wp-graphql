<?php

namespace WPGraphQL\Type;

use WPGraphQL\Data\DataSource;

register_graphql_object_type( 'ThemeMods', [
  'description' => __( 'All of registered theme modifications' ),
  'interfaces'  => [ WPObjectType::node_interface() ],
  'fields'  => [
	'background' => [ 
	  'type' 				=> 'MediaItem',
	  'description'	=> __( 'custom background' ),
	  'resolve'			=> function( $root, $args, $context, $info ) {
		if( ! empty( $root['background'] ) ) { 
		  return ( ! empty( $root['background']['id'] ) ) ?
			DataSource::resolve_post_object( absint( $root['background']['id'] ), 'attachment' ) :
			null;
		}

		return null;
	  }
	],
	'backgroundColor' => [ 
	  'type' 				=> 'String',
	  'description'	=> __( 'background color' ),
	  'resolve'			=> function( $root, $args, $context, $info ) {
		return ( ! empty( $root['background_color'] ) ) ? $root['background_color'] : null;
	  }
	],
	'customCssPostId' => [ 
	  'type' 				=> 'Int',
	  'description'	=> __( 'custom theme logo' ),
	  'resolve'			=> function( $root, $args, $context, $info ) {
		return ( ! empty( $root['custom_css_post_id'] ) ) ? $root['custom_css_post_id'] : null;
	  }
	],
	'customLogo' => [ 
	  'type' 				=> 'Int',
	  'description'	=> __( 'WP ID of customLogo mediaItem' ),
	  'resolve'			=> function( $root, $args, $context, $info ){
		return ( ! empty( $root['custom_logo'] ) ) ? absint( $root['custom_logo'] ) : null;
	  }
	],
	'headerImage' => [ 
	  'type' 				=> 'MediaItem',
	  'description'	=> __( 'custom header image' ),
	  'resolve'			=> function( $root, $args, $context, $info ){
		if( ! empty ( $root['header_image'] ) ) {
		  return ( ! empty( $root['header_image']['id'] ) ) ?
			DataSource::resolve_post_object( absint( $root['header_image']['id'] ), 'attachment' ) :
			null;
		}
		
		return null;
	  },
	],
	'navMenu' => [
	  'type' 				=> 'Menu',
	  'description'	=> __( 'Menus are the containers for navigation items. Menus can be assigned to menu locations, which are typically registered by the active theme.', 'wp-graphql' ),
	  'args'				=> [
		'location' => [
		  'type'	=> ['non_null' => 'MenuLocationEnum'],
		  'description' => __( 'The menu location for the menu being queried', 'wp-graphql' )
		],
	  ],
	  'resolve'			=> function($root, $args, $context, $info ) {
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