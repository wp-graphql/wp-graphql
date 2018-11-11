<?php 
namespace WPGraphQL\Type;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

register_graphql_object_type('Sidebar', [
	'description' =>  __( 'A sidebar object', 'wp-graphql' ),
	'interfaces' =>   [ WPObjectType::node_interface() ],
	'fields' =>       [
		'id'          => [
			'type'    => [ 'non_null' => 'ID' ],
			'resolve' => function( array $sidebar, $args, $context, $info ) {
				return ( ! empty( $sidebar ) && ! empty( $sidebar['id'] ) ) ?
					Relay::toGlobalId( 'sidebar', $sidebar['id'] ) :
					null;
			},
		],
		'sidebarId'   => [
			'type'        => 'String',
			'description' => __( 'WP ID of the sidebar.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['id'] ) ? $sidebar['id'] : null;
			},
		],
		'name'        => [
			'type'        => 'String',
			'description' => __( 'Display name of the sidebar.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['name'] ) ? $sidebar['name'] : null;
			},
		],
		'description' => [
			'type'        => 'String',
			'description' => __( 'Description of the sidebar.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['description'] ) ? $sidebar['description'] : null;
			},
		],
		'class'        => [
			'type'        => 'String',
			'description' => __( 'HTML class attribute of the sidebar.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['class'] ) ? $sidebar['class'] : null;
			},
		],
		'beforeWidget'        => [
			'type'        => 'String',
			'description' => __( 'HTML Tags used before each widget in sidebar.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['before_widget'] ) ? $sidebar['before_widget'] : null;
			},
		],
		'afterWidget'        => [
			'type'        => 'String',
			'description' => __( 'HTML Tags used after each widget in sidebar.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['after_widget'] ) ? $sidebar['after_widget'] : null;
			},
		],
		'beforeTitle'        => [
			'type'        => 'String',
			'description' => __( 'HTML Tags used before sidebar title display.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['before_title'] ) ? $sidebar['before_title'] : null;
			},
		],
		'afterTitle'        => [
			'type'        => 'String',
			'description' => __( 'HTML Tags used after sidebar title display.', 'wp-graphql' ),
			'resolve'     => function( array $sidebar, $args, $context, $info ) {
				return ! empty( $sidebar['after_title'] ) ? $sidebar['after_title'] : null;
			},
		],
	]
] );

register_graphql_field( 'RootQuery', 'sidebar', [
	'type' => 'Sidebar',
	'description' => __( 'A WordPress sidebar', 'wp-graphql' ),
	'args' => [
		'id' => [ 'type' => ['non_null' => 'ID'] ],
	],
	'resolve' => function( $source, $args, $context, $info ) {
		$id_components = Relay::fromGlobalId( $args['id'] );

		return DataSource::resolve_sidebar( $id_components['id'] );
	},
] );

register_graphql_field( 'RootQuery', 'sidebarBy', [
	'type' => 'Sidebar',
	'description' => __( 'Retrieves WordPress Sidebar object by name or ID', 'wp-graphql' ),
	'args' => [
		'id' 		=> [ 'type' => 'String' ],
		'name' 	=> [ 'type' => 'String' ],
	],
	'resolve' => function( $source, array $args, $context, $info ) {
		$sidebar = null;

		if( ! empty( $args[ 'id' ] ) ) {
			$sidebar = DataSource::resolve_sidebar( $args[ 'id' ] );
		}
		if ( ! empty( $args[ 'name' ] ) ) {
			$sidebar = DataSource::resolve_sidebar( $args[ 'name' ], 'name' );
		}

		return $sidebar;
	},
] );