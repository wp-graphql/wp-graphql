<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

class Comment extends Model {

	protected $comment;

	protected $fields;

	public function __construct( \WP_Comment $comment ) {

		$allowed_restricted_fields = [
			'id',
			'ID',
			'commentId',
			'contentRendered',
			'date',
			'dateGmt',
			'karma',
			'type',
			'author',
			'commentedOn',
			'approved',
			'parent',
			'isRestricted',
			'isPrivate',
			'isPublic',
		];

		$this->comment = $comment;

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );
		}

		parent::__construct( 'commentObject', $comment, 'moderate_comments', $allowed_restricted_fields, $comment->user_id );
		$this->init();

	}

	public function is_private( $private, $model_name, $data ) {

		if ( 'commentObject' !== $model_name ) {
			return $private;
		}

		if ( ! $data->comment_approved && ! current_user_can( 'moderate_comments' ) ) {
			return true;
		}

		return $private;

	}

	public function init() {

		if ( 'private' === $this->get_visibility() ) {
			$this->comment = null;
			return;
		}

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id' => function() {
					return ! empty( $this->comment->comment_ID ) ? Relay::toGlobalId( 'comment', $this->comment->comment_ID ) : null;
				},
				'commentId' => function() {
					return ! empty( $this->comment->comment_ID ) ? $this->comment->comment_ID : 0;
				},
				'comment_ID' => function() {
					return ! empty( $this->comment->comment_ID ) ? $this->comment->comment_ID : 0;
				},
				'commentedOn' => function() {
					$post_object = null;
					if ( ! empty( $this->comment->comment_post_ID ) ) {
						$post_object = get_post( $this->comment->comment_post_ID );
						$post_object = isset( $post_object->post_type ) && isset( $post_object->ID ) ? DataSource::resolve_post_object( $post_object->ID, $post_object->post_type ) : null;
					}

					return $post_object;
				},
				'author' => function() {
					/**
					 * If the comment has a user associated, use it to populate the author, otherwise return
					 * the $comment and the Union will use that to hydrate the CommentAuthor Type
					 */
					if ( ! empty( $this->comment->user_id ) ) {
						return DataSource::resolve_user( absint( $this->comment->user_id ) );
					} else {
						return DataSource::resolve_comment_author( $this->comment->comment_author_email );
					}
				},
				'authorIp' => function() {
					return ! empty( $this->comment->comment_author_IP ) ? $this->comment->comment_author_IP : null;
				},
				'date' => function() {
					return ! empty( $this->comment->comment_date ) ? $this->comment->comment_date : null;
				},
				'dateGmt' => function() {
					return ! empty( $this->comment->comment_date_gmt ) ? $this->comment->comment_date_gmt : null;
				},
				'contentRaw' => function() {
					return ! empty( $this->comment->comment_content ) ? $this->comment->comment_content : null;
				},
				'contentRendered' => function() {
					$content = ! empty( $this->comment->comment_content ) ? $this->comment->comment_content : null;
					return apply_filters( 'comment_text', $content );
				},
				'karma' => function() {
					return ! empty( $this->comment->comment_karma ) ? $this->comment->comment_karma : null;
				},
				'approved' => function() {
					return ! empty( $this->comment->comment_approved ) ? $this->comment->comment_approved : null;
				},
				'agent' => function() {
					return ! empty( $this->comment->comment_agent ) ? $this->comment->comment_agent : null;
				},
				'type' => function() {
					return ! empty( $this->comment->comment_type ) ? $this->comment->comment_type : null;
				},
				'parent' => function() {
					$parent = null;
					if ( ! empty( $this->comment->comment_parent ) ) {
						$parent_obj = get_comment( $this->comment->comment_parent );
						if ( is_a( $parent_obj, 'WP_Comment' ) ) {
							$parent = new Comment( $parent_obj );
						}
					}
					return $parent;
				},
			];

			parent::prepare_fields();

		}

	}
}
