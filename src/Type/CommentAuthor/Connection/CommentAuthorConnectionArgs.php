<?php
namespace WPGraphQL\Type\CommentAuthor\Connection;

use GraphQL\Type\Definition\EnumType;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

/**
 * Class CommentAuthorConnectionArgs
 *
 * This sets up the Query Args for comment author connections, which uses WP_Comment_Query, so this defines the allowed
 * input fields that will be passed to the WP_Comment_Query
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class CommentAuthorConnectionArgs extends WPInputObjectType {

	/**
	 * This holds the field definitions
	 * @var array $fields
	 * @since 0.0.5
	 */
	public static $fields;

	/**
	 * CommentAuthorConnectionArgs constructor.
	 *
	 * @since 0.0.5
	 */
	public function __construct( $config = [] ) {
		$config['name'] = 'commentAuthorArgs';
		$config['fields'] = self::fields();
		parent::__construct( $config );
	}

	/**
	 * fields
	 *
	 * This defines the fields that make up the CommentAuthorConnectionArgs
	 *
	 * @return array
	 * @since 0.0.5
	 */
	private static function fields() {

		if ( null === self::$fields ) :
			self::$fields = function() {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'description' => __( 'The globally unique identifier for the Comment Author user', 'wp-graphql' ),
						'resolve' => function( \WP_Comment $comment, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_ID ) ? Relay::toGlobalId( 'comment', $comment->comment_ID ) : null;
						},
					],
					'name' => [
						'type' => Types::string(),
						'description' => __( 'The name for the comment author.', 'wp-graphql' ),
						'resolve' => function( \WP_Comment $comment, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_author ) ? $comment->comment_author : '';
						},
					],
					'email' => [
						'type' => Types::string(),
						'description' => __( 'The email for the comment author', 'wp-graphql' ),
						'resolve' => function( \WP_Comment $comment, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_author_email ) ? get_post( $comment->comment_author_email ) : '';
						},
					],
					'url' => [
						'type' => Types::string(),
						'description' => __( 'The url the comment author.', 'wp-graphql' ),
						'resolve' => function( \WP_Comment $comment, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_author_IP ) ? $comment->comment_author_url : '';
						},
					],
				];

			};
			$f2 = $fields;
		endif;

		return self::$fields;

	}

}
