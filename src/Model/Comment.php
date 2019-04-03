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
	 * @var \WP_Comment $comment
	 * @access protected
	 */
	protected $comment;

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

		$this->comment = $comment;

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );
		}

		parent::__construct( $comment, 'moderate_comments', $allowed_restricted_fields, $comment->user_id );
		$this->init();

	}

	/**
	 * Callback for the graphql_data_is_private filter for determining if the object should be
	 * considered private or not
	 *
	 * @param bool        $private    Whether or not to consider the object private
	 * @param string      $model_name Name of the model currently being processed
	 * @param \WP_Comment $data       The data currently being modeled
	 *
	 * @access public
	 * @return bool
	 */
	public function is_private( $private, $model_name, $data ) {

		if ( $this->get_model_name() !== $model_name ) {
			return $private;
		}

		if ( true != $data->comment_approved && ! current_user_can( 'moderate_comments' ) ) {
			return true;
		}

		return $private;

	}

	/**
	 * Initializes the object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

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
				'commentAuthorEmail' => function() {
					return ! empty( $this->comment->comment_author_email ) ? $this->comment->comment_author_email : 0;
				},
				'comment_ID' => function() {
					return ! empty( $this->comment->comment_ID ) ? $this->comment->comment_ID : 0;
				},
				'comment_post_ID' => function() {
					return ! empty( $this->comment->comment_post_ID ) ? absint( $this->comment->comment_post_ID ) : null;
				},
				'comment_parent_id' => function() {
					return ! empty( $this->comment->comment_parent ) ? absint( $this->comment->comment_parent ) : 0;
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
				'userId' => function() {
					return ! empty( $this->comment->user_id ) ? $this->comment->user_id : null;
				}
			];

			parent::prepare_fields();

		}

	}
}
