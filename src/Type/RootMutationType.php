<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Type\PostObject\Mutation\PostObjectCreate;
use WPGraphQL\Types;

/**
 * Class RootMutationType
 * The RootMutationType is the primary entry point for Mutations in the GraphQL Schema
 *
 * @package WPGraphQL\Type
 * @since   0.0.8
 */
class RootMutationType extends WPObjectType {

	/**
	 * Holds the $fields definition for the PluginType
	 *
	 * @var $fields
	 */
	private static $fields;

	/**
	 * Holds the type name
	 *
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * RootMutationType constructor.
	 */
	public function __construct() {

		self::$type_name = 'rootMutation';

		/**
		 * Configure the rootMutation
		 */
		$config = [
			'name'        => self::$type_name,
			'description' => __( 'The root mutation', 'wp-graphql' ),
			'fields'      => self::fields(),
		];

		/**
		 * Pass the config to the parent construct
		 */
		parent::__construct( $config );

	}

	/**
	 * This defines the fields for the RootMutationType. The fields are passed through a filter so the shape of the
	 * schema can be modified, for example to add entry points to Types that are unique to certain plugins.
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			$fields             = [];
			$allowed_post_types = \WPGraphQL::$allowed_post_types;
			$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

			if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
				foreach ( $allowed_post_types as $post_type ) {
					/**
					 * Get the post_type object to pass down to the schema
					 *
					 * @since 0.0.5
					 */
					$post_type_object = get_post_type_object( $post_type );

					/**
					 * Root mutation for single posts (of the specified post_type)
					 *
					 * @since 0.0.5
					 */
					$fields[ 'create' . ucwords( $post_type_object->graphql_single_name ) ] = PostObjectCreate::mutate( $post_type_object );

				} // End foreach().
			} // End if().
			
			self::$fields = $fields;

		} // End if().

		/**
		 * Pass the fields through a filter to allow for hooking in and adjusting the shape
		 * of the type's schema
		 */
		return self::prepare_fields( self::$fields, self::$type_name );

	}

}
