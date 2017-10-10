<?php
namespace WPGraphQL\Type\GeneralSetting\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

/**
 * Class GeneralSettingConnectionResolver - Connects all general settings
 *
 * @package WPGraphQL\Data\Resolvers
 */
class GeneralSettingConnectionResolver {

	/**
	 * Creates the connection for general settings
	 *
	 * @param mixed $source The query results
	 * @param array $args The query arguments
	 * @param AppContext $context The AppContext object
	 * @param ResolveInfo $info The ResolveInfo object
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Create an array with all of the general settings mapping
		 * the actual WP option to the field name
		 */
		$general_settings_array = [
			[
				'name' => __( 'adminEmail', 'wp-graphql' ),
				'value' => get_option( 'admin_email' ),
			],
			[
				'name' => __( 'siteDescription', 'wp-graphql' ),
				'value' => get_option( 'blogdescription' ),
			],
			[
				'name' => __( 'siteName', 'wp-graphql' ),
				'value' => get_option( 'blogname' ),
			],
			[
				'name' => __( 'commentRegistration', 'wp-graphql' ),
				'value' => get_option( 'comment_registration' ),
			],
			[
				'name' => __( 'dateFormat', 'wp-graphql' ),
				'value' => get_option( 'date_format' ),
			],
			[
				'name' => __( 'defaultRole', 'wp-graphql' ),
				'value' => get_option( 'default_role' ),
			],
			[
				'name' => __( 'gmtOffset', 'wp-graphql' ),
				'value' => get_option( 'gmt_offset' ),
			],
			[
				'name' => __( 'home', 'wp-graphql' ),
				'value' => get_option( 'home' ),
			],
			[
				'name' => __( 'siteUrl', 'wp-graphql' ),
				'value' => get_option( 'siteurl' ),
			],
			[
				'name' => __( 'startOfWeek', 'wp-graphql' ),
				'value' => get_option( 'start_of_week' ),
			],
			[
				'name' => __( 'timeFormat', 'wp-graphql' ),
				'value' => get_option( 'time_format' ),
			],
			[
				'name' => __( 'timezoneString', 'wp-graphql' ),
				'value' => get_option( 'timezone_string' ),
			],
			[
				'name' => __( 'usersCanRegister', 'wp-graphql' ),
				'value' => get_option( 'users_can_register' ),
			],
		];

		return ! empty( $general_settings_array ) ? Relay::connectionFromArray( $general_settings_array, $args ) : null;

	}
}