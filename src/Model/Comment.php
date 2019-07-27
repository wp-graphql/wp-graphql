<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class Comment - Models data for Comments
 *
 * @property string     $id
 * @property int        $commentId
 * @property string     $commentAuthorEmail
 * @property int        $comment_ID
 * @property int        $comment_parent_id
 * @property string     $authorIp
 * @property string     $date
 * @property string     $dateGmt
 * @property string     $contentRaw
 * @property string     $contentRendered
 * @property string     $karma
 * @property int        $approved
 * @property string     $agent
 * @property string     $type
 * @property int        $userId
 *
 * @package WPGraphQL\Model
 */
class Comment extends Model {

	/**
	 * Stores the incoming WP_Comment object to be modeled
	 *
	 * @var \WP_Comment $data
	 * @access protected
	 */
	protected $data;

	/**
	 * Comment constructor.
	 *
	 * @param \WP_Comment $comment The incoming WP_Comment to be modeled
	 *
	 * @throws \Exception
	 */
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
			'commentedOnId',
			'comment_post_ID',
			'approved',
			'comment_parent_id',
			'isRestricted',
			'isPrivate',
			'isPublic',
		];

		$this->data = $comment;
		parent::__construct( 'moderate_comments', $allowed_restricted_fields, $comment->user_id );

	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @access protected
	 * @return bool
	 */
	protected function is_private() {

		if ( true != $this->data->comment_approved && ! current_user_can( 'moderate_comments' ) ) {
			return true;
		}

		return false;

	}

	/**
	 * Initializes the object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id'                 => function() {
					return ! empty( $this->data->comment_ID ) ? Relay::toGlobalId( 'comment', $this->data->comment_ID ) : null;
				},
				'commentId'          => function() {
					return ! empty( $this->data->comment_ID ) ? $this->data->comment_ID : 0;
				},
				'commentAuthorEmail' => function() {
					return ! empty( $this->data->comment_author_email ) ? $this->data->comment_author_email : 0;
				},
				'comment_ID'         => function() {
					return ! empty( $this->data->comment_ID ) ? $this->data->comment_ID : 0;
				},
				'comment_post_ID'    => function() {
					return ! empty( $this->data->comment_post_ID ) ? absint( $this->data->comment_post_ID ) : null;
				},
				'comment_parent_id'  => function() {
					return ! empty( $this->data->comment_parent ) ? absint( $this->data->comment_parent ) : 0;
				},
				'authorIp'           => function() {
					return ! empty( $this->data->comment_author_IP ) ? $this->data->comment_author_IP : null;
				},
				'date'               => function() {
					return ! empty( $this->data->comment_date ) ? $this->data->comment_date : null;
				},
				'dateGmt'            => function() {
					return ! empty( $this->data->comment_date_gmt ) ? $this->data->comment_date_gmt : null;
				},
				'contentRaw'         => function() {
					return ! empty( $this->data->comment_content ) ? $this->data->comment_content : null;
				},
				'contentRendered'    => function() {
					$content = ! empty( $this->data->comment_content ) ? $this->data->comment_content : null;
					return apply_filters( 'comment_text', $content );
				},
				'karma'              => function() {
					return ! empty( $this->data->comment_karma ) ? $this->data->comment_karma : null;
				},
				'approved'           => function() {
					return ! empty( $this->data->comment_approved ) ? $this->data->comment_approved : null;
				},
				'agent'              => function() {
					return ! empty( $this->data->comment_agent ) ? $this->data->comment_agent : null;
				},
				'type'               => function() {
					return ! empty( $this->data->comment_type ) ? $this->data->comment_type : null;
				},
				'userId'             => function() {
					return ! empty( $this->data->user_id ) ? $this->data->user_id : null;
				},
			];

		}

	}
}
