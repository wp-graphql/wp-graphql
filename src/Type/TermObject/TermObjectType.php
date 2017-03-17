<?php
namespace WPGraphQL\Type\TermObject;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class TermObjectType
 *
 * This sets up the base TermObjectType. Custom taxonomies that are set to "show_in_graphql" will automatically
 * use the TermObjectType and inherit the fields that are defined here. The fields get passed through a
 * filter unique to each taxonomy, so each taxonomy can modify it's term schema via field filters.
 *
 * NOTE: In some cases, (probably rare, I would guess) the "shape" of a custom taxonomy term might not make sense to
 * inherit the fields defined here, so it might make sense for a Taxonomy to register it's own custom defined type
 * for it's terms instead of utilizing the TermObjectType.
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class TermObjectType extends WPObjectType {

	/**
	 * Holds the $fields definition for the TermObjectType
	 * @var $fields
	 */
	private static $fields;

	/**
	 * Holds the $taxonomy_object
	 * @var $taxonomy_object
	 */
	private static $taxonomy_object;

	/**
	 * TermObjectType constructor.
	 *
	 * @param array $taxonomy
	 *
	 * @since 0.0.5
	 */
	public function __construct( $taxonomy ) {

		/**
		 * Get the taxonomy object and store it
		 * @since 0.0.5
		 */
		self::$taxonomy_object = get_taxonomy( $taxonomy );

		$config = [
			'name' => self::$taxonomy_object->graphql_single_name,
			'description' => sprintf( __( 'The % object type', 'wp-graphql' ), self::$taxonomy_object->graphql_single_name ),
			'fields' => self::fields( self::$taxonomy_object ),
			'interfaces' => [ self::node_interface() ],
		];
		parent::__construct( $config );
	}

	/**
	 * fields
	 *
	 * This defines the fields for TermObjectType
	 *
	 * @param $taxonomy_object
	 * @return \GraphQL\Type\Definition\FieldDefinition|mixed|null
	 * @since 0.0.5
	 */
	private static function fields( $taxonomy_object ) {

		/**
		 * Get the $single_name out of the taxonomy_object
		 * @since 0.0.5
		 */
		$single_name = self::$taxonomy_object->graphql_single_name;

		/**
		 * If no fields have been defined for this type already,
		 * make sure the $fields var is an empty array
		 * @since 0.0.5
		 */
		if ( null === self::$fields ) {
			self::$fields = [];
		}

		/**
		 * If the $fields haven't already been defined for this type,
		 * define the fields
		 * @since 0.0.5
		 */
		if ( empty( self::$fields[ $single_name ] ) ) {

			/**
			 * Get the post_types and taxonomies that are allowed
			 * in WPGraphQL
			 * @since 0.0.5
			 */
			$allowed_post_types = \WPGraphQL::$allowed_post_types;

			/**
			 * Define the fields for the terms of the specified taxonomy
			 * @return mixed
			 * @since 0.0.5
			 */
			self::$fields[ $single_name ] = function() use ( $single_name, $taxonomy_object, $allowed_post_types ) {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( \WP_Term $term, $args, $context, ResolveInfo $info ) {
							return ( ! empty( $term->taxonomy ) && ! empty( $term->term_id ) ) ? Relay::toGlobalId( $term->taxonomy, $term->term_id ) : null;
						},
					],
					$single_name . 'Id' => [
						'type' => Types::int(),
						'description' => esc_html__( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, $args, $context, ResolveInfo $info ) {
							return ! empty( $term->term_id ) ? absint( $term->term_id ) : null;
						},
					],
					'count' => [
						'type' => Types::int(),
						'description' => __( 'The number of objects connected to the object', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->count ) ? absint( $term->count ) : null;
						},
					],
					'description' => [
						'type' => Types::string(),
						'description' => __( 'The description of the object', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->description ) ? $term->description : null;
						},
					],
					'name' => [
						'type' => Types::string(),
						'description' => __( 'The human friendly name of the object.', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->name ) ? $term->name : null;
						},
					],
					'slug' => [
						'type' => Types::string(),
						'description' => __( 'An alphanumeric identifier for the object unique to its type.', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->slug ) ? $term->slug : null;
						},
					],
					'termGroupId' => [
						'type' => Types::int(),
						'description' => __( 'The ID of the term group that this term object belongs to', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->term_group ) ? absint( $term->term_group ) : null;
						},
					],
					'termTaxonomyId' => [
						'type' => Types::int(),
						'description' => __( 'The taxonomy ID that the object is associated with', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->term_taxonomy_id ) ? absint( $term->term_taxonomy_id ) : null;
						},
					],
					'taxonomyName' => [
						'type' => Types::string(),
						'description' => __( 'The name of the taxonomy this term belongs to', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, array $args, $context, ResolveInfo $info ) {
							return ! empty( $term->taxonomy ) ? $term->taxonomy : null;
						},
					],
					'link' => [
						'type' => Types::string(),
						'description' => __( 'The link to the term', 'wp-graphql' ),
						'resolve' => function( \WP_Term $term, $args, $context, ResolveInfo $info ) {
							$link = get_term_link( $term->term_id );

							return ( ! is_wp_error( $link ) ) ? $link : null;
						},
					],
				];

				/**
				 * Add connections for post_types that are registered to the taxonomy
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
					foreach ( $allowed_post_types as $post_type ) {
						$post_type_object = get_post_type_object( $post_type );
						$fields[ $post_type_object->graphql_plural_name ] = PostObjectConnectionDefinition::connection( $post_type_object );
					}
				}

				/**
				 * This prepares the fields by sorting them and applying a filter for adjusting the schema.
				 * Because these fields are implemented via a closure the prepare_fields needs to be applied
				 * to the fields directly instead of being applied to all objects extending
				 * the WPObjectType class.
				 * @since 0.0.5
				 */
				return self::prepare_fields( $fields, $single_name );

			};

		}

		return ! empty( self::$fields[ $single_name ] ) ? self::$fields[ $single_name ] : null;

	}
}
