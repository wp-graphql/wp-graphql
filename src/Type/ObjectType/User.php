<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\UserRoleConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\User as UserModel;
use WPGraphQL\Type\Connection\PostObjects;

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
				'description' => static function () {
					return __( 'A registered user account. Users can be assigned roles, author content, and have various capabilities within the site.', 'wp-graphql' );
				},
				'model'       => UserModel::class,
				'interfaces'  => [ 'Node', 'UniformResourceIdentifiable', 'Commenter', 'DatabaseIdentifier' ],
				'connections' => [
					'enqueuedScripts'     => [
						'toType'  => 'EnqueuedScript',
						'resolve' => static function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'enqueuedStylesheets' => [
						'toType'  => 'EnqueuedStylesheet',
						'resolve' => static function ( $source, $args, $context, $info ) {
							$resolver = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'revisions'           => [
						'toType'             => 'ContentNode',
						'connectionTypeName' => 'UserToRevisionsConnection',
						'queryClass'         => 'WP_Query',
						'description'        => static function () {
							return __( 'Connection between the User and Revisions authored by the user', 'wp-graphql' );
						},
						'connectionArgs'     => PostObjects::get_connection_args(),
						'resolve'            => static function ( $root, $args, $context, $info ) {
							$resolver = new PostObjectConnectionResolver( $root, $args, $context, $info, 'revision' );

							return $resolver->get_connection();
						},
					],
					'roles'               => [
						'toType'        => 'UserRole',
						'fromFieldName' => 'roles',
						'resolve'       => static function ( UserModel $user, $args, $context, $info ) {
							$resolver = new UserRoleConnectionResolver( $user, $args, $context, $info );

							// abort if no roles are set
							if ( empty( $user->roles ) ) {
								return null;
							}

							// Only get roles matching the slugs of the roles belonging to the user
							$resolver->set_query_arg( 'slugIn', $user->roles );
							return $resolver->get_connection();
						},
					],
				],
				'fields'      => static function () {
					return [
						'id'                     => [
							'description' => static function () {
								return __( 'The globally unique identifier for the user object.', 'wp-graphql' );
							},
						],
						'capabilities'           => [
							'type'        => [
								'list_of' => 'String',
							],
							'description' => static function () {
								return __( 'A list of capabilities (permissions) granted to the user', 'wp-graphql' );
							},
						],
						'capKey'                 => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'User metadata option name. Usually it will be "wp_capabilities".', 'wp-graphql' );
							},
						],
						'databaseId'             => [
							'type'        => [ 'non_null' => 'Int' ],
							'description' => static function () {
								return __( 'Identifies the primary key from the database.', 'wp-graphql' );
							},
							'resolve'     => static function ( \WPGraphQL\Model\User $user ) {
								return (int) $user->databaseId;
							},
						],
						'description'            => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Description of the user.', 'wp-graphql' );
							},
						],
						'email'                  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Email address of the user. This is equivalent to the WP_User->user_email property.', 'wp-graphql' );
							},
						],
						'extraCapabilities'      => [
							'type'        => [
								'list_of' => 'String',
							],
							'description' => static function () {
								return __( 'A complete list of capabilities including capabilities inherited from a role. This is equivalent to the array keys of WP_User->allcaps.', 'wp-graphql' );
							},
						],
						'firstName'              => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'First name of the user. This is equivalent to the WP_User->user_first_name property.', 'wp-graphql' );
							},
						],
						'lastName'               => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Last name of the user. This is equivalent to the WP_User->user_last_name property.', 'wp-graphql' );
							},
						],

						'username'               => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Username for the user. This field is equivalent to WP_User->user_login.', 'wp-graphql' );
							},
						],
						'name'                   => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Display name of the user. This is equivalent to the WP_User->display_name property.', 'wp-graphql' );
							},
						],
						'registeredDate'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The date the user registered or was created. The field follows a full ISO8601 date string format.', 'wp-graphql' );
							},
						],
						'nickname'               => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Nickname of the user.', 'wp-graphql' );
							},
						],
						'url'                    => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'A website url that is associated with the user.', 'wp-graphql' );
							},
						],
						'slug'                   => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The slug for the user. This field is equivalent to WP_User->user_nicename', 'wp-graphql' );
							},
						],
						'nicename'               => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The nicename for the user. This field is equivalent to WP_User->user_nicename', 'wp-graphql' );
							},
						],
						'locale'                 => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The preferred language locale set for the user. Value derived from get_user_locale().', 'wp-graphql' );
							},
						],
						'userId'                 => [
							'type'              => 'Int',
							'description'       => static function () {
								return __( 'The Id of the user. Equivalent to WP_User->ID', 'wp-graphql' );
							},
							'deprecationReason' => static function () {
								return __( 'Deprecated in favor of the databaseId field', 'wp-graphql' );
							},
						],
						'isRestricted'           => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
						'shouldShowAdminToolbar' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the Toolbar should be displayed when the user is viewing the site.', 'wp-graphql' );
							},
						],
						'avatar'                 => [
							'args'    => [
								'size'         => [
									'type'         => 'Int',
									'description'  => static function () {
										return __( 'The size attribute of the avatar field can be used to fetch avatars of different sizes. The value corresponds to the dimension in pixels to fetch. The default is 96 pixels.', 'wp-graphql' );
									},
									'defaultValue' => 96,
								],
								'forceDefault' => [
									'type'        => 'Boolean',
									'description' => static function () {
										return __( 'Whether to always show the default image, never the Gravatar. Default false', 'wp-graphql' );
									},
								],
								'rating'       => [
									'type'        => 'AvatarRatingEnum',
									'description' => static function () {
										return __( 'The rating level of the avatar.', 'wp-graphql' );
									},
								],

							],
							'resolve' => static function ( $user, $args ) {
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

								return DataSource::resolve_avatar( $user->userId, $avatar_args );
							},
						],
					];
				},
			]
		);
	}
}
