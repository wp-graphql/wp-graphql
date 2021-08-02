<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\UserMutation;
use WPGraphQL\Model\User;

/**
 * Class UserCreate
 *
 * @package WPGraphQL\Mutation
 */
class UserCreate {
	/**
	 * Registers the CommentCreate mutation.
	 *
	 * @return void
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'createUser',
			[
				'inputFields'         => array_merge(
					[
						'username' => [
							'type'        => [
								'non_null' => 'String',
							],
							// translators: the placeholder is the name of the type of post object being updated
							'description' => __( 'A string that contains the user\'s username for logging in.', 'wp-graphql' ),
						],
					],
					self::get_input_fields()
				),
				'outputFields'        => self::get_output_fields(),
				'mutateAndGetPayload' => self::mutate_and_get_payload(),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @return array
	 */
	public static function get_input_fields() {
		return [
			'password'    => [
				'type'        => 'String',
				'description' => __( 'A string that contains the plain text password for the user.', 'wp-graphql' ),
			],
			'nicename'    => [
				'type'        => 'String',
				'description' => __( 'A string that contains a URL-friendly name for the user. The default is the user\'s username.', 'wp-graphql' ),
			],
			'websiteUrl'  => [
				'type'        => 'String',
				'description' => __( 'A string containing the user\'s URL for the user\'s web site.', 'wp-grapql' ),
			],
			'email'       => [
				'type'        => 'String',
				'description' => __( 'A string containing the user\'s email address.', 'wp-graphql' ),
			],
			'displayName' => [
				'type'        => 'String',
				'description' => __( 'A string that will be shown on the site. Defaults to user\'s username. It is likely that you will want to change this, for both appearance and security through obscurity (that is if you dont use and delete the default admin user).', 'wp-graphql' ),
			],
			'nickname'    => [
				'type'        => 'String',
				'description' => __( 'The user\'s nickname, defaults to the user\'s username.', 'wp-graphql' ),
			],
			'firstName'   => [
				'type'        => 'String',
				'description' => __( '	The user\'s first name.', 'wp-graphql' ),
			],
			'lastName'    => [
				'type'        => 'String',
				'description' => __( 'The user\'s last name.', 'wp-graphql' ),
			],
			'description' => [
				'type'        => 'String',
				'description' => __( 'A string containing content about the user.', 'wp-graphql' ),
			],
			'richEditing' => [
				'type'        => 'String',
				'description' => __( 'A string for whether to enable the rich editor or not. False if not empty.', 'wp-graphql' ),
			],
			'registered'  => [
				'type'        => 'String',
				'description' => __( 'The date the user registered. Format is Y-m-d H:i:s.', 'wp-graphql' ),
			],
			'roles'       => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'An array of roles to be assigned to the user.', 'wp-graphql' ),
			],
			'jabber'      => [
				'type'        => 'String',
				'description' => __( 'User\'s Jabber account.', 'wp-graphql' ),
			],
			'aim'         => [
				'type'        => 'String',
				'description' => __( 'User\'s AOL IM account.', 'wp-graphql' ),
			],
			'yim'         => [
				'type'        => 'String',
				'description' => __( 'User\'s Yahoo IM account.', 'wp-graphql' ),
			],
			'locale'      => [
				'type'        => 'String',
				'description' => __( 'User\'s locale.', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array
	 */
	public static function get_output_fields() {
		return [
			'user' => [
				'type'        => 'User',
				'description' => __( 'The User object mutation type.', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return function ( $input, AppContext $context, ResolveInfo $info ) {
			if ( ! current_user_can( 'create_users' ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to create a new user.', 'wp-graphql' ) );
			}

			/**
			 * Map all of the args from GQL to WP friendly
			 */
			$user_args = UserMutation::prepare_user_object( $input, 'createUser' );

			/**
			 * Create the new user
			 */
			$user_id = wp_insert_user( $user_args );

			/**
			 * Throw an exception if the post failed to create
			 */
			if ( is_wp_error( $user_id ) ) {
				$error_message = $user_id->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				} else {
					throw new UserError( __( 'The object failed to create but no error was provided', 'wp-graphql' ) );
				}
			}

			/**
			 * If the $post_id is empty, we should throw an exception
			 */
			if ( empty( $user_id ) ) {
				throw new UserError( __( 'The object failed to create', 'wp-graphql' ) );
			}

			/**
			 * Update additional user data
			 */
			UserMutation::update_additional_user_object_data( $user_id, $input, 'createUser', $context, $info );

			/**
			 * Return the new user ID
			 */
			return [
				'id'   => $user_id,
				'user' => $context->get_loader( 'user' )->load_deferred( $user_id ),
			];
		};
	}
}
