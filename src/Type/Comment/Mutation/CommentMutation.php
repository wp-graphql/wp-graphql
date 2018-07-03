<?php

namespace WPGraphQL\Type\Comment\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

/**
 * Class CommentMutation
 *
 * @package WPGraphQL\Type\Comment\Mutation
 */
class CommentMutation {
    /**
	 * Holds the input_fields configuration
	 *
	 * @var array
	 */
    private static $input_fields = [];
    
    /**
	 * Checks and initialize comment mutation input fields
	 * @return mixed|array|null $input_fields
	 */
	public static function input_fields() {
		if (empty(self::$input_fields)) {
			$input_fields = [
				'postId' 	  => [
					'type'		  => Types::id(),
					'description' => __('The ID of the post the comment belongs to.', 'wp-graphql'),
				],
				'userId'    => [
					'type'        => Types::id(),
					'description' => __( 'The userID of the comment\'s author.', 'wp-graphql' ),
				],
				'author'    => [
					'type'        => Types::string(),
					'description' => __( 'The name of the comment\'s author.', 'wp-graphql' ),
				],
				'authorEmail'    => [
					'type'        => Types::string(),
					'description' => __( 'The email of the comment\'s author.', 'wp-graphql' ),
				],
				'authorUrl'    => [
					'type'        => Types::string(),
					'description' => __( 'The url of the comment\'s author.', 'wp-graphql' ),
				],
				'authorIp'    => [
					'type'        => Types::string(),
					'description' => __( 'IP address for the comment\'s author.', 'wp-graphql' ),
				],
				'content'     => [
					'type'        => Types::string(),
					'description' => __( 'Content of the comment.', 'wp-graphql' ),
				],
				'type'        => [
					'type'        => Types::string(),
					'description' => __( 'Type of comment.', 'wp-graphql' ),
				],
				'parent'      => [
					'type'        =>  Types::id(),
					'description' => __( 'Parent comment of current comment.', 'wp-graphql' ),
				],
				'agent'       => [
					'type'        => Types::string(),
					'description' => __( 'User agent used to post the comment.', 'wp-graphql' ),
				],
				'date'        => [
					'type'        => Types::string(),
					'description' => __( 'The date of the object. Preferable to enter as year/month/day (e.g. 01/31/2017) as it will rearrange date as fit if it is not specified. Incomplete dates may have unintended results for example, "2017" as the input will use current date with timestamp 20:17 ', 'wp-graphql' ),
				],
				'approved'    => [
					'type'        => Types::string(),
					'description' => __( 'The approval status of the comment.', 'wp-graphql' ),
				],
			];

			/**
			 * Filters the mutation input fields for the object type
			 *
			 * @param array         $input_fields     	The array of input fields
			 */
			self::$input_fields = apply_filters('graphql_comment_mutation_input_fields', $input_fields );
		}

		return (!empty(self::$input_fields)) ? self::$input_fields : null;
    }

    /**
	 * This handles inserting the comment and creating 
	 *
	 * @param array         $input              The input for the mutation
	 * @param string        $mutation_name      The name of the mutation being performed
	 *
	 * @return array $insert_comment_args
	 * @throws \Exception
	 */
	public static function prepare_comment_object($input, $mutation_name) {
		/**
		 * Prepare the data for inserting the post
		 * NOTE: These are organized in the same order as: https://developer.wordpress.org/reference/functions/wp_insert_comment/
		 *
		 *  Ex.
		 * 	'comment_post_ID' => 1,
		 *	'comment_author' => 'admin',
		 *	'comment_author_email' => 'admin@admin.com',
		 *	'comment_author_url' => 'http://',
		 *	'comment_content' => 'content here',
		 *	'comment_type' => '',
		 *	'comment_parent' => 0,
		 *	'user_id' => 1,
		 *	'comment_author_IP' => '127.0.0.1',
		 *	'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
		 *	'comment_date' => $time,
		 *	'comment_approved' => 1,
		 */

		$insert_comment_args = [];

		$author = !empty($input['authorId']) ? Relay::fromGlobalId($input['authorId']) : null;
		if (is_array($author) && is_int($author['id'])) {
			$insert_comment_args['user_id'] = absint($author['id']);
			if ($author['name']) $insert_comment_args['comment_author'] = $author['name'];
			if ($author['email']) $insert_comment_args['comment_author_email'] = $author['email'];
			if ($author['url']) $insert_comment_args['comment_author_url'] = $author['url'];
		} else {
			if (!empty($input['author'])) $insert_comment_args['comment_author'] = $input['author'];
			if (!empty($input['authorEmail'])) {
				if ( false === is_email( apply_filters( 'pre_user_email', $input['authorEmail'] ) ) ) {
					throw new UserError( __( 'The email address you are trying to use is invalid', 'graphql' ) );
				}
				$insert_comment_args['comment_author_email'] = $input['authorEmail'];
			}
			if (!empty($input['authorUrl'])) $insert_comment_args['comment_author_url'] = $input['authorUrl'];
		}

		if (!empty($input['postId'])) {
			$insert_comment_args['comment_post_ID'] = $input['postId'];
		}

		if (!empty($input['date']) && false !== strtotime($input['date'])) {
			$insert_comment_args['comment_date'] = date( 'Y-m-d H:i:s', strtotime($input['date']));
		}

		if (!empty($input['content'])) {
			$insert_comment_args['comment_content'] = $input['content'];
		}

		if (!empty($input['parent'])) {
			$insert_comment_args['comment_parent'] = $input['parent'];
		}

		if (!empty($input['type'])) {
			$insert_comment_args['comment_type'] = $input['type'];
		}

		if (!empty($input['authorIP'])) {
			$insert_comment_args['comment_author_IP'] = $input['authorIp'];
		}

		if (!empty($input['agent'])) {
			$insert_comment_args['comment_agent'] = $input['agent'];
		}

		if (!empty($input['approved'])) {
			$insert_comment_args['comment_approved'] = $input['approved'];
		}

		/**
		 * Filter the $insert_post_args
		 *
		 * @param array         $insert_post_args The array of $input_post_args that will be passed to wp_insert_post
		 * @param array         $input            The data that was entered as input for the mutation
		 * @param \WP_Post_Type $post_type_object The post_type_object that the mutation is affecting
		 * @param string        $mutation_type    The type of mutation being performed (create, edit, etc)
		 */
		$insert_comment_args = apply_filters('graphql_comment_insert_post_args', $insert_comment_args, $input, $mutation_name);

		return $insert_comment_args;
    }

    /**
	 * This updates commentmeta.
	 *
	 * @param int           $post_id              The ID of the postObject the comment is connected to
	 * @param array         $input                The input for the mutation
	 * @param \WP_Comment   $comment_object       The Comment Object for the type of post being mutated
	 * @param string        $mutation_name        The name of the mutation (ex: create, update, delete)
	 * @param AppContext    $context              The AppContext passed down to all resolvers
	 * @param ResolveInfo   $info                 The ResolveInfo passed down to all resolvers
	 * @param string        $comment_status       The comment object status set by user privileges and post permissions
	 */
	public static function update_additional_comment_data($post_id, $input, $comment_object, $mutation_name, AppContext $context, ResolveInfo $info) {

    }
}