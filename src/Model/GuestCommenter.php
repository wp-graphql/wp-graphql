<?php

namespace WPGraphQL\Model;

use Exception;
use GraphQLRelay\Relay;
use WP_Comment;

/**
 * Class GuestCommenter - Models the GuestCommenter object
 *
 * @property string $id
 * @property int    $databaseId
 * @property string $name
 * @property string $email
 * @property string $url
 *
 * @package WPGraphQL\Model
 */
class GuestCommenter extends Model {

	/**
	 * Stores the guest commenter to be modeled
	 *
	 * @var WP_Comment $data The raw data passed to he model
	 */
	protected $data;

	/**
	 * GuestCommenter constructor.
	 *
	 * @param WP_Comment $guest_commenter The incoming guest commenter array to be modeled
	 *
	 * @throws Exception
	 */
	public function __construct( WP_Comment $guest_commenter ) {
		$this->data = $guest_commenter;
		parent::__construct();
	}

	/**
	 * Initializes the object
	 *
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id'         => function () {
					return ! empty( $this->data->comment_ID ) ? Relay::toGlobalId( 'comment_author', $this->data->comment_ID ) : null;
				},
				'databaseId' => function () {
					return ! empty( $this->data->comment_ID ) ? absint( $this->data->comment_ID ) : null;
				},
				'name'       => function () {
					return ! empty( $this->data->comment_author ) ? $this->data->comment_author : null;
				},
				'email'      => function () {
					return current_user_can( 'moderate_comments' ) && ! empty( $this->data->comment_author_email ) ? $this->data->comment_author_email : null;
				},
				'url'        => function () {
					return ! empty( $this->data->comment_author_url ) ? $this->data->comment_author_url : '';
				},
			];

		}
	}
}
