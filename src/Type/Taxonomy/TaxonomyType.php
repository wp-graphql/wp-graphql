<?php
namespace WPGraphQL\Type\Taxonomy;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class TaxonomyType
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class TaxonomyType extends WPObjectType {

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
	 * TaxonomyType constructor.
	 * @since 0.0.5
	 */
	public function __construct() {

		/**
		 * Set the type_name
		 * @since 0.0.5
		 */
		self::$type_name = 'taxonomy';

		$config = [
			'name' => self::$type_name,
			'description' => __( 'A taxonomy object', 'wp-graphql' ),
			'fields' => self::fields(),
			'interfaces' => [ self::node_interface() ],
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields for the TaxonomyType. The fields are passed through a filter so the shape of the schema
	 * can be modified
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 * @since 0.0.5
	 */
	private function fields() {

		if ( null === self::$fields ) {

			self::$fields = [
				'id' => [
					'type' => Types::non_null( Types::id() ),
					'resolve' => function( $taxonomy, $args, $context, ResolveInfo $info ) {
						return ( ! empty( $info->parentType ) && ! empty( $taxonomy->name ) ) ? Relay::toGlobalId( $info->parentType, $taxonomy->name ) : null;
					},
				],
				'name' => [
					'type' => Types::string(),
					'description' => esc_html__( 'The display name of the taxonomy. This field is equivalent to WP_Taxonomy->label', 'wp-graphql' ),
				],
				'slug' => [
					'type' => Types::string(),
					'description' => esc_html__( 'The url friendly name of the taxonomy. This field is equivalent to WP_Taxonomy->name', 'wp-graphql' ),
				],
				'description' => [
					'type' => Types::string(),
					'description' => esc_html__( 'Description of the taxonomy. This field is equivalent to WP_Taxonomy->description', 'wp-graphql' ),
				],
				'show_cloud' => [
					'type' => Types::boolean(),
					'description' => esc_html__( 'Whether to show the taxonomy as part of a tag cloud widget. This field is equivalent to WP_Taxonomy->show_tagcloud', 'wp-graphql' ),
				],
				'hierarchical' => [
					'type' => Types::string(),
					'description' => esc_html__( 'Whether the taxonomy is hierarchical. This field is equivalent to WP_Taxonomy->hierarchical', 'wp-graphql' ),
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
