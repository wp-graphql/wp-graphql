<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;


/**
 * Class Users
 *
 * This class organizes the registration of connections to Users
 *
 * @package WPGraphQL\Connection
 */
class Users {

	/**
	 * Register connections to Users
	 *
	 * @access public
	 */
	public static function register_connections() {

		/**
		 * Connection from RootQuery to Users
		 */
		register_graphql_connection(
			[
				'fromType'       => 'RootQuery',
				'toType'         => 'User',
				'fromFieldName'  => 'users',
				'resolveNode'    => function( $id, $args, $context, $info ) {
					return DataSource::resolve_user( $id, $context );
				},
				'resolve'        => function ( $source, $args, $context, $info ) {
					return DataSource::resolve_users_connection( $source, $args, $context, $info );
				},
				'connectionArgs' => self::get_connection_args(),
			]
		);

	}

	/**
	 * Returns the connection args for use in the connection
	 *
	 * @return array
	 * @access public
	 */
	public static function get_connection_args() {
		return [
			'role'              => [
				'type'        => 'UserRoleEnum',
				'description' => __( 'An array of role names that users must match to be included in results. Note that this is an inclusive list: users must match *each* role.', 'wp-graphql' ),
			],
			'roleIn'            => [
				'type'        => [
					'list_of' => 'UserRoleEnum',
				],
				'description' => __( 'An array of role names. Matched users must have at least one of these roles.', 'wp-graphql' ),
			],
			'roleNotIn'         => [
				'type'        => [
					'list_of' => 'UserRoleEnum',
				],
				'description' => __( 'An array of role names to exclude. Users matching one or more of these roles will not be included in results.', 'wp-graphql' ),
			],
			'include'           => [
				'type'        => [
					'list_of' => 'Int',
				],
				'description' => __( 'Array of userIds to include.', 'wp-graphql' ),
			],
			'exclude'           => [
				'type'        => [
					'list_of' => 'Int',
				],
				'description' => __( 'Array of userIds to exclude.', 'wp-graphql' ),
			],
			'search'            => [
				'type'        => 'String',
				'description' => __( 'Search keyword. Searches for possible string matches on columns. When "searchColumns" is left empty, it tries to determine which column to search in based on search string.', 'wp-graphql' ),
			],
			'searchColumns'     => [
				'type'        => [
					'list_of' => 'UsersConnectionSearchColumnEnum',
				],
				'description' => __( 'Array of column names to be searched. Accepts \'ID\', \'login\', \'nicename\', \'email\', \'url\'.', 'wp-graphql' ),
			],
			'hasPublishedPosts' => [
				'type'        => [
					'list_of' => 'PostTypeEnum',
				],
				'description' => __( 'Pass an array of post types to filter results to users who have published posts in those post types.', 'wp-graphql' ),
			],
			'nicename'          => [
				'type'        => 'String',
				'description' => __( 'The user nicename.', 'wp-graphql' ),
			],
			'nicenameIn'        => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'An array of nicenames to include. Users matching one of these nicenames will be included in results.', 'wp-graphql' ),
			],
			'nicenameNotIn'     => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'An array of nicenames to exclude. Users matching one of these nicenames will not be included in results.', 'wp-graphql' ),
			],
			'login'             => [
				'type'        => 'String',
				'description' => __( 'The user login.', 'wp-graphql' ),
			],
			'loginIn'           => [
				'type'        => 'Int',
				'description' => __( 'An array of logins to include. Users matching one of these logins will be included in results.', 'wp-graphql' ),
			],
			'loginNotIn'        => [
				'type'        => 'Int',
				'description' => __( 'An array of logins to exclude. Users matching one of these logins will not be included in results.', 'wp-graphql' ),
			],
			'orderby'           => [
				'type'        => [
					'list_of' => 'UsersConnectionOrderbyInput',
				],
				'description' => __( 'What paramater to use to order the objects by.', 'wp-graphql' ),
			],
		];
	}

}
