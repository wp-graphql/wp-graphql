<?php
namespace WPGraphQL\Type\GeneralSetting\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class GeneralSettingsConnectionDefinition
 * @package WPGraphQL\Type\GeneralSetting\Connection
 */
class GeneralSettingConnectionDefinition {

	/**
	 * @var array connection
	 * @access private
	 */
	private static $connection;

	/**
	 * This sets up a connection of general settings
	 *
	 * @return mixed
	 * @access public
	 */
	public static function connection() {

		if ( null === self::$connection ) {

			/**
			 * Setup the connectionDefinition
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::general_setting(),
				'name' => 'generalSettings',
			] );

			/**
			 * Add the connection to the general_setting_connection object
			 */
			self::$connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of general settings.', 'wp-graphql' ),
				'args' => Relay::connectionArgs(),
				'resolve' => function( $source, $args, AppContext $context, ResolveInfo $info ) {
					return DataSource::resolve_general_settings_connection( $source, $args, $context, $info );
				},
			];
		}

		return self::$connection;

	}

}
