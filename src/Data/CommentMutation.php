<?php

namespace WPGraphQL\Data;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

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
	 * @param string $mutation_name The name of the mutation being performed
	 *
	 * @return array $output_args
	 * @throws \Exception
	 */
	public static function prepare_comment_object( $input, &$output_args, $mutation_name, $update = false ) {
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

		$user = wp_get_current_user();
		if ( $user instanceof \WP_User && 0 !== $user->ID ) {
			$output_args['user_id']              = $user->ID;
			$output_args['comment_author']       = $user->display_name;
			$output_args['comment_author_email'] = $user->user_email;
			if ( ! is_null( $user->user_url ) ) {
				$output_args['comment_author_url'] = $user->user_url;
			}
		} else {
			if ( empty( $input['author'] ) ) {
				if ( ! $update ) {
					throw new UserError( __( 'Comment must include an authorName', 'wp-graphql' ) );
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
			$output_args['comment_parent'] = $input['parent'];
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
	 * @param int         $comment_id              The ID of the postObject the comment is connected to
	 * @param array       $input                   The input for the mutation
	 * @param string      $mutation_name           The name of the mutation ( ex: create, update, delete )
	 * @param AppContext  $context                 The AppContext passed down to all resolvers
	 * @param ResolveInfo $info                    The ResolveInfo passed down to all resolvers
	 * @param string      $intended_comment_status The intended post_status the post should have according to the mutation input
	 * @param string      $intended_comment_status The default status posts should use if an intended status wasn't set
	 */
	public static function update_additional_comment_data( $comment_id, $input, $mutation_name, AppContext $context, ResolveInfo $info ) {
		/**
		* @todo: should account for authentication
		*/
		$intended_comment_status = 0;
		$default_comment_status  = 0;

		do_action( 'graphql_comment_object_mutation_update_additional_data', $comment_id, $input, $mutation_name, $context, $info, $intended_comment_status, $default_comment_status );
	}
}
