<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;

register_graphql_object_type( 'Theme', [
	'description' => __( 'A theme object', 'wp-graphql' ),
	'interfaces'  => [ WPObjectType::node_interface() ],
	'fields'      => [
		'id'          => [
			'type'    => [
				'non_null' => 'ID'
			],
			'resolve' => function( \WP_Theme $theme, $args, $context, $info ) {
				$stylesheet = $theme->get_stylesheet();

				return ( ! empty( $info->parentType ) && ! empty( $stylesheet ) ) ? Relay::toGlobalId( 'theme', $stylesheet ) : null;
			},
		],
		'slug'        => [
			'type'        => 'String',
			'description' => __( 'The theme slug is used to internally match themes. Theme slugs can have subdirectories like: my-theme/sub-theme. This field is equivalent to WP_Theme->get_stylesheet().', 'wp-graphql' ),
			'resolve'     => function( \WP_Theme $theme, $args, $context, $info ) {
				$stylesheet = $theme->get_stylesheet();

				return ! empty( $stylesheet ) ? $stylesheet : null;
			},
		],
		'name'        => [
			'type'        => 'String',
			'description' => __( 'Display name of the theme. This field is equivalent to WP_Theme->get( "Name" ).', 'wp-graphql' ),
			'resolve'     => function( \WP_Theme $theme, $args, $context, $info ) {
				$name = $theme->get( 'Name' );

				return ! empty( $name ) ? $name : null;
			},
		],
		'screenshot'  => [
			'type'        => 'String',
			'description' => __( 'The URL of the screenshot for the theme. The screenshot is intended to give an overview of what the theme looks like. This field is equivalent to WP_Theme->get_screenshot().', 'wp-graphql' ),
			'resolve'     => function( \WP_Theme $theme, $args, $context, $info ) {
				$screenshot = $theme->get_screenshot();

				return ! empty( $screenshot ) ? $screenshot : null;
			},
		],
		'themeUri'    => [
			'type'        => 'String',
			'description' => __( 'A URI if the theme has a website associated with it. The Theme URI is handy for directing users to a theme site for support etc. This field is equivalent to WP_Theme->get( "ThemeURI" ).', 'wp-graphql' ),
			'resolve'     => function( \WP_Theme $theme, $args, $context, $info ) {
				$theme_uri = $theme->get( 'ThemeURI' );

				return ! empty( $theme_uri ) ? $theme_uri : null;
			},
		],
		'description' => [
			'type'        => 'String',
			'description' => __( 'The description of the theme. This field is equivalent to WP_Theme->get( "Description" ).', 'wp-graphql' ),
		],
		'author'      => [
			'type'        => 'String',
			'description' => __( 'Name of the theme author(s), could also be a company name. This field is equivalent to WP_Theme->get( "Author" ).', 'wp-graphql' ),
		],
		'authorUri'   => [
			'type'        => 'String',
			'description' => __( 'URI for the author/company website. This field is equivalent to WP_Theme->get( "AuthorURI" ).', 'wp-graphql' ),
			'resolve'     => function( \WP_Theme $theme, $args, $context, $info ) {
				$author_uri = $theme->get( 'AuthorURI' );

				return ! empty( $author_uri ) ? $author_uri : null;
			},
		],
		'tags'        => [
			'type'        => [
				'list_of' => 'String'
			],
			'description' => __( 'URI for the author/company website. This field is equivalent to WP_Theme->get( "Tags" ).', 'wp-graphql' ),
		],
		'version'     => [
			'type'        => 'Float',
			'description' => __( 'The current version of the theme. This field is equivalent to WP_Theme->get( "Version" ).', 'wp-graphql' ),
		],
	],

] );
