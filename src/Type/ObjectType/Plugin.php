<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Model\Plugin as PluginModel;

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
				'model'       => PluginModel::class,
				'description' => static function () {
					return __( 'An plugin object', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'id'           => [
							'description' => static function () {
								return __( 'The globally unique identifier of the plugin object.', 'wp-graphql' );
							},
						],
						'name'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Display name of the plugin.', 'wp-graphql' );
							},
						],
						'pluginUri'    => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'URI for the plugin website. This is useful for directing users for support requests etc.', 'wp-graphql' );
							},
						],
						'description'  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Description of the plugin.', 'wp-graphql' );
							},
						],
						'author'       => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Name of the plugin author(s), may also be a company name.', 'wp-graphql' );
							},
						],
						'authorUri'    => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'URI for the related author(s)/company website.', 'wp-graphql' );
							},
						],
						'version'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Current version of the plugin.', 'wp-graphql' );
							},
						],
						'isRestricted' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
						'path'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Plugin path.', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
