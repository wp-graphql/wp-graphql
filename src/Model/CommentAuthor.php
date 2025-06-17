<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WP_Comment;

/**
 * Class CommentAuthor - Models the CommentAuthor object
 *
 * @property ?int    $databaseId
 * @property ?string $email
 * @property ?string $id
 * @property ?string $name
 * @property ?string $url
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_Comment>
 */
class CommentAuthor extends Model {
	/**
	 * CommentAuthor constructor.
	 *
	 * @param \WP_Comment $comment_author The incoming comment author array to be modeled
	 */
	public function __construct( WP_Comment $comment_author ) {
		$this->data = $comment_author;
		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'databaseId' => function () {
					return ! empty( $this->data->comment_ID ) ? absint( $this->data->comment_ID ) : null;
				},
				'email'      => function () {
					return current_user_can( 'moderate_comments' ) && ! empty( $this->data->comment_author_email ) ? $this->data->comment_author_email : null;
				},
				'id'         => function () {
					return ! empty( $this->databaseId ) ? Relay::toGlobalId( 'comment_author', (string) $this->databaseId ) : null;
				},
				'name'       => function () {
					return ! empty( $this->data->comment_author ) ? $this->data->comment_author : null;
				},
				'url'        => function () {
					return ! empty( $this->data->comment_author_url ) ? $this->data->comment_author_url : '';
				},
			];
		}
	}
}
