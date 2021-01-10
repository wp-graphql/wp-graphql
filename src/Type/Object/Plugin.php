<?php

namespace WPGraphQL\Type\Object;

/**
 * Class Plugin
 *
 * @package WPGraphQL\Type\Object
 */
class Plugin {

	/**
	 * Registers the Plugin Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'Plugin',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'An plugin object', 'wp-graphql' ),
				'fields'      => [
					'id'           => [
						'description' => __( 'The globally unique identifier of the plugin object.', 'wp-graphql' ),
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
					'path'         => [
						'type'        => 'String',
						'description' => __( 'Plugin path.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
