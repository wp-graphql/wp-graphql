<?php

namespace WPGraphQL\Type;

register_graphql_object_type(
	'Plugin',
	[
		'description' => __( 'An plugin object', 'wp-graphql' ),
		'fields'      => [
			'id'           => [
				'type' => [
					'non_null' => 'ID',
				],
			],
			'name'         => [
				'type'        => 'String',
				'description' => __( 'Display name of the plugin.', 'wp-graphql' ),
			],
			'pluginUri'    => [
				'type'        => 'String',
				'description' => __( 'URI for the plugin website. This is useful for directing users for support requests etc.', 'wp-graphql' ),
			],
			'description'  => [
				'type'        => 'String',
				'description' => __( 'Description of the plugin.', 'wp-graphql' ),
			],
			'author'       => [
				'type'        => 'String',
				'description' => __( 'Name of the plugin author(s), may also be a company name.', 'wp-graphql' ),
			],
			'authorUri'    => [
				'type'        => 'String',
				'description' => __( 'URI for the related author(s)/company website.', 'wp-graphql' ),
			],
			'version'      => [
				'type'        => 'String',
				'description' => __( 'Current version of the plugin.', 'wp-graphql' ),
			],
			'isRestricted' => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
			],
		],
		'interfaces'  => [ WPObjectType::node_interface() ],
	]
);
