<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Model\User;
use WPGraphQL\Utils\Utils;

/**
 * Class UserDelete
 *
 * @package WPGraphQL\Mutation
 */
class UserDelete {
	/**
	 * Registers the CommentCreate mutation.
	 *
	 * @return void
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'deleteUser',
			[
				'inputFields'         => self::get_input_fields(),
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
			'id'         => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'The ID of the user you want to delete', 'wp-graphql' ),
			],
			'reassignId' => [
				'type'        => 'ID',
				'description' => __( 'Reassign posts and links to new User ID.', 'wp-graphql' ),
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
			'deletedId' => [
				'type'        => 'ID',
				'description' => __( 'The ID of the user that you just deleted', 'wp-graphql' ),
				'resolve'     => static function ( $payload ) {
					$deleted = (object) $payload['user'];
					return ( ! empty( $deleted->ID ) ) ? Relay::toGlobalId( 'user', $deleted->ID ) : null;
				},
			],
			'user'      => [
				'type'        => 'User',
				'description' => __( 'The deleted user object', 'wp-graphql' ),
				'resolve'     => static function ( $payload ) {
					return new User( $payload['user'] );
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return static function ( $input ) {
			// Get the user ID.
			$user_id = Utils::get_database_id_from_id( $input['id'] );

			if ( empty( $user_id ) ) {
				throw new UserError( esc_html__( 'The user ID passed is invalid', 'wp-graphql' ) );
			}

			if ( ! current_user_can( 'delete_users', $user_id ) ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to delete users.', 'wp-graphql' ) );
			}

			/**
			 * Retrieve the user object before it's deleted
			 */
			$user_before_delete = get_user_by( 'id', $user_id );

			/**
			 * Throw an error if the user we are trying to delete doesn't exist
			 */
			if ( false === $user_before_delete ) {
				throw new UserError( esc_html__( 'Could not find an existing user to delete', 'wp-graphql' ) );
			}

			/**
			 * Get the user to reassign posts to.
			 */
			$reassign_id = 0;
			if ( ! empty( $input['reassignId'] ) ) {
				$reassign_id = Utils::get_database_id_from_id( $input['reassignId'] );

				if ( empty( $reassign_id ) ) {
					throw new UserError( esc_html__( 'The user ID passed to `reassignId` is invalid', 'wp-graphql' ) );
				}
				/**
			 * Retrieve the user object before it's deleted
			 */
				$reassign_user = get_user_by( 'id', $reassign_id );

				if ( false === $reassign_user ) {
					throw new UserError( esc_html__( 'Could not find the existing user to reassign.', 'wp-graphql' ) );
				}
			}

			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}

			if ( is_multisite() ) {

				/**
				 * If wpmu_delete_user() or remove_user_from_blog() doesn't exist yet,
				 * load the files in which each is defined. I think we need to
				 * load this manually here because WordPress only uses this
				 * function on the user edit screen normally.
				 */

				// only include these files for multisite requests
				if ( ! function_exists( 'wpmu_delete_user' ) ) {
					require_once ABSPATH . 'wp-admin/includes/ms.php';
				}
				if ( ! function_exists( 'remove_user_from_blog' ) ) {
					require_once ABSPATH . 'wp-admin/includes/ms-functions.php';
				}

				$blog_id = get_current_blog_id();

				// remove the user from the blog and reassign their posts
				remove_user_from_blog( $user_id, $blog_id, $reassign_id );

				// delete the user
				$deleted_user = wpmu_delete_user( $user_id );
			} else {
				$deleted_user = wp_delete_user( $user_id, $reassign_id );
			}

			if ( true !== $deleted_user ) {
				throw new UserError( esc_html__( 'Could not delete the user.', 'wp-graphql' ) );
			}

			return [
				'user' => $user_before_delete,
			];
		};
	}
}
