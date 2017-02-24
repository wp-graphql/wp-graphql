<?php
namespace WPGraphQL\Type\Avatar;

use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class AvatarType
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class AvatarType extends WPObjectType {

	/**
	 * Holds the type name
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * This holds the field definitions
	 * @var array $fields
	 * @since 0.0.5
	 */
	private static $fields;

	/**
	 * WPObjectType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		/**
		 * Set the type_name
		 * @since 0.0.5
		 */
		self::$type_name = 'avatar';

		$config = [
			'name' => self::$type_name,
			'fields' => self::fields(),
			'description' => esc_html__( 'Avatars are profile images for users. WordPress by default uses the Gravatar service to host and fetch avatars from.', 'wp-graphql' ),
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields for the AvatarType. The fields are passed through a filter so the shape of the schema
	 * can be modified
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 * @since 0.0.5
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = [
				'size' => [
					'type' => Types::int(),
					'description' => esc_html__( 'The size of the avatar in pixels. A value of 96 will match a 96px x 96px gravatar image.', 'wp-graphql' ),
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
					'description' => esc_html__( "URL for the default image or a default type. Accepts '404' (return a 404 instead of a default image), 'retro' (8bit), 'monsterid' (monster), 'wavatar' (cartoon face), 'indenticon' (the 'quilt'), 'mystery', 'mm', or 'mysteryman' (The Oyster Man), 'blank' (transparent GIF), or 'gravatar_default' (the Gravatar logo).", 'wp-graphql' ),
				],
				'force_default' => [
					'type' => Types::boolean(),
					'description' => esc_html__( 'Whether to always show the default image, never the 
						Gravatar.', 'wp-graphql' ),
				],
				'rating' => [
					'type' => Types::string(),
					'description' => esc_html__( "What rating to display avatars up to. Accepts 'G', 'PG', 'R', 'X', and are judged in that order.", 'wp-graphql' ),
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
		}

		/**
		 * Pass the fields through a filter to allow for hooking in and adjusting the shape
		 * of the type's schema
		 * @since 0.0.5
		 */
		return self::prepare_fields( self::$fields, self::$type_name );

	}
}
