<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class CommentAuthor extends Model {

	protected $comment_author;

	public function __construct( $comment_author ) {
		$this->comment_author = $comment_author;
		parent::__construct( 'CommentAuthorObject', $comment_author );
		$this->init();
	}

	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return null;
		}

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id' => function() {
					return ! empty( $this->comment_author['comment_author_email'] ) ? Relay::toGlobalId( 'commentAuthor', $this->comment_author['comment_author_email'] ) : null;
				},
				'name' => function() {
					return ! empty( $this->comment_author['comment_author'] ) ? $this->comment_author['comment_author'] : null;
				},
				'email' => function() {
					return ! empty( $this->comment_author['comment_author_email'] ) ? $this->comment_author['comment_author_email'] : null;
				},
				'url' => function() {
					return ! empty( $this->comment_author['comment_author_url'] ) ? $this->comment_author['comment_author_url'] : '';
				}
			];

			parent::prepare_fields();

		}
	}
}
