<?php
namespace WPGraphQL\Type\GeneralSetting;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

/**
 * Class GeneralSettingQuery
 *
 * @package WPGraphQL\Type\PostObject
 */
class GeneralSettingQuery {

	/**
	 * Holds the root_query field definition
	 * @var array $root_query
	 */
	private static $root_query;

	/**
	 * Method that returns the root query field definition for the general settings type
	 *
	 * @return array
	 */
	public static function root_query() {

		if ( null === self::$root_query ) :
			self::$root_query = [
				'type' => Types::general_setting(),
				'description' => __( 'Returns general settings.', 'wp-graphql' ),
				'args' => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );
					return DataSource::resolve_general_setting( $id_components['id'] );
				},
			];
		endif;
		return self::$root_query;
	}

}
