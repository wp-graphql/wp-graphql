<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Connections;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class UserType extends ObjectType {

	public function __construct() {

		$node_definition    = DataSource::get_node_definition();
		$allowed_post_types = \WPGraphQL::$allowed_post_types;
		$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

		$config = [
			'name'        => 'user',
			'description' => __( 'A User object', 'wp-graphql' ),
			'fields'      => function() use ( $allowed_post_types, $allowed_taxonomies ) {

				$fields = [
					'id'                => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'The globally unique identifier for the user', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ( ! empty( $info->parentType ) && ! empty( $user->ID ) ) ? Relay::toGlobalId( $info->parentType, $user->ID ) : null;
						},
					],
					'capabilities'      => [
						'type'        => Types::list_of( Types::string() ),
						'description' => __( 'This field is the id of the user. The id of the user matches WP_User->ID 
						field and the value in the ID column for the `users` table in SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							if ( ! empty( $user->allcaps ) ) {
								// Filters list for capabilities the user has.
								$capabilities = array_keys( array_filter( $user->allcaps, function( $cap ) {
									return true === $cap;
								} ) );
							}

							return ! empty( $capabilities ) ? $capabilities : [];
						},
					],
					'capKey'            => [
						'type'        => Types::string(),
						'description' => __( 'User metadata option name. Usually it will be 
						`wp_capabilities`.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->cap_key ) ? $user->cap_key : '';
						},
					],
					'roles'             => [
						'type'        => Types::list_of( Types::string() ),
						'description' => __( 'A list of roles that the user has. Roles can be used for querying for 
						certain types of users, but should not be used in permissions checks.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->roles ) ? $user->roles : [];
						},
					],
					'email'             => [
						'type'        => Types::string(),
						'description' => __( 'Email of the user. This is equivalent to the WP_User->user_email 
						property.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->user_email ) ? $user->user_email : '';
						},
					],
					'firstName'         => [
						'type'        => Types::string(),
						'description' => esc_html__( 'First name of the user. This is equivalent to the 
						WP_User->user_first_name property.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->first_name ) ? $user->first_name : '';
						},
					],
					'lastName'          => [
						'name'        => 'last_name',
						'type'        => Types::string(),
						'description' => esc_html__( 'Last name of the user. This is equivalent to the WP_User->user_last_name 
						property.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->last_name ) ? $user->last_name : '';
						},
					],
					'extraCapabilities' => [
						'type'        => Types::list_of( Types::string() ),
						'description' => esc_html__( 'A complete list of capabilities including capabilities inherited from a 
						role. This is equivalent to the array keys of WP_User->allcaps.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->allcaps ) ? array_keys( $user->allcaps ) : [];
						},
					],
					'description'       => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Description of the user.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->description ) ? $user->description : '';
						},
					],
					'username'          => [
						'type'        => Types::string(),
						'description' => __( 'Username for the user. This field is equivalent to 
						WP_User->user_login.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->user_login ) ? $user->user_login : '';
						},
					],
					'name'              => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Display name of the user. This is equivalent to the WP_User->dispaly_name 
						property.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->display_name ) ? $user->display_name : '';
						},
					],
					'registeredDate'    => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The date the user registered or was created. The field follows a full 
						ISO8601 date string format.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->user_registered ) ? date( 'c', strtotime( $user->user_registered ) ) : '';
						},
					],
					'nickname'          => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Nickname of the user.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->nickname ) ? $user->nickname : '';
						},
					],
					'url'               => [
						'type'        => Types::string(),
						'description' => esc_html__( 'A website url that is associated with the user.', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->user_url ) ? $user->user_url : '';
						},
					],
					'slug'              => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The slug for the user. This field is equivalent to 
						WP_User->user_nicename', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->user_nicename ) ? $user->user_nicename : '';
						},
					],
					'locale'            => [
						'type'              => Types::string(),
						'description'       => esc_html__( 'The preferred language locale set for the user. Value derived from 
						get_user_locale().', 'wp-graphql' ),
						'isDeprecated'      => true,
						'deprecationReason' => 'Fool, go away',
						'resolve'           => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							$user_locale = get_user_locale( $user );
							return ! empty( $user_locale ) ? $user_locale : '';
						},
					],
					'userId'            => [
						'type'        => Types::int(),
						'description' => __( 'The Id of the user. Equivelant to WP_User->ID', 'wp-graphql' ),
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							return ! empty( $user->ID ) ? $user->ID : 0;
						},
					],
					'avatar'            => [
						'type'        => Types::avatar(),
						'description' => __( 'Avatar object for user. The avatar object can be retrieved in different 
						sizes by specifying the size argument.', 'wp-graphql' ),
						'args'        => [
							'size' => [
								'type'         => Types::int(),
								'description'  => __( 'The size attribute of the avatar field can be used to fetch 
								avatars of different sizes. The value corresponds to the dimension in pixels to fetch. 
								The default is 96 pixels.', 'wp-graphql' ),
								'defaultValue' => 96,
							],
						],
						'resolve'     => function( \WP_User $user, $args, $context, ResolveInfo $info ) {
							$avatar = get_avatar_data( $user->ID, array( 'size', $args['size'] ) );

							return ( ! empty( $avatar ) && true === $avatar['found_avatar'] ) ? $avatar : false;
						},
					],
					'comments' => Connections::comments_connection(),
				];

				if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
					foreach ( $allowed_post_types as $post_type ) {
						// @todo: maybe look into narrowing this based on permissions?
						$post_type_object                                 = get_post_type_object( $post_type );
						$fields[ $post_type_object->graphql_plural_name ] = Connections::post_objects_connection( $post_type_object );
					}
				}

				$fields = apply_filters( 'graphql_user_type_fields', $fields );
				ksort( $fields );
				return $fields;
			},
			'interfaces'  => [
				$node_definition['nodeInterface'],
			],
		];

		parent::__construct( $config );

	}

}
