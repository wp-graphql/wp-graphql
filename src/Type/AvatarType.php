<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use WPGraphQL\Types;

class AvatarType extends ObjectType {

	public function __construct() {

		$config = [
			'name' => 'avatar',
			'fields' => function() {
				$fields = [
					'size' => [
						'type' => Types::int(),
						'description' => esc_html__( 'The size of the avatar in pixels. A value of 96 will match a 
						96px x 96px gravatar image.', 'wp-graphql' ),
					],
					'height' => [
						'type' => Types::int(),
						'description' => esc_html__( 'Height of the avatar image.', 'wp-graphql' ),
					],
					'width' => [
						'type' => Types::int(),
						'description' => esc_html__( 'Width of the avatar image.', 'wp-graphql' ),
					],
					'default' => [
						'type' => Types::string(),
						'description' => esc_html__( "URL for the default image or a default type. Accepts '404' 
						(return a 404 instead of a default image), 'retro' (8bit), 'monsterid' (monster), 'wavatar' 
						(cartoon face), 'indenticon' (the 'quilt'), 'mystery', 'mm', or 'mysteryman' (The Oyster Man), 
						'blank' (transparent GIF), or 'gravatar_default' (the Gravatar logo).", 'wp-graphql' ),
					],
					'force_default' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether to always show the default image, never the 
						Gravatar.', 'wp-graphql' ),
					],
					'rating' => [
						'type' => Types::string(),
						'description' => esc_html__( "What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', 
						and are judged in that order.", 'wp-graphql' ),
					],
					'scheme' => [
						'type' => Types::string(),
						'description' => esc_html__( 'Type of url scheme to use. Typically HTTP vs. 
						HTTPS.', 'wp-graphql' ),
					],
					'extra_attr' => [
						'type' => Types::string(),
						'description' => esc_html__( 'HTML attributes to insert in the IMG element. Is not 
						sanitized.', 'wp-graphql' ),
					],
					'found_avatar' => [
						'type' => Types::boolean(),
						'description' => esc_html__( 'Whether the avatar was successfully found.', 'wp-graphql' ),
					],
					'url' => [
						'type' => Types::string(),
						'description' => esc_html__( 'URL for the gravatar image source.', 'wp-graphql' ),
					],
				];

				/**
				 * Pass the fields through a filter
				 *
				 * @param array $fields
				 *
				 * @since 0.0.5
				 */
				$fields = apply_filters( 'graphql_avatar_type_fields', $fields );

				/**
				 * Sort the fields alphabetically by key. This makes reading through docs much easier
				 * @since 0.0.2
				 */
				ksort( $fields );

				return $fields;
			},
			'description' => esc_html__( 'Avatars are profile images for users. WordPress by default uses the Gravatar 
			service to host and fetch avatars from.', 'wp-graphql' ),
		];

		parent::__construct( $config );

	}
}