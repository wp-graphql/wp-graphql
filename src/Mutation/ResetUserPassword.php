<?php
namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use function Patchwork\Utils\args;
use WPGraphQL\AppContext;

class ResetUserPassword {
	public static function register_mutation() {
		register_graphql_mutation( 'resetUserPassword', [
			'inputFields' => self::get_input_fields(),
			'outputFields' => self::get_output_fields(),
			'mutateAndGetPayload' => self::mutate_and_get_payload(),
		] );
	}

	public static function get_input_fields() {
		return [
			'key'      => [
				'type'        => 'String',
				'description' => __( 'Password reset key', 'wp-graphql' ),
			],
			'outputFields' => [
				'user' => [
					'type' => 'User',
					'resolve' => function( $payload ) {
						$user = null;
						if ( ! empty( $payload['id'] ) ) {
							$user = get_user_by( 'ID', absint( $payload['id'] ) );
						}
						return $user;
					},
				],
			],
			'password' => [
				'type'        => 'String',
				'description' => __( 'The new password.', 'wp-graphql' ),
			],
		];
	}

	public static function get_output_fields() {
		return UserCreate::get_output_fields();
	}

	public static function mutate_and_get_payload() {
		return function( $input, AppContext $context, ResolveInfo $info ) {

					/**
					 * Throw an error with the message
					 */
					throw new UserError( $message );
				}

				/**
				 * Reset the password
				 */
				reset_password( $user, $input['password'] );

				/**
				 * Return the user ID
				 */
				return [
					'id' => $user->ID,
				];

			/**
			 * Reset the password
			 */
			reset_password( $user, $input['password'] );

			/**
			 * Return the user ID
			 */
			return [
				'id' => $user->ID,
			];
		};
	}
}