<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InputObjectType;
use WPGraphQL\Types;

class UserConnectionQueryArgsType extends InputObjectType {

	public function __construct() {

		$config = [
			'name' => 'userArgs',
			'fields' => function() {
				return [
//					'role' => [
//						// @todo: enum roles
//						'type' => Types::list_of( Types::string() ),
//						'description' => __( 'An array of role names that users must match to be included in results. Note that this is an inclusive list: users must match *each* role.', 'wp-graphql' ),
//					],
//					'roleIn' => [
//						// @todo: enum roles
//						'type' => Types::list_of( Types::string() ),
//						'description' => __( 'An array of role names. Matched users must have at least one of these roles.', 'wp-graphql' ),
//					],
//					'roleNotIn' => [
//						// @todo: enum roles
//						'type' => Types::list_of( Types::string() ),
//						'description' => __( 'An array of role names to exclude. Users matching one or more of these roles will not be included in results.', 'wp-graphql' ),
//					],
					'include' => [
						'type' => Types::list_of( Types::int() ),
						'description' => __( 'Array of comment IDs to include.', 'wp-graphql' ),
					],
					'exclude' => [
						'type' => Types::list_of( Types::int() ),
						'description' => __( 'Array of IDs of users whose unapproved comments will be returned by the query regardless of status.', 'wp-graphql' ),
					],
					'search' => [
						'type' => Types::string(),
						'description' => __( 'Search keyword. Searches for possible string matches on columns. When `searchColumns` is left empty, it tries to determine which column to search in based on search string.', 'wp-graphql' ),
					],
//					'searchColumns' => [
//						// @todo: enum columns
//						'type' => Types::list_of( Types::string() ),
//						'description' => __( 'Array of column names to be searched. Accepts \'ID\', \'login\', \'nicename\', \'email\', \'url\'.', 'wp-graphql' ),
//					],
					/**
					 * Field(s) to sort the retrieved users by. May be a single value,
					 * an array of values, or a multi-dimensional array with fields as
					 * keys and orders ('ASC' or 'DESC') as values. Accepted values are
					 * 'display_name' (or 'name'), 'include', 'user_login'
					 * ogin'), 'login__in', 'user_nicename' (or 'nicename'),
					 * cename__in', 'user_email (or 'email'), 'user_url' (or 'url'),
					 * 'user_registered' (or 'registered'), 'post_count', 'meta_value',
					 * 'meta_value_num', the value of `$meta_key`, or an array key of
					 * `$meta_query`. To use 'meta_value' or 'meta_value_num', `$meta_key`
					 */
//					'orderby' => [
//						// @todo: enum orderby
//						'type' => Types::list_of( Types::string() ),
//						'description' => __( 'Field(s) to sort the retrieved users by', 'wp-graphql' ),
//					],
//					'hasPublishedPosts' => [
//						// @todo: post_type_enum
//						'type' => Types::list_of( Types::string() ),
//						'description' => __( 'Pass an array of post types to filter results to users who have published posts in those post types.', 'wp-graphql' ),
//					],
					'nicename' => [
						'type' => Types::int(),
						'description' => __( 'The user nicename.', 'wp-graphql' ),
					],
					'nicenameIn' => [
						'type' => Types::list_of( Types::string() ),
						'description' => __( 'An array of nicenames to include. Users matching one of these nicenames will be included in results.', 'wp-graphql' ),
					],
					'nicenameNotIn' => [
						'type' => Types::list_of( Types::string() ),
						'description' => __( 'An array of nicenames to exclude. Users matching one of these nicenames will not be included in results.', 'wp-graphql' ),
					],
					'login' => [
						'type' => Types::string(),
						'description' => __( 'The user login.', 'wp-graphql' ),
					],
					'loginIn' => [
						'type' => Types::int(),
						'description' => __( 'An array of logins to include. Users matching one of these logins will be included in results.', 'wp-graphql' ),
					],
					'loginNotIn' => [
						'type' => Types::int(),
						'description' => __( 'An array of logins to exclude. Users matching one of these logins will not be included in results.', 'wp-graphql' ),
					],
				];
			},
		];

		parent::__construct( $config );

	}

}
