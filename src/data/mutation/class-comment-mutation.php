<?php
/**
 * Defines shared functionality for comment mutations.
 *
 * @package WPGraphQL\Data\Mutation
 */

namespace WPGraphQL\Data\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class Comment_Mutation
 */
class Comment_Mutation {

	/**
	 * This handles inserting the comment and creating
	 *
	 * @param array  $input          The input for the mutation.
	 * @param array  $output_args    Process argument being provided WordPress comment functions.
	 * @param string $mutation_name  The name of the mutation being performed.
	 * @param string $update         Whether or not an existing comment is being updated.
	 *
	 * @throws UserError Invalid input.
	 *
	 * @return array $output_args
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
		 *    'user_id' => 1,
		 *    'comment_author_IP' => '127.0.0.1',
		 *    'comment_agent' => 'Mozilla/5.0 ( Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10 ) Gecko/2009042316 Firefox/3.0.10 ( .NET CLR 3.5.30729 )',
		 *    'comment_date' => $time,
		 *    'comment_approved' => 1,
		 */

		$user = ! empty( $input['userId'] ) ? get_user_by( 'ID', $input['userId'] ) : false;
		if ( $user instanceof \WP_User ) {
			$output_args['user_id']              = $user->ID;
			$output_args['comment_author']       = $user->display_name;
			$output_args['comment_author_email'] = $user->user_email;
			if ( ! is_null( $user->user_url ) ) {
				$output_args['comment_author_url'] = $user->user_url;
			}
		} else {
			if ( empty( $input['author'] ) ) {
				if ( ! $update ) {
					throw new UserError( __( 'Must enter a valid user_id or author name', 'graphql' ) );
				}
			} else {
				$output_args['comment_author'] = $input['author'];
			}
			if ( ! empty( $input['authorEmail'] ) ) {
				if ( false === is_email( apply_filters( 'pre_user_email', $input['authorEmail'] ) ) ) {
					throw new UserError( __( 'The email address you are trying to use is invalid', 'graphql' ) );
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

		if ( ! empty( $input['authorIp'] ) ) {
			$output_args['comment_author_IP'] = $input['authorIp'];
		}

		if ( ! empty( $input['agent'] ) ) {
			$output_args['comment_agent'] = $input['agent'];
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
}
