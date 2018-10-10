<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;

register_graphql_object_type( 'Plugin', [
	'description' => __( 'An plugin object', 'wp-graphql' ),
	'fields'      => [
		'id'          => [
			'type'    => [
				'non_null' => 'ID'
			],
			'resolve' => function( array $plugin, $args, $context, $info ) {
				return ( ! empty( $plugin ) && ! empty( $plugin['Name'] ) ) ? Relay::toGlobalId( 'plugin', $plugin['Name'] ) : null;
			},
		],
		'name'        => [
			'type'        => 'String',
			'description' => __( 'Display name of the plugin.', 'wp-graphql' ),
			'resolve'     => function( array $plugin, $args, $context, $info ) {
				return ! empty( $plugin['Name'] ) ? $plugin['Name'] : '';
			},
		],
		'pluginUri'   => [
			'type'        => 'String',
			'description' => __( 'URI for the plugin website. This is useful for directing users for support requests etc.', 'wp-graphql' ),
			'resolve'     => function( array $plugin, $args, $context, $info ) {
				return ! empty( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '';
			},
		],
		'description' => [
			'type'        => 'String',
			'description' => __( 'Description of the plugin.', 'wp-graphql' ),
			'resolve'     => function( array $plugin, $args, $context, $info ) {
				return ! empty( $plugin['Description'] ) ? $plugin['Description'] : '';
			},
		],
		'author'      => [
			'type'        => 'String',
			'description' => __( 'Name of the plugin author(s), may also be a company name.', 'wp-graphql' ),
			'resolve'     => function( array $plugin, $args, $context, $info ) {
				return ! empty( $plugin['Author'] ) ? $plugin['Author'] : '';
			},
		],
		'authorUri'   => [
			'type'        => 'String',
			'description' => __( 'URI for the related author(s)/company website.', 'wp-graphql' ),
			'resolve'     => function( array $plugin, $args, $context, $info ) {
				return ! empty( $plugin['AuthorURI'] ) ? $plugin['AuthorURI'] : '';
			},
		],
		'version'     => [
			'type'        => 'String',
			'description' => __( 'Current version of the plugin.', 'wp-graphql' ),
			'resolve'     => function( array $plugin, $args, $context, $info ) {
				return ! empty( $plugin['Version'] ) ? $plugin['Version'] : '';
			},
		],
	],
	'interfaces'  => [ WPObjectType::node_interface() ],
] );
