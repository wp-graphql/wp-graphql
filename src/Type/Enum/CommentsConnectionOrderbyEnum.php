<?php
namespace WPGraphQL\Type\Enum;

class CommentsConnectionOrderbyEnum {

	/**
	 * Register the CommentsConnectionOrderbyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'CommentsConnectionOrderbyEnum',
			[
				'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
				'values'      => [
					'COMMENT_AGENT'        => [
						'description' => 'Order by browser user agent of the commenter.',
						'value'       => 'comment_agent',
					],
					'COMMENT_APPROVED'     => [
						'description' => 'Order by true/false approval of the comment.',
						'value'       => 'comment_approved',
					],
					'COMMENT_AUTHOR'       => [
						'description' => 'Order by name of the comment author.',
						'value'       => 'comment_author',
					],
					'COMMENT_AUTHOR_EMAIL' => [
						'description' => 'Order by e-mail of the comment author.',
						'value'       => 'comment_author_email',
					],
					'COMMENT_AUTHOR_IP'    => [
						'description' => 'Order by IP address of the comment author.',
						'value'       => 'comment_author_IP',
					],
					'COMMENT_AUTHOR_URL'   => [
						'description' => 'Order by URL address of the comment author.',
						'value'       => 'comment_author_url',
					],
					'COMMENT_CONTENT'      => [
						'description' => 'Order by the comment contents.',
						'value'       => 'comment_content',
					],
					'COMMENT_DATE'         => [
						'descriotion' => 'Order by date/time timestamp of the comment.',
						'value'       => 'comment_date',
					],
					'COMMENT_DATE_GMT'     => [
						'descriotion' => 'Order by GMT timezone date/time timestamp of the comment.',
						'value'       => 'comment_date_gmt',
					],
					'COMMENT_ID'           => [
						'description' => 'Order by the globally unique identifier for the comment object',
						'value'       => 'comment_ID',
					],
					'COMMENT_IN'           => [
						'description' => 'Order by the array list of comment IDs listed in the where clause.',
						'value'       => 'comment__in',
					],
					'COMMENT_KARMA'        => [
						'description' => 'Order by the comment karma score.',
						'value'       => 'comment_karma',
					],
					'COMMENT_PARENT'       => [
						'description' => 'Order by the comment parent ID.',
						'value'       => 'comment_parent',
					],
					'COMMENT_POST_ID'      => [
						'description' => 'Order by the post object ID.',
						'value'       => 'comment_post_ID',
					],
					'COMMENT_TYPE'         => [
						'description' => 'Order by the The type of comment, such as \'comment\', \'pingback\', or \'trackback\'.',
						'value'       => 'comment_type',
					],
					'USER_ID'              => [
						'description' => 'Order by the user ID.',
						'value'       => 'user_id',
					],
				],
			]
		);
	}
}
