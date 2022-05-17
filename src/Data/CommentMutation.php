<?php

namespace WPGraphQL\Data;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

/**
 * Class CommentMutation
 *
 * @package WPGraphQL\Type\Comment\Mutation
 */
class CommentMutation {

	/**
	 * This handles inserting the comment and creating
	 *
	 * @param array  $input         The input for the mutation
	 * @param array  $output_args   The output args
	 * @param string $mutation_name The name of the mutation being performed
	 * @param bool   $update        Whether it's an update action
	 *
	 * @return array $output_args
	 * @throws Exception
	 */
	public static function prepare_comment_object( array $input, array &$output_args, string $mutation_name, $update = false ) {
		/**
		 * Prepare the data for inserting the comment
		 * NOTE: These are organized in the same order as: https://developer.wordpress.org/reference/functions/wp_insert_comment/
		 *
		 *  Ex.
		 *    'comment_post_ID' => 1,
		 *    'comment_author' => 'admin',
		 *    'comment_author_email' => 'admin@admin.com',
		 *    'comment_author_url' => 'http://',
		 *    'comment_content' => 'content here',
		 *    'comment_type' => '',
		 *    'comment_parent' => 0,
		 *    'comment_date' => $time,
		 *    'comment_approved' => 1,
		 */

		$user = self::get_comment_author( $input['authorEmail'] ?? null );

		if ( false !== $user ) {

			$output_args['user_id'] = $user->ID;

			$input['author']      = ! empty( $input['author'] ) ? $input['author'] : $user->display_name;
			$input['authorEmail'] = ! empty( $input['authorEmail'] ) ? $input['authorEmail'] : $user->user_email;
			$input['authorUrl']   = ! empty( $input['authorUrl'] ) ? $input['authorUrl'] : $user->user_url;
		}

		if ( empty( $input['author'] ) ) {
			if ( ! $update ) {
				throw new UserError( __( 'Comment must include an authorName.', 'wp-graphql' ) );
			}
		} else {
			$output_args['comment_author'] = $input['author'];
		}

		if ( ! empty( $input['authorEmail'] ) ) {
			if ( false === is_email( apply_filters( 'pre_user_email', $input['authorEmail'] ) ) ) {
				throw new UserError( __( 'The email address you are trying to use is invalid', 'wp-graphql' ) );
			}
			$output_args['comment_author_email'] = $input['authorEmail'];
		}

		if ( ! empty( $input['authorUrl'] ) ) {
			$output_args['comment_author_url'] = $input['authorUrl'];
		}

		if ( ! empty( $input['commentOn'] ) ) {
			$output_args['comment_post_ID'] = $input['commentOn'];
		}

		if ( ! empty( $input['date'] ) && false !== strtotime( $input['date'] ) ) {
			$output_args['comment_date'] = gmdate( 'Y-m-d H:i:s', strtotime( $input['date'] ) );
		}

		if ( ! empty( $input['content'] ) ) {
			$output_args['comment_content'] = $input['content'];
		}

		if ( ! empty( $input['parent'] ) ) {
			$output_args['comment_parent'] = Utils::get_database_id_from_id( $input['parent'] );
		}

		if ( ! empty( $input['type'] ) ) {
			$output_args['comment_type'] = $input['type'];
		}

		if ( ! empty( $input['approved'] ) ) {
			$output_args['comment_approved'] = $input['approved'];
		}

		/**
		 * Filter the $insert_post_args
		 *
		 * @param array  $output_args   The array of $input_post_args that will be passed to wp_new_comment
		 * @param array  $input         The data that was entered as input for the mutation
		 * @param string $mutation_type The type of mutation being performed ( create, edit, etc )
		 */
		$output_args = apply_filters( 'graphql_comment_insert_post_args', $output_args, $input, $mutation_name );

		return $output_args;
	}

	/**
	 * This updates commentmeta.
	 *
	 * @param int         $comment_id    The ID of the postObject the comment is connected to
	 * @param array       $input         The input for the mutation
	 * @param string      $mutation_name The name of the mutation ( ex: create, update, delete )
	 * @param AppContext  $context       The AppContext passed down to all resolvers
	 * @param ResolveInfo $info          The ResolveInfo passed down to all resolvers
	 *
	 * @return void
	 */
	public static function update_additional_comment_data( int $comment_id, array $input, string $mutation_name, AppContext $context, ResolveInfo $info ) {

		/**
		 * @todo: should account for authentication
		 */
		$intended_comment_status = 0;
		$default_comment_status  = 0;

		do_action( 'graphql_comment_object_mutation_update_additional_data', $comment_id, $input, $mutation_name, $context, $info, $intended_comment_status, $default_comment_status );
	}

	/**
	 * Gets the user object for the comment author.
	 *
	 * @param ?string $author_email The authorEmail provided to the mutation input.
	 *
	 * @return \WP_User|false
	 */
	protected static function get_comment_author( string $author_email = null ) {
		$user = wp_get_current_user();

		// Fail if no logged in user.
		if ( 0 === $user->ID ) {
			return false;
		}

		// Return the current user if they can only handle their own comments or if there's no specified author.
		if ( empty( $author_email ) || ! $user->has_cap( 'moderate_comments' ) ) {
			return $user;
		}

		$author = get_user_by( 'email', $author_email );

		return ! empty( $author->ID ) ? $author : false;
	}
}
