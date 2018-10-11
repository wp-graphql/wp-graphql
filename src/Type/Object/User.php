<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;


register_graphql_type( 'User', [
	'description' => __( 'A User object', 'wp-graphql' ),
	'fields'      => [
		'id'                => [
			'type'        => [
				'non_null' => 'ID'
			],
			'description' => __( 'The globally unique identifier for the user', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ( ! empty( $info->parentType ) && ! empty( $user->ID ) ) ? Relay::toGlobalId( 'user', $user->ID ) : null;
			},
		],
		'capabilities'      => [
			'type'        => [
				'list_of' => 'String'
			],
			'description' => __( 'This field is the id of the user. The id of the user matches WP_User->ID field and the value in the ID column for the "users" table in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				if ( ! empty( $user->allcaps ) ) {
					// Filters list for capabilities the user has.
					$capabilities = array_keys( array_filter( $user->allcaps, function( $cap ) {
						return true === $cap;
					} ) );
				}

				return ! empty( $capabilities ) ? $capabilities : null;
			},
		],
		'capKey'            => [
			'type'        => 'String',
			'description' => __( 'User metadata option name. Usually it will be "wp_capabilities".', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->cap_key ) ? $user->cap_key : null;
			},
		],
		'roles'             => [
			'type'        => [
				'list_of' => 'String'
			],
			'description' => __( 'A list of roles that the user has. Roles can be used for querying for certain types of users, but should not be used in permissions checks.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->roles ) ? $user->roles : null;
			},
		],
		'email'             => [
			'type'        => 'String',
			'description' => __( 'Email of the user. This is equivalent to the WP_User->user_email property.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->user_email ) ? $user->user_email : null;
			},
		],
		'firstName'         => [
			'type'        => 'String',
			'description' => __( 'First name of the user. This is equivalent to the WP_User->user_first_name property.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->first_name ) ? $user->first_name : null;
			},
		],
		'lastName'          => [
			'type'        => 'String',
			'description' => __( 'Last name of the user. This is equivalent to the WP_User->user_last_name property.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->last_name ) ? $user->last_name : null;
			},
		],
		'extraCapabilities' => [
			'type'        => [
				'list_of' => 'String'
			],
			'description' => __( 'A complete list of capabilities including capabilities inherited from a role. This is equivalent to the array keys of WP_User->allcaps.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->allcaps ) ? array_keys( $user->allcaps ) : null;
			},
		],
		'description'       => [
			'type'        => 'String',
			'description' => __( 'Description of the user.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->description ) ? $user->description : null;
			},
		],
		'username'          => [
			'type'        => 'String',
			'description' => __( 'Username for the user. This field is equivalent to WP_User->user_login.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->user_login ) ? $user->user_login : null;
			},
		],
		'name'              => [
			'type'        => 'String',
			'description' => __( 'Display name of the user. This is equivalent to the WP_User->dispaly_name property.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->display_name ) ? $user->display_name : null;
			},
		],
		'registeredDate'    => [
			'type'        => 'String',
			'description' => __( 'The date the user registered or was created. The field follows a full ISO8601 date string format.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->user_registered ) ? date( 'c', strtotime( $user->user_registered ) ) : null;
			},
		],
		'nickname'          => [
			'type'        => 'String',
			'description' => __( 'Nickname of the user.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->nickname ) ? $user->nickname : null;
			},
		],
		'url'               => [
			'type'        => 'String',
			'description' => __( 'A website url that is associated with the user.', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->user_url ) ? $user->user_url : null;
			},
		],
		'slug'              => [
			'type'        => 'String',
			'description' => __( 'The slug for the user. This field is equivalent to WP_User->user_nicename', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->user_nicename ) ? $user->user_nicename : null;
			},
		],
		'nicename'          => [
			'type'        => 'String',
			'description' => __( 'The nicename for the user. This field is equivalent to WP_User->user_nicename', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->user_nicename ) ? $user->user_nicename : null;
			},
		],
		'locale'            => [
			'type'        => 'String',
			'description' => __( 'The preferred language locale set for the user. Value derived from get_user_locale().', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				$user_locale = get_user_locale( $user );

				return ! empty( $user_locale ) ? $user_locale : null;
			},
		],
		'userId'            => [
			'type'        => 'Int',
			'description' => __( 'The Id of the user. Equivelant to WP_User->ID', 'wp-graphql' ),
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {
				return ! empty( $user->ID ) ? absint( $user->ID ) : null;
			},
		],
		'avatar'            => [
			'type'        => 'Avatar',
			'description' => __( 'Avatar object for user. The avatar object can be retrieved in different sizes by specifying the size argument.', 'wp-graphql' ),
			'args'        => [
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
					'type' => 'AvatarRatingEnum',
				],

			],
			'resolve'     => function( \WP_User $user, $args, $context, $info ) {

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

				$avatar = get_avatar_data( $user->ID, $avatar_args );

				return ( ! empty( $avatar ) && true === $avatar['found_avatar'] ) ? $avatar : null;
			},
		],
	],
	'interfaces'  => [ WPObjectType::node_interface() ]
] );
