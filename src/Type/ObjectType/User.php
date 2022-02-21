<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;
use WPGraphQL\Data\Connection\UserRoleConnectionResolver;
use WPGraphQL\Data\DataSource;
use \WPGraphQL\Model\User as UserModel;

/**
 * Class User
 *
 * @package WPGraphQL\Type\Object
 */
class User {

	/**
	 * Registers the User type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'User',
			[
				'description' => __( 'A User object', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'UniformResourceIdentifiable', 'Commenter', 'DatabaseIdentifier' ],
				'connections' => [
					'enqueuedScripts'     => [
						'toType'  => 'EnqueuedScript',
						'resolve' => function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'enqueuedStylesheets' => [
						'toType'  => 'EnqueuedStylesheet',
						'resolve' => function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'roles'               => [
						'toType'        => 'UserRole',
						'fromFieldName' => 'roles',
						'resolve'       => function ( UserModel $user, $args, $context, $info ) {
							$resolver = new UserRoleConnectionResolver( $user, $args, $context, $info );
							// Only get roles matching the slugs of the roles belonging to the user

							if ( isset( $user->roles ) && ! empty( $user->roles ) ) {
								$resolver->set_query_arg( 'slugIn', $user->roles );
							}

							return $resolver->get_connection();
						},
					],
				],
				'fields'      => [
					'id'                => [
						'description' => __( 'The globally unique identifier for the user object.', 'wp-graphql' ),
					],
					'databaseId'        => [
						'type'        => [ 'non_null' => 'Int' ],
						'description' => __( 'Identifies the primary key from the database.', 'wp-graphql' ),
						'resolve'     => function ( \WPGraphQL\Model\User $user ) {
							return absint( $user->userId );
						},
					],
					'capabilities'      => [
						'type'        => [
							'list_of' => 'String',
						],
						'description' => __( 'A list of capabilities (permissions) granted to the user', 'wp-graphql' ),
					],
					'capKey'            => [
						'type'        => 'String',
						'description' => __( 'User metadata option name. Usually it will be "wp_capabilities".', 'wp-graphql' ),
					],
					'email'             => [
						'type'        => 'String',
						'description' => __( 'Email address of the user. This is equivalent to the WP_User->user_email property.', 'wp-graphql' ),
					],
					'firstName'         => [
						'type'        => 'String',
						'description' => __( 'First name of the user. This is equivalent to the WP_User->user_first_name property.', 'wp-graphql' ),
					],
					'lastName'          => [
						'type'        => 'String',
						'description' => __( 'Last name of the user. This is equivalent to the WP_User->user_last_name property.', 'wp-graphql' ),
					],
					'extraCapabilities' => [
						'type'        => [
							'list_of' => 'String',
						],
						'description' => __( 'A complete list of capabilities including capabilities inherited from a role. This is equivalent to the array keys of WP_User->allcaps.', 'wp-graphql' ),
					],
					'description'       => [
						'type'        => 'String',
						'description' => __( 'Description of the user.', 'wp-graphql' ),
					],
					'username'          => [
						'type'        => 'String',
						'description' => __( 'Username for the user. This field is equivalent to WP_User->user_login.', 'wp-graphql' ),
					],
					'name'              => [
						'type'        => 'String',
						'description' => __( 'Display name of the user. This is equivalent to the WP_User->dispaly_name property.', 'wp-graphql' ),
					],
					'registeredDate'    => [
						'type'        => 'String',
						'description' => __( 'The date the user registered or was created. The field follows a full ISO8601 date string format.', 'wp-graphql' ),
					],
					'nickname'          => [
						'type'        => 'String',
						'description' => __( 'Nickname of the user.', 'wp-graphql' ),
					],
					'url'               => [
						'type'        => 'String',
						'description' => __( 'A website url that is associated with the user.', 'wp-graphql' ),
					],
					'slug'              => [
						'type'        => 'String',
						'description' => __( 'The slug for the user. This field is equivalent to WP_User->user_nicename', 'wp-graphql' ),
					],
					'nicename'          => [
						'type'        => 'String',
						'description' => __( 'The nicename for the user. This field is equivalent to WP_User->user_nicename', 'wp-graphql' ),
					],
					'locale'            => [
						'type'        => 'String',
						'description' => __( 'The preferred language locale set for the user. Value derived from get_user_locale().', 'wp-graphql' ),
					],
					'userId'            => [
						'type'              => 'Int',
						'description'       => __( 'The Id of the user. Equivalent to WP_User->ID', 'wp-graphql' ),
						'deprecationReason' => __( 'Deprecated in favor of the databaseId field', 'wp-graphql' ),
					],
					'isRestricted'      => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
					'avatar'            => [
						'args'    => [
							'size'         => [
								'type'         => 'Int',
								'description'  => __( 'The size attribute of the avatar field can be used to fetch avatars of different sizes. The value corresponds to the dimension in pixels to fetch. The default is 96 pixels.', 'wp-graphql' ),
								'defaultValue' => 96,
							],
							'forceDefault' => [
								'type'        => 'Boolean',
								'description' => __( 'Whether to always show the default image, never the Gravatar. Default false' ),
							],
							'rating'       => [
								'type'        => 'AvatarRatingEnum',
								'description' => __( 'The rating level of the avatar.', 'wp-graphql' ),
							],

						],
						'resolve' => function ( $user, $args, $context, $info ) {

							$avatar_args = [];
							if ( is_numeric( $args['size'] ) ) {
								$avatar_args['size'] = absint( $args['size'] );
								if ( ! $avatar_args['size'] ) {
									$avatar_args['size'] = 96;
								}
							}

							if ( ! empty( $args['forceDefault'] ) && true === $args['forceDefault'] ) {
								$avatar_args['force_default'] = true;
							}

							if ( ! empty( $args['rating'] ) ) {
								$avatar_args['rating'] = esc_sql( $args['rating'] );
							}

							$avatar = DataSource::resolve_avatar( $user->userId, $avatar_args );

							return isset( $avatar->foundAvatar ) && true === $avatar->foundAvatar ? $avatar : null;
						},
					],
				],
			]
		);
	}
}
