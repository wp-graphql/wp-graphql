<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WP_Comment;

/**
 * Class Comment - Models data for Comments
 *
 * @property int    $comment_ID
 * @property int    $comment_parent_id
 * @property int    $commentId
 * @property int    $parentDatabaseId
 * @property int    $userId
 * @property string $agent
 * @property string $authorIp
 * @property string $comment_author
 * @property string $comment_author_url
 * @property string $commentAuthorEmail
 * @property string $contentRaw
 * @property string $contentRendered
 * @property string $date
 * @property string $dateGmt
 * @property string $id
 * @property string $karma
 * @property string $parentId
 * @property string $status
 * @property string $type
 *
 * @package WPGraphQL\Model
 */
class Comment extends Model {

	/**
	 * Stores the incoming WP_Comment object to be modeled
	 *
	 * @var \WP_Comment $data
	 */
	protected $data;

	/**
	 * Comment constructor.
	 *
	 * @param \WP_Comment $comment The incoming WP_Comment to be modeled
	 *
	 * @throws \Exception
	 */
	public function __construct( WP_Comment $comment ) {
		$allowed_restricted_fields = [
			'id',
			'ID',
			'commentId',
			'databaseId',
			'contentRendered',
			'date',
			'dateGmt',
			'karma',
			'type',
			'commentedOnId',
			'comment_post_ID',
			'approved',
			'status',
			'comment_parent_id',
			'parentId',
			'parentDatabaseId',
			'isRestricted',
			'userId',
		];

		$this->data = $comment;
		$owner      = ! empty( $comment->user_id ) ? absint( $comment->user_id ) : null;
		parent::__construct( 'moderate_comments', $allowed_restricted_fields, $owner );
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function is_private() {
		if ( empty( $this->data->comment_post_ID ) ) {
			return true;
		}

		// if the current user is the author of the comment, the comment should not be private
		if ( 0 !== wp_get_current_user()->ID && absint( $this->data->user_id ) === absint( wp_get_current_user()->ID ) ) {
			return false;
		}

		$commented_on = get_post( (int) $this->data->comment_post_ID );

		if ( ! $commented_on instanceof \WP_Post ) {
			return true;
		}

		// A comment is considered private if it is attached to a private post.
		if ( true === ( new Post( $commented_on ) )->is_private() ) {
			return true;
		}

		if ( 0 === absint( $this->data->comment_approved ) && ! current_user_can( 'moderate_comments' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Initializes the object
	 *
	 * @return void
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id'                 => function () {
					return ! empty( $this->data->comment_ID ) ? Relay::toGlobalId( 'comment', $this->data->comment_ID ) : null;
				},
				'commentId'          => function () {
					return ! empty( $this->data->comment_ID ) ? absint( $this->data->comment_ID ) : 0;
				},
				'databaseId'         => function () {
					return ! empty( $this->data->comment_ID ) ? $this->data->comment_ID : 0;
				},
				'commentAuthorEmail' => function () {
					return ! empty( $this->data->comment_author_email ) ? $this->data->comment_author_email : 0;
				},
				'comment_ID'         => function () {
					return ! empty( $this->data->comment_ID ) ? absint( $this->data->comment_ID ) : 0;
				},
				'comment_post_ID'    => function () {
					return ! empty( $this->data->comment_post_ID ) ? absint( $this->data->comment_post_ID ) : null;
				},
				'comment_parent_id'  => function () {
					return ! empty( $this->data->comment_parent ) ? absint( $this->data->comment_parent ) : 0;
				},
				'parentDatabaseId'   => function () {
					return ! empty( $this->data->comment_parent ) ? absint( $this->data->comment_parent ) : 0;
				},
				'parentId'           => function () {
					return ! empty( $this->comment_parent_id ) ? Relay::toGlobalId( 'comment', $this->data->comment_parent ) : null;
				},
				'comment_author'     => function () {
					return ! empty( $this->data->comment_author ) ? absint( $this->data->comment_author ) : null;
				},
				'comment_author_url' => function () {
					return ! empty( $this->data->comment_author_url ) ? absint( $this->data->comment_author_url ) : null;
				},
				'authorIp'           => function () {
					return ! empty( $this->data->comment_author_IP ) ? $this->data->comment_author_IP : null;
				},
				'date'               => function () {
					return ! empty( $this->data->comment_date ) ? $this->data->comment_date : null;
				},
				'dateGmt'            => function () {
					return ! empty( $this->data->comment_date_gmt ) ? $this->data->comment_date_gmt : null;
				},
				'contentRaw'         => function () {
					return ! empty( $this->data->comment_content ) ? $this->data->comment_content : null;
				},
				'contentRendered'    => function () {
					$content = ! empty( $this->data->comment_content ) ? $this->data->comment_content : null;

					return $this->html_entity_decode( apply_filters( 'comment_text', $content, $this->data ), 'contentRendered', false );
				},
				'karma'              => function () {
					return ! empty( $this->data->comment_karma ) ? $this->data->comment_karma : null;
				},
				'approved'           => function () {
					_doing_it_wrong( __METHOD__, 'The approved field is deprecated in favor of `status`', '1.13.0' );
					return ! empty( $this->data->comment_approved ) && 'hold' !== $this->data->comment_approved;
				},
				'status'             => function () {
					if ( ! is_numeric( $this->data->comment_approved ) ) {
						return $this->data->comment_approved;
					}

					return '1' === $this->data->comment_approved ? 'approve' : 'hold';
				},
				'agent'              => function () {
					return ! empty( $this->data->comment_agent ) ? $this->data->comment_agent : null;
				},
				'type'               => function () {
					return ! empty( $this->data->comment_type ) ? $this->data->comment_type : null;
				},
				'userId'             => function () {
					return ! empty( $this->data->user_id ) ? absint( $this->data->user_id ) : null;
				},
			];
		}
	}
}
