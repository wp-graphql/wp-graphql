<?php
namespace WPGraphQL\Type;

use GraphQL\Language\AST\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Connections;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class CommentType extends ObjectType {

	public function __construct() {

		$node_definition = DataSource::get_node_definition();

		$config = [
			'name' => 'comment',
			'description' => __( 'A Comment object', 'wp-graphql' ),
			'fields' => function() {
				$fields = [
					'id'                 => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'The globally unique identifier for the user', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_ID ) ? Relay::toGlobalId( 'comment', $comment->comment_ID ) : null;
						},
					],
					'commentId' => [
						'type' => Types::int(),
						'description' => __( 'ID for the comment, unique among comments.', 'wp-graphql' ),
						'resolve' => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_ID ) ? $comment->comment_ID : 0;
						},
					],
					//@todo: the related post_object needs to be a union as the parent of a comment can be any post_type
					'author'          => [
						'type'        => Types::user(),
						'description' => esc_html__( 'The post field for comments matches the post id the comment is 
						assigned to. This field is equivalent to WP_Comment->comment_post_ID and the value matching 
						the `comment_post_ID` column in SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							$author = new \WP_User( $comment->user_id );
							return ! empty( $author ) ? $author : null;
						},
					],
					'author_ip'       => [
						'type'        => Types::string(),
						'description' => esc_html__( 'IP address for the author. This field is equivalent to 
						WP_Comment->comment_author_IP and the value matching the `comment_author_IP` column in 
						SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_author_IP ) ? $comment->comment_author_IP : '';
						},
					],
					'date'            => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Date the comment was posted in local time. This field is 
						equivalent to WP_Comment->date and the value matching the `date` column in 
						SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->date ) ? $comment->date : '';
						},
					],
					'date_gmt'        => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Date the comment was posted in GMT. This field is equivalent 
						to WP_Comment->date_gmt and the value matching the `date_gmt` column in SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->date_gmt ) ? $comment->date_gmt : '';
						},
					],
					'content'         => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Content of the comment. This field is equivalent to 
						WP_Comment->comment_content and the value matching the `comment_content` column in 
						SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_content ) ? $comment->comment_content : '';
						},
					],
					'karma'           => [
						'type'        => Types::int(),
						'description' => esc_html__( 'Karma value for the comment. This field is equivalent to 
						WP_Comment->comment_karma and the value matching the `comment_karma` column in 
						SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_karma ) ? $comment->comment_karma : 0;
						},
					],
					'approved'        => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The approval status of the comment. This field is equivalent 
						to WP_Comment->comment_approved and the value matching the `comment_approved` column in 
						SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_approved ) ? $comment->comment_approved : '';
						},
					],
					'agent'           => [
						'type'        => Types::string(),
						'description' => esc_html__( 'User agent used to post the comment. This field is equivalent 
						to WP_Comment->comment_agent and the value matching the `comment_agent` column in 
						SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_agent ) ? $comment->comment_agent : '';
						},
					],
					'type'            => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Type of comment. This field is equivalent to 
						WP_Comment->comment_type and the value matching the `comment_type` column in 
						SQL.', 'wp-graphql' ),
						'resolve'     => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_type ) ? $comment->comment_type : '';
						},
					],
					'parent' => [
						'type' => Types::comment(),
						'description' => esc_html__( 'Parent comment of current comment. This field is equivalent to 
						the WP_Comment instance matching the WP_Comment->comment_parent ID.', 'wp-graphql' ),
						'resolve' => function( \WP_Comment $comment, $args, $context, ResolveInfo $info ) {
							return get_comment( $comment->comment_parent );
						}
					],
				];

				$fields['children'] = Connections::comments_connection();

				return $fields;
			},
			'interfaces' => [ $node_definition['nodeInterface'] ],
		];

		parent::__construct( $config );

	}

}