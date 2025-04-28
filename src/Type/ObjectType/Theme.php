<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Model\Theme as ThemeModel;

/**
 * Class Theme
 *
 * @package WPGraphQL\Type\Object
 */
class Theme {

	/**
	 * Register the Theme Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'Theme',
			[
				'description' => static function () {
					return __( 'A theme object', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node' ],
				'model'       => ThemeModel::class,
				'fields'      => static function () {
					return [
						'id'           => [
							'description' => static function () {
								return __( 'The globally unique identifier of the theme object.', 'wp-graphql' );
							},
						],
						'slug'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The theme slug is used to internally match themes. Theme slugs can have subdirectories like: my-theme/sub-theme. This field is equivalent to WP_Theme->get_stylesheet().', 'wp-graphql' );
							},
						],
						'name'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Display name of the theme. This field is equivalent to WP_Theme->get( "Name" ).', 'wp-graphql' );
							},
						],
						'screenshot'   => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The URL of the screenshot for the theme. The screenshot is intended to give an overview of what the theme looks like. This field is equivalent to WP_Theme->get_screenshot().', 'wp-graphql' );
							},
						],
						'themeUri'     => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'A URI if the theme has a website associated with it. The Theme URI is handy for directing users to a theme site for support etc. This field is equivalent to WP_Theme->get( "ThemeURI" ).', 'wp-graphql' );
							},
						],
						'description'  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The description of the theme. This field is equivalent to WP_Theme->get( "Description" ).', 'wp-graphql' );
							},
						],
						'author'       => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Name of the theme author(s), could also be a company name. This field is equivalent to WP_Theme->get( "Author" ).', 'wp-graphql' );
							},
						],
						'authorUri'    => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'URI for the author/company website. This field is equivalent to WP_Theme->get( "AuthorURI" ).', 'wp-graphql' );
							},
						],
						'tags'         => [
							'type'        => [
								'list_of' => 'String',
							],
							'description' => static function () {
								return __( 'URI for the author/company website. This field is equivalent to WP_Theme->get( "Tags" ).', 'wp-graphql' );
							},
						],
						'version'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The current version of the theme. This field is equivalent to WP_Theme->get( "Version" ).', 'wp-graphql' );
							},
						],
						'isRestricted' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
