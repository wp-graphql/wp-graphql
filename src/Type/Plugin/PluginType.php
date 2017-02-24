<?php
namespace WPGraphQL\Type\Plugin;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class PluginType
 *
 * This sets up the PluginType schema.
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class PluginType extends WPObjectType {

	/**
	 * Holds the type name
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * Holds the $fields definition for the PluginType
	 * @var $fields
	 */
	private static $fields;

	/**
	 * PluginType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		/**
		 * Set the type_name
		 * @since 0.0.5
		 */
		self::$type_name = 'plugin';

		$config = [
			'name' => self::$type_name,
			'description' => __( 'An plugin object', 'wp-graphql' ),
			'fields' => self::fields(),
			'interfaces' => [ self::node_interface() ],
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields for the PluginType. The fields are passed through a filter so the shape of the schema
	 * can be modified, for example to add entry points to Types that are unique to certain plugins.
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 * @since 0.0.5
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = [
				'id' => [
					'type' => Types::non_null( Types::id() ),
					'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
						return ( ! empty( $plugin ) && ! empty( $plugin['Name'] ) ) ? Relay::toGlobalId( 'plugin', $plugin['Name'] ) : null;
					},
				],
				'name' => [
					'type' => Types::string(),
					'description' => esc_html__( 'Display name of the plugin.', 'wp-graphql' ),
					'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
						return ! empty( $plugin['Name'] ) ? $plugin['Name'] : '';
					},
				],
				'pluginUri' => [
					'type' => Types::string(),
					'description' => esc_html__( 'URI for the plugin website. This is useful for directing users for support requests etc.', 'wp-graphql' ),
					'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
						return ! empty( $plugin['PluginURI'] ) ? $plugin['PluginURI'] : '';
					},
				],
				'description' => [
					'type' => Types::string(),
					'description' => esc_html__( 'Description of the plugin.', 'wp-graphql' ),
					'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
						return ! empty( $plugin['Description'] ) ? $plugin['Description'] : '';
					},
				],
				'author' => [
					'type' => Types::string(),
					'description' => esc_html__( 'Name of the plugin author(s), may also be a company name.', 'wp-graphql' ),
					'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
						return ! empty( $plugin['Author'] ) ? $plugin['Author'] : '';
					},
				],
				'authorUri' => [
					'type' => Types::string(),
					'description' => esc_html__( 'URI for the related author(s)/company website.', 'wp-graphql' ),
					'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
						return ! empty( $plugin['AuthorURI'] ) ? $plugin['AuthorURI'] : '';
					},
				],
				'version' => [
					'type' => Types::string(),
					'description' => esc_html__( 'Current version of the plugin.', 'wp-graphql' ),
					'resolve' => function( array $plugin, $args, $context, ResolveInfo $info ) {
						return ! empty( $plugin['Version'] ) ? $plugin['Version'] : '';
					},
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
