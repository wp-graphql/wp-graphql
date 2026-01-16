<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WP_Comment;

/**
 * Class Comment - Models data for Comments
 *
 * @property ?string $agent
 * @property ?string $authorIp
 * @property ?string $commentAuthor
 * @property ?string $commentAuthorEmail
 * @property ?string $commentAuthorUrl
 * @property ?string $contentRaw
 * @property ?string $contentRendered
 * @property int     $databaseId
 * @property ?string $date
 * @property ?string $dateGmt
 * @property ?string $id
 * @property ?string $karma
 * @property ?string $link
 * @property int     $parentDatabaseId
 * @property string  $parentId
 * @property ?string $status
 * @property ?string $type
 * @property ?string $uri
 * @property ?int    $userId
 *
 * Aliases:
 * @property ?string $comment_author
 * @property ?string $comment_author_url
 * @property int     $comment_ID
 * @property int     $comment_parent_id
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_Comment>
 */
class Comment extends Model {
	/**
	 * Comment constructor.
	 *
	 * @param \WP_Comment $comment The incoming WP_Comment to be modeled
	 */
	public function __construct( WP_Comment $comment ) {
		$allowed_restricted_fields = [
			'approved',
			'comment_parent_id',
			'comment_post_ID',
			'commentAuthor',
			'commentAuthorEmail',
			'commentAuthorUrl',
			'commentedOnId',
			'commentId',
			'contentRendered',
			'databaseId',
			'date',
			'dateGmt',
			'id',
			'ID',
			'isRestricted',
			'karma',
			'link',
			'parentDatabaseId',
			'parentId',
			'status',
			'type',
			'uri',
			'userId',
		];

		$this->data = $comment;
		$owner      = ! empty( $comment->user_id ) ? absint( $comment->user_id ) : null;
		parent::__construct( 'moderate_comments', $allowed_restricted_fields, $owner );
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'agent'              => function () {
					return ! empty( $this->data->comment_agent ) ? $this->data->comment_agent : null;
				},
				'authorIp'           => function () {
					return ! empty( $this->data->comment_author_IP ) ? $this->data->comment_author_IP : null;
				},
				'commentAuthor'      => function () {
					return ! empty( $this->data->comment_author ) ? $this->data->comment_author : null;
				},
				'commentAuthorEmail' => function () {
					return current_user_can( 'moderate_comments' ) && ! empty( $this->data->comment_author_email ) ? $this->data->comment_author_email : null;
				},
				'commentAuthorUrl'   => function () {
					return ! empty( $this->data->comment_author_url ) ? $this->data->comment_author_url : null;
				},
				'comment_post_ID'    => function () {
					return ! empty( $this->data->comment_post_ID ) ? absint( $this->data->comment_post_ID ) : null;
				},
				'contentRaw'         => function () {
					return ! empty( $this->data->comment_content ) ? $this->data->comment_content : null;
				},
				'contentRendered'    => function () {
					$content = ! empty( $this->data->comment_content ) ? $this->data->comment_content : null;

					return $this->html_entity_decode( apply_filters( 'comment_text', $content, $this->data ), 'contentRendered', false );
				},
				'databaseId'         => function () {
					return ! empty( $this->data->comment_ID ) ? (int) $this->data->comment_ID : 0;
				},
				'date'               => function () {
					return ! empty( $this->data->comment_date ) ? $this->data->comment_date : null;
				},
				'dateGmt'            => function () {
					return ! empty( $this->data->comment_date_gmt ) ? $this->data->comment_date_gmt : null;
				},
				'id'                 => function () {
					return ! empty( $this->databaseId ) ? Relay::toGlobalId( 'comment', (string) $this->databaseId ) : null;
				},
				'karma'              => function () {
					return ! empty( $this->data->comment_karma ) ? $this->data->comment_karma : null;
				},
				'link'               => function () {
					$link = get_comment_link( $this->data );

					return ! empty( $link ) ? urldecode( $link ) : null;
				},
				'parentDatabaseId'   => function () {
					return ! empty( $this->data->comment_parent ) ? absint( $this->data->comment_parent ) : 0;
				},
				'parentId'           => function () {
					return ! empty( $this->parentDatabaseId ) ? Relay::toGlobalId( 'comment', (string) $this->parentDatabaseId ) : null;
				},
				'status'             => function () {
					if ( ! is_numeric( $this->data->comment_approved ) ) {
						return $this->data->comment_approved;
					}

					return '1' === $this->data->comment_approved ? 'approve' : 'hold';
				},
				'type'               => function () {
					return ! empty( $this->data->comment_type ) ? $this->data->comment_type : null;
				},
				'uri'                => function () {
					$uri = $this->link;

					return ! empty( $uri ) ? str_ireplace( home_url(), '', $uri ) : null;
				},
				'userId'             => function () {
					return ! empty( $this->data->user_id ) ? absint( $this->data->user_id ) : null;
				},

				// Aliases.
				'comment_author'     => function () {
					return $this->commentAuthor;
				},
				'comment_author_url' => function () {
					return $this->commentAuthorUrl;
				},
				'comment_ID'         => function () {
					return $this->databaseId;
				},
				'comment_parent_id'  => function () {
					return $this->parentDatabaseId;
				},

				// Deprecated fields.
				'approved'           => function () {
					_doing_it_wrong( __METHOD__, 'The approved field is deprecated in favor of `status`', '1.13.0' );
					return ! empty( $this->data->comment_approved ) && 'hold' !== $this->data->comment_approved;
				},
				'commentId'          => function () {
					return $this->databaseId;
				},
			];
		}
	}
}
