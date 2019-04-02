<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

/**
 * Class CommentAuthor - Models the CommentAuthor object
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $url
 *
 * @package WPGraphQL\Model
 */
class CommentAuthor extends Model {

	/**
	 * Stores the comment author to be modeled
	 *
	 * @var array $comment_author
	 * @access protected
	 */
	protected $comment_author;

	/**
	 * CommentAuthor constructor.
	 *
	 * @param array $comment_author The incoming comment author array to be modeled
	 *
	 * @throws \Exception
	 * @access public
	 */
	public function __construct( $comment_author ) {
		$this->comment_author = $comment_author;
		parent::__construct( 'CommentAuthorObject', $comment_author );
		$this->init();
	}

	/**
	 * Initializes the object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return;
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
