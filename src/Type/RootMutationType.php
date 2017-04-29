<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Types;

/**
 * Class RootMutationType
 * The RootMutationType is the primary entry point for Mutations in the GraphQL Schema
 * @package WPGraphQL\Type
 * @since 0.0.8
 */
class RootMutationType extends WPObjectType {

	/**
	 * Holds the $fields definition for the PluginType
	 * @var $fields
	 * @since 0.0.8
	 */
	private static $fields;

	/**
	 * Holds the type name
	 * @var string $type_name
	 * @since 0.0.8
	 */
	private static $type_name;

	/**
	 * RootMutationType constructor.
	 * @since 0.0.8
	 */
	public function __construct() {

		self::$type_name = 'rootMutation';

		/**
		 * Configure the rootMutation
		 * @since 0.0.8
		 */
		$config = [
			'name' => self::$type_name,
			'description' => __( 'The root mutation', 'wp-graphql' ),
			'fields' => self::fields(),
		];

		/**
		 * Pass the config to the parent construct
		 * @since 0.0.8
		 */
		parent::__construct( $config );

	}

	/**
	 * This defines the fields for the RootMutationType. The fields are passed through a filter so the shape of the
	 * schema can be modified, for example to add entry points to Types that are unique to certain plugins.
	 *
	 * @return array|\GraphQL\Type\Definition\FieldDefinition[]
	 * @since 0.0.8
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			// @todo: Remove this once mutations are built out. This was added to get mutations going, so that
			// Authentication mutations could be added, but this will not be a long-term mutation that will remain
			self::$fields['hello'] = [
				'type' => Types::string(),
				'description' => esc_html__( 'Example mutation field', 'wp-graphql' ),
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {

					$payload = wp_json_encode( [
						'query' => 'mutation{createTransportEvent(wpAction:"demo",wpType:"helloWorld"){createdAt,wpType,wpAction}}',
						'variables' => null,
					] );

					$event_trigger = wp_remote_post( 'https://api.graph.cool/simple/v1/cj1xmyllis1hv0133ixhz3pmx', [
						'method' => 'POST',
						'blocking' => true,
						'body' => $payload,
						'headers' => [
							'Authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE0OTA4MDQ1MzYsImNsaWVudElkIjoiY2l6N3RzdDFlNnVlYjAxNDl2OHhtbm5nZiJ9.IYrJA-5GjjthFaabmaLj-4dz_vQb1CG5Xliclp2KGhc',
							'Content-Type' => 'application/json',
						],
					] );

//					var_dump( $event_trigger );


					return 'world';
				}
			];

		}

		/**
		 * Pass the fields through a filter to allow for hooking in and adjusting the shape
		 * of the type's schema
		 * @since @since 0.0.8
		 */
		return self::prepare_fields( self::$fields, self::$type_name );

	}

}
