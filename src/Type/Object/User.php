<?php

namespace WPGraphQL\Type\Object;

use WPGraphQL\Data\DataSource;

/**
 * Class User
 *
 * @package WPGraphQL\Type\Object
 */
class User {

	/**
	 * Registers the User type
	 */
	public static function register_type() {

		register_graphql_object_type(
			'User',
			[
				'description' => __( 'A User object', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'UniformResourceIdentifiable' ],
				'fields'      => [
					'id'                => [
						'description' => __( 'The globally unique identifier for the user object.', 'wp-graphql' ),
					],
					'databaseId'        => [
						'type'        => [ 'non_null' => 'Int' ],
						'description' => __( 'Identifies the primary key from the database.', 'wp-graphql' ),
						'resolve'     => function( \WPGraphQL\Model\User $user ) {
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
					'status'            => [
						'type'        => 'String',
						'description' => __( 'The status for the user. This field is equivalent to WP_User->user_status', 'wp-graphql' ),
					],
					'pluginsPerPage'     => [
						'type'        => 'String',
						'description' => __( 'The plugins per page for the user. This field is equivalent to get_user_meta( WP_User->id, \'plugins_per_page\', true )', 'wp-graphql' ),
					],
					'activationKey'     => [
						'type'        => 'String',
						'description' => __( 'The activation_key for the user. This field is equivalent to WP_User->user_activation_key', 'wp-graphql' ),
					],
					'level'     => [
						'type'        => 'String',
						'description' => __( 'The level of the user. This field is equivalent to WP_User->user_level', 'wp-graphql' ),
					],
					'commentKeyboardShortcuts'     => [
						'type'        => 'String',
						'description' => __( 'Enable keyboard shortcuts for comment moderation. This field is equivalent to WP_User->comment_shortcuts', 'wp-graphql' ),
					],
					'enableRichEditing'     => [
						'type'        => 'String',
						'description' => __( 'The rich editing for the user is enabled. This field is equivalent to WP_User->rich_editing', 'wp-graphql' ),
					],
					'enableSyntaxHighlighting'     => [
						'type'        => 'String',
						'description' => __( 'The syntaxHighlighting for the user is enabled. This field is equivalent to WP_User->syntax_highlighting', 'wp-graphql' ),
					],
					'login'             => [
						'type'        => 'String',
						'description' => __( 'The login for the user. This field is equivalent to WP_User->user_login', 'wp-graphql' ),
					],
					'locale'            => [
						'type'        => 'String',
						'description' => __( 'The preferred language locale set for the user. Value derived from get_user_locale().', 'wp-graphql' ),
					],
					'userId'            => [
						'type'        => 'Int',
						'description' => __( 'The Id of the user. Equivalent to WP_User->ID', 'wp-graphql' ),
					],
					'isRestricted'      => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
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
						'resolve'     => function( $user, $args, $context, $info ) {

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

							return ( ! empty( $avatar ) && true === $avatar->foundAvatar ) ? $avatar : null;
						},
					],
				],
			]
		);


	$mock_colors = '
	{
	   "fresh":{
		  "name":"Default",
		  "url":false,
		  "colors":[
			 "#222",
			 "#333",
			 "#0073aa",
			 "#00a0d2"
		  ],
		  "icon_colors":{
			 "base":"#a0a5aa",
			 "focus":"#00a0d2",
			 "current":"#fff"
		  }
	   },
	   "light":{
		  "name":"Light",
		  "url":"http://acf2.local/wp-admin/css/colors/light/colors.min.css",
		  "colors":[
			 "#e5e5e5",
			 "#999",
			 "#d64e07",
			 "#04a4cc"
		  ],
		  "icon_colors":{
			 "base":"#999",
			 "focus":"#ccc",
			 "current":"#ccc"
		  }
	   },
	   "blue":{
		  "name":"Blue",
		  "url":"http://acf2.local/wp-admin/css/colors/blue/colors.min.css",
		  "colors":[
			 "#096484",
			 "#4796b3",
			 "#52accc",
			 "#74B6CE"
		  ],
		  "icon_colors":{
			 "base":"#e5f8ff",
			 "focus":"#fff",
			 "current":"#fff"
		  }
	   },
	   "midnight":{
		  "name":"Midnight",
		  "url":"http://acf2.local/wp-admin/css/colors/midnight/colors.min.css",
		  "colors":[
			 "#25282b",
			 "#363b3f",
			 "#69a8bb",
			 "#e14d43"
		  ],
		  "icon_colors":{
			 "base":"#f1f2f3",
			 "focus":"#fff",
			 "current":"#fff"
		  }
	   },
	   "sunrise":{
		  "name":"Sunrise",
		  "url":"http://acf2.local/wp-admin/css/colors/sunrise/colors.min.css",
		  "colors":[
			 "#b43c38",
			 "#cf4944",
			 "#dd823b",
			 "#ccaf0b"
		  ],
		  "icon_colors":{
			 "base":"#f3f1f1",
			 "focus":"#fff",
			 "current":"#fff"
		  }
	   },
	   "ectoplasm":{
		  "name":"Ectoplasm",
		  "url":"http://acf2.local/wp-admin/css/colors/ectoplasm/colors.min.css",
		  "colors":[
			 "#413256",
			 "#523f6d",
			 "#a3b745",
			 "#d46f15"
		  ],
		  "icon_colors":{
			 "base":"#ece6f6",
			 "focus":"#fff",
			 "current":"#fff"
		  }
	   },
	   "ocean":{
		  "name":"Ocean",
		  "url":"http://acf2.local/wp-admin/css/colors/ocean/colors.min.css",
		  "colors":[
			 "#627c83",
			 "#738e96",
			 "#9ebaa0",
			 "#aa9d88"
		  ],
		  "icon_colors":{
			 "base":"#f2fcff",
			 "focus":"#fff",
			 "current":"#fff"
		  }
	   },
	   "coffee":{
		  "name":"Coffee",
		  "url":"http://acf2.local/wp-admin/css/colors/coffee/colors.min.css",
		  "colors":[
			 "#46403c",
			 "#59524c",
			 "#c7a589",
			 "#9ea476"
		  ],
		  "icon_colors":{
			 "base":"#f3f2f1",
			 "focus":"#fff",
			 "current":"#fff"
		  }
	   }
	}
';
		register_graphql_field(
			'User',
			'selectedAdminColorScheme',
			[
				'type' => 'AdminColorScheme',
				'resolve' => function( \WPGraphQL\Model\User $user ) use ( $mock_colors ) {
					$encoded = json_decode( $mock_colors );
	
					$colors = [];
	
					foreach ($encoded as $key => $value) {
						$value->key = $key;
						$colors[$key] = $value;
					}
	
					$color = get_user_meta( $user->userId, 'admin_color', true );
					return isset( $colors[ $color ] ) ? $colors[ $color ] : $colors[ 'fresh' ];
				}
			]
		);

	}
}
