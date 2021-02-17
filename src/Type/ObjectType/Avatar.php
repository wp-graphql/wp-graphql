<?php

namespace WPGraphQL\Type\ObjectType;

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
				'description' => __( 'Avatars are profile images for users. WordPress by default uses the Gravatar service to host and fetch avatars from.', 'wp-graphql' ),
				'fields'      => [
					'size'         => [
						'type'        => 'Int',
						'description' => __( 'The size of the avatar in pixels. A value of 96 will match a 96px x 96px gravatar image.', 'wp-graphql' ),
					],
					'height'       => [
						'type'        => 'Int',
						'description' => __( 'Height of the avatar image.', 'wp-graphql' ),
					],
					'width'        => [
						'type'        => 'Int',
						'description' => __( 'Width of the avatar image.', 'wp-graphql' ),
					],
					'default'      => [
						'type'        => 'String',
						'description' => __( "URL for the default image or a default type. Accepts '404' (return a 404 instead of a default image), 'retro' (8bit), 'monsterid' (monster), 'wavatar' (cartoon face), 'indenticon' (the 'quilt'), 'mystery', 'mm', or 'mysteryman' (The Oyster Man), 'blank' (transparent GIF), or 'gravatar_default' (the Gravatar logo).", 'wp-graphql' ),
					],
					'forceDefault' => [
						'type'        => 'Bool',
						'description' => __( 'Whether to always show the default image, never the Gravatar.', 'wp-graphql' ),
					],
					'rating'       => [
						'type'        => 'String',
						'description' => __( "What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are judged in that order.", 'wp-graphql' ),
					],
					'scheme'       => [
						'type'        => 'String',
						'description' => __( 'Type of url scheme to use. Typically HTTP vs. HTTPS.', 'wp-graphql' ),
					],
					'extraAttr'    => [
						'type'        => 'String',
						'description' => __( 'HTML attributes to insert in the IMG element. Is not sanitized.', 'wp-graphql' ),
					],
					'foundAvatar'  => [
						'type'        => 'Bool',
						'description' => __( 'Whether the avatar was successfully found.', 'wp-graphql' ),
					],
					'url'          => [
						'type'        => 'String',
						'description' => __( 'URL for the gravatar image source.', 'wp-graphql' ),
					],
					'isRestricted' => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
