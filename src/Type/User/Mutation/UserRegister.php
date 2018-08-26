<?php

namespace WPGraphQL\Type\User\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Types;

/**
 * Class UserRegister
 *
 * @package WPGraphQL\Type\User\Mutation
 */
class UserRegister {

	/**
	 * Stores the user register mutation
	 *
	 * @var array $mutation
	 * @access private
	 */
	private static $mutation;

	/**
	 * Process the user register mutation
	 *
	 * @return array|null
	 * @access public
	 */
	public static function mutate() {

		if ( empty( self::$mutation ) ) {

			self::$mutation = Relay::mutationWithClientMutationId( [
				'name' => 'RegisterUser',
				'description' => __( 'Register new user', 'wp-graphql' ),
				'inputFields' => self::input_fields(),
				'outputFields' => [
					'user' => [
						'type' => Types::user(),
						'resolve' => function( $payload ) {
							return get_user_by( 'ID', $payload['id'] );
						}
					]
				],
				'mutateAndGetPayload' => function( $input, AppContext $context, ResolveInfo $info ) {

					if ( ! get_option('users_can_register') ) {
						throw new UserError( __( 'User registration is currently not allowed.', 'wp-graphql' ) );
					}

					if ( empty( $input['username'] ) ) {
						throw new UserError( __( 'A username was not provided.', 'wp-graphql' ) );
					}

					if ( empty( $input['email'] ) ) {
						throw new UserError( __( 'An email address was not provided.', 'wp-graphql' ) );
					}

					/**
					 * Map all of the args from GQL to WP friendly
					 */
					$user_args = UserMutation::prepare_user_object( $input, 'userRegister' );

					/**
					 * Register the new user
					 */
					$user_id = register_new_user( $user_args['user_login'], $user_args['user_email'] );

					/**
					 * Throw an exception if the user failed to register
					 */
					if ( is_wp_error( $user_id ) ) {
						$error_message = $user_id->get_error_message();
						if ( ! empty( $error_message ) ) {
							throw new UserError( esc_html( $error_message ) );
						} else {
							throw new UserError( __( 'The user failed to register but no error was provided', 'wp-graphql' ) );
						}
					}

					/**
					 * If the $user_id is empty, we should throw an exception
					 */
					if ( empty( $user_id ) ) {
						throw new UserError( __( 'The user failed to create', 'wp-graphql' ) );
					}

					/**
					 * Set the ID of the user to be used in the update
					 */
					$user_args['ID'] = absint( $user_id );

					/**
					 * Update the registered user with the additional input (firstName, lastName, etc) from the mutation
					 */
					wp_update_user( $user_args );

					/**
					 * Update additional user data
					 */
					UserMutation::update_additional_user_object_data( $user_id, $input, 'register', $context, $info );

					/**
					 * Return the new user ID
					 */
					return [
						'id' => $user_id,
					];

				}

			] );
		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;

	}

	/**
	 * Add the username and email fields for register mutations
	 *
	 * @return array
	 */
	private static function input_fields() {

		/**
		 * Register mutations require a username and email to be passed
		 */
		$input_fields = array_merge(
			[
				'username' => [
					'type'        => Types::non_null( Types::string() ),
					// translators: the placeholder is the name of the type of object being updated
					'description' => __( 'A string that contains the user\'s username.', 'wp-graphql' ),
				],
				'email'    => [
					'type'        => Types::string(),
					'description' => __( 'A string containing the user\'s email address.', 'wp-graphql' ),
				],
			],
			UserMutation::input_fields()
		);

		/**
		 * Roles should not be a mutable field by default during registration
		 */
		unset( $input_fields['roles'] );

		return $input_fields;

	}

}
