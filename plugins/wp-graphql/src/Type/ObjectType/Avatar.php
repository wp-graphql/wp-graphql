<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Model\Avatar as AvatarModel;

class Avatar {

	/**
	 * Register the Avatar Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'Avatar',
			[
				'description' => static function () {
					return __( 'Avatars are profile images for users. WordPress by default uses the Gravatar service to host and fetch avatars from.', 'wp-graphql' );
				},
				'model'       => AvatarModel::class,
				'fields'      => static function () {
					return [
						'size'         => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The size of the avatar in pixels. A value of 96 will match a 96px x 96px gravatar image.', 'wp-graphql' );
							},
						],
						'height'       => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Height of the avatar image.', 'wp-graphql' );
							},
						],
						'width'        => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Width of the avatar image.', 'wp-graphql' );
							},
						],
						'default'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( "TEST: URL for the default image or a default type. Accepts '404' (return a 404 instead of a default image), 'retro' (8bit), 'monsterid' (monster), 'wavatar' (cartoon face), 'indenticon' (the 'quilt'), 'mystery', 'mm', or 'mysteryman' (The Oyster Man), 'blank' (transparent GIF), or 'gravatar_default' (the Gravatar logo).", 'wp-graphql' );
							},
						],
						'forceDefault' => [
							'type'        => 'Bool',
							'description' => static function () {
								return __( 'Whether to always show the default image, never the Gravatar.', 'wp-graphql' );
							},
						],
						'rating'       => [
							'type'        => 'String',
							'description' => static function () {
								return __( "What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are judged in that order.", 'wp-graphql' );
							},
						],
						'scheme'       => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Type of url scheme to use. Typically HTTP vs. HTTPS.', 'wp-graphql' );
							},
						],
						'extraAttr'    => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'HTML attributes to insert in the IMG element. Is not sanitized.', 'wp-graphql' );
							},
						],
						'foundAvatar'  => [
							'type'        => 'Bool',
							'description' => static function () {
								return __( 'Whether the avatar was successfully found.', 'wp-graphql' );
							},
						],
						'url'          => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'URL for the gravatar image source.', 'wp-graphql' );
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
