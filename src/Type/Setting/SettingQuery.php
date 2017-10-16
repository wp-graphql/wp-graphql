<?php
namespace WPGraphQL\Type\Setting;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

/**
 * Class SettingQuery
 *
 * @package WPGraphQL\Type\Setting
 */
class SettingQuery {

	/**
	 * Holds the root_query field definition
	 * @var array $root_query
	 */
	private static $root_query;

	/**
	 * Method that returns the root query field definition for setting type
	 *
	 * @return array
	 */
	public static function root_query( $setting_type, $setting_type_array ) {

		if ( null === self::$root_query ) {
			self::$root_query = [];
		}

		if ( ! empty( $setting_type ) && empty( self::$root_query[ $setting_type ] ) ) {
			self::$root_query[ $setting_type ] = [
				'type'        => Types::setting( $setting_type ),
				'description' => sprintf( __( 'A %s setting type', 'wp-graphql' ), $setting_type ),
				'resolve'     => function ( $source, array $args, AppContext $context, ResolveInfo $info ) {
					return $setting_type_array;
				},
			];

		}

		return self::$root_query;
	}
}
