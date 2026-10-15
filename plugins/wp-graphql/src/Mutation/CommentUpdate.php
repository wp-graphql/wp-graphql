<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\CommentMutation;
use WPGraphQL\Utils\Utils;

/**
 * Class CommentUpdate
 *
 * @package WPGraphQL\Mutation
 */
class CommentUpdate {
	/**
	 * Registers the CommentUpdate mutation.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'updateComment',
			[
				'inputFields'         => self::get_input_fields(),
				'outputFields'        => self::get_output_fields(),
				'mutateAndGetPayload' => self::mutate_and_get_payload(),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_input_fields() {
		return array_merge(
			CommentCreate::get_input_fields(),
			[
				'id' => [
					'type'        => [
						'non_null' => 'ID',
					],
					'description' => static function () {
						return __( 'The ID of the comment being updated.', 'wp-graphql' );
					},
				],
			]
		);
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_output_fields() {
		return CommentCreate::get_output_fields();
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable(array<string,mixed>$input,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):array<string,mixed>
	 */
	public static function mutate_and_get_payload() {
		return static function ( $input, AppContext $context, ResolveInfo $info ) {
			/**
			 * A caller who is not logged in can neither moderate comments nor own one, so they can
			 * never pass the check below. Rejecting them before the comment is looked up means they
			 * get the same answer for every ID and cannot use the mutation to find out which
			 * comment IDs exist, including unapproved ones.
			 *
			 * @see https://github.com/wp-graphql/wp-graphql/security/advisories/GHSA-r7m4-3v6c-3435
			 */
			if ( ! is_user_logged_in() ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to update this comment.', 'wp-graphql' ) );
			}

			$can_moderate = current_user_can( 'moderate_comments' );

			// Get the database ID for the comment.
			$comment_id = ! empty( $input['id'] ) ? Utils::get_database_id_from_id( $input['id'] ) : null;

			// Get the args from the existing comment.
			$comment_args = ! empty( $comment_id ) ? get_comment( $comment_id, ARRAY_A ) : null;

			if ( empty( $comment_id ) || empty( $comment_args ) ) {
				/**
				 * Someone who may moderate comments is entitled to know that an ID holds nothing,
				 * and the message is more useful to them than a refusal would be.
				 *
				 * Everyone else gets the same answer here as they would for a comment that exists
				 * but is not theirs. Ownership cannot be checked before the lookup, the way the
				 * capability can for the media item mutations, so the two outcomes are made
				 * identical instead. Otherwise the pair of messages tells any logged-in caller
				 * which comment IDs exist, including unapproved ones.
				 *
				 * @see https://github.com/wp-graphql/wp-graphql/security/advisories/GHSA-r7m4-3v6c-3435
				 */
				throw new UserError(
					$can_moderate
						? esc_html__( 'The Comment could not be updated', 'wp-graphql' )
						: esc_html__( 'Sorry, you are not allowed to update this comment.', 'wp-graphql' )
				);
			}

			$user_id          = $comment_args['user_id'] ?? null;
			$raw_comment_args = $comment_args;

			// Prevent comment updates by default.
			$not_allowed = true;

			// If the current user can moderate comments, allow.
			if ( $can_moderate ) {
				$not_allowed = false;
			} else {
				// If the current user is the comment author, allow (but not for status changes).
				$current_user_id = absint( get_current_user_id() );
				if ( 0 !== $current_user_id && absint( $user_id ) === $current_user_id ) {
					/**
					 * Filters whether the author of a comment may update it without the
					 * `moderate_comments` capability.
					 *
					 * WPGraphQL lets people edit their own comments. WordPress does not: its
					 * `edit_comment` capability maps to `edit_post` on the post the comment was
					 * left on, so editing any comment in the admin or through the REST API
					 * requires being able to edit that post. Return false to turn that off,
					 * which leaves `updateComment` to callers who can moderate comments. Note
					 * that this is stricter than WordPress's own rule rather than the same as
					 * it: an author who can edit a post can edit comments left on it in the
					 * admin, but would not be able to here.
					 *
					 * @param bool                $can_update   Whether the comment's author may update it. Default true.
					 * @param array<string,mixed> $comment_args The comment as it is currently stored.
					 * @param array<string,mixed> $input        The input for the mutation.
					 *
					 * @since 2.24.1
					 * @hookGroup authentication
					 */
					$not_allowed = ! apply_filters( 'graphql_comment_author_can_update_own_comment', true, $raw_comment_args, $input );
				}
			}

			if ( true === $not_allowed ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to update this comment.', 'wp-graphql' ) );
			}

			// Only users with moderate_comments may change moderation status (status/approved).
			$attempting_status_change = isset( $input['status'] ) || array_key_exists( 'approved', $input );
			if ( $attempting_status_change && ! $can_moderate ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to change the moderation status of this comment.', 'wp-graphql' ) );
			}

			// A caller without moderation rights may edit what their comment says, nothing else.
			if ( ! $can_moderate ) {
				self::assert_only_content_changes( $input, $raw_comment_args );
			}

			/**
			 * Map all of the args from GraphQL to WordPress friendly args array
			 */
			CommentMutation::prepare_comment_object( $input, $comment_args, 'update', true );

			// Keep the stored byline. prepare_comment_object() back-fills `author`,
			// `authorEmail` and `authorUrl` from the caller's own account when they are
			// left out of the input, and writes those columns either way, so an update
			// sending only `content` would otherwise rewrite who the comment appears to
			// have been written by. assert_only_content_changes() cannot catch that,
			// because it only inspects values the caller actually sent.
			//
			// Done before the no-changes check below so that resubmitting nothing but the
			// stored byline is still correctly reported as no change.
			if ( ! $can_moderate ) {
				self::keep_stored_author( $comment_args, $raw_comment_args );
			}

			// If there are no changes between the existing comment and the incoming comment
			if ( $comment_args === $raw_comment_args ) {
				throw new UserError( esc_html__( 'No changes have been provided to the comment.', 'wp-graphql' ) );
			}

			// Edited content has not been moderated yet, whatever the comment it replaced was.
			if ( ! $can_moderate ) {
				self::maybe_hold_for_moderation( $comment_args, $raw_comment_args );
			}

			/**
			 * Update comment
			 * $success   int   1 on success and 0 on fail
			 */
			$success = wp_update_comment( $comment_args, true );

			/**
			 * Throw an exception if the comment failed to be created
			 */
			if ( is_wp_error( $success ) ) {
				throw new UserError( esc_html( $success->get_error_message() ) );
			}

			/**
			 * This updates additional data not part of the comments table ( commentmeta, other relations, etc )
			 *
			 * The input for the commentMutation will be passed, along with the $new_comment_id for the
			 * comment that was created so that relations can be set, meta can be updated, etc.
			 */
			CommentMutation::update_additional_comment_data( $comment_id, $input, 'create', $context, $info );

			/**
			 * Return the comment object
			 */
			return [
				'id'      => $comment_id,
				'success' => (bool) $success,
			];
		};
	}

	/**
	 * Asserts that a caller without the `moderate_comments` capability is changing nothing but
	 * the content of the comment they own.
	 *
	 * `UpdateCommentInput` inherits every field of `CreateCommentInput`, which means the fields
	 * that decide which node a comment belongs to, who it appears to have been written by, and
	 * when, are writable on an update. Deciding those is moderation, not authorship, so a caller
	 * who may edit their own comment may not change them. Sending a value equal to the stored one
	 * is accepted, so a client that resubmits a whole form is not rejected for fields it left
	 * alone.
	 *
	 * The restricted fields are named explicitly rather than allowlisted because
	 * `CommentMutation::prepare_comment_object()` writes a fixed set of columns. An input field an
	 * extension registers on `UpdateCommentInput` is that extension's to authorize, through the
	 * hooks it writes its own data on.
	 *
	 * @param array<string,mixed> $input        The input for the mutation.
	 * @param array<string,mixed> $comment_args The comment as it is currently stored.
	 *
	 * @throws \GraphQL\Error\UserError If a restricted field is being changed.
	 */
	private static function assert_only_content_changes( array $input, array $comment_args ): void {
		$restricted_fields = [
			'commentOn'   => 'comment_post_ID',
			'author'      => 'comment_author',
			'authorEmail' => 'comment_author_email',
			'authorUrl'   => 'comment_author_url',
			'date'        => 'comment_date',
			'parent'      => 'comment_parent',
			'type'        => 'comment_type',
		];

		foreach ( $restricted_fields as $field => $column ) {
			/**
			 * An empty value cannot name someone else, so there is nothing to refuse.
			 *
			 * `author`, `authorEmail` and `authorUrl` are not simply skipped, though:
			 * prepare_comment_object() back-fills all three from the caller's own account
			 * when they are left out, and writes those columns. So omitting them resets
			 * the comment's byline to the caller's current profile, which is their own
			 * identity rather than someone else's, but is still a change. That predates
			 * this guard and is tracked separately.
			 */
			if ( empty( $input[ $field ] ) ) {
				continue;
			}

			$incoming = (string) $input[ $field ];
			$stored   = (string) ( $comment_args[ $column ] ?? '' );

			// Normalize each value the way prepare_comment_object() does before it writes the column.
			if ( 'commentOn' === $field ) {
				$incoming = (string) absint( $incoming );
				$stored   = (string) absint( $stored );
			} elseif ( 'parent' === $field ) {
				$incoming = (string) absint( Utils::get_database_id_from_id( $input[ $field ] ) );
				$stored   = (string) absint( $stored );
			} elseif ( 'date' === $field ) {
				$timestamp = strtotime( $incoming );

				// An unparseable date is never written, so it cannot change anything.
				if ( false === $timestamp ) {
					continue;
				}

				$incoming = gmdate( 'Y-m-d H:i:s', $timestamp );
			}

			if ( $incoming !== $stored ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to change the target, author, date, parent or type of this comment.', 'wp-graphql' ) );
			}
		}
	}

	/**
	 * Restores the comment's stored author columns before it is saved.
	 *
	 * `CommentMutation::prepare_comment_object()` fills `author`, `authorEmail` and
	 * `authorUrl` from the current user when the input leaves them out, then writes all
	 * three columns. For a caller who may only edit content that is a change they are not
	 * allowed to make: an update sending nothing but `content` would reset the byline to
	 * whatever is on the caller's profile now, discarding a moderator's correction or a
	 * redaction. Only the three author columns are restored, so a content edit still
	 * saves.
	 *
	 * A caller who may moderate is left alone, because changing the byline is a decision
	 * they are allowed to make.
	 *
	 * @param array<string,mixed> $comment_args     The comment args about to be saved, by reference.
	 * @param array<string,mixed> $raw_comment_args The comment as it is currently stored.
	 */
	private static function keep_stored_author( array &$comment_args, array $raw_comment_args ): void {
		$author_columns = [ 'comment_author', 'comment_author_email', 'comment_author_url' ];

		foreach ( $author_columns as $column ) {
			if ( ! array_key_exists( $column, $raw_comment_args ) ) {
				// Nothing stored to restore, so leave whatever was prepared in place.
				continue;
			}

			$comment_args[ $column ] = $raw_comment_args[ $column ];
		}
	}

	/**
	 * Re-runs WordPress's moderation decision when a caller without the `moderate_comments`
	 * capability edits the content of a comment that is already approved, holding the comment for
	 * review when the new content would not have been approved had it arrived as a new comment.
	 *
	 * Approval belongs to the content a moderator read, not to the comment row, so without this an
	 * approved comment is a slot its author can refill with content nobody has reviewed.
	 *
	 * @param array<string,mixed> $comment_args     The comment args about to be saved, passed by reference.
	 * @param array<string,mixed> $raw_comment_args The comment as it is currently stored.
	 */
	private static function maybe_hold_for_moderation( array &$comment_args, array $raw_comment_args ): void {
		// Only an edit to the content of an approved comment needs a new decision.
		if ( '1' !== (string) ( $raw_comment_args['comment_approved'] ?? '' ) ) {
			return;
		}

		if ( ( $comment_args['comment_content'] ?? null ) === ( $raw_comment_args['comment_content'] ?? null ) ) {
			return;
		}

		/**
		 * WordPress approves a comment left by the author of the node it is on without checking
		 * it, so an author editing their own comment on their own content is left alone too.
		 *
		 * @see wp_check_comment_data()
		 */
		$node_author = (int) get_post_field( 'post_author', absint( $comment_args['comment_post_ID'] ?? 0 ) );

		if ( 0 !== $node_author && absint( get_current_user_id() ) === $node_author ) {
			return;
		}

		$author     = (string) ( $comment_args['comment_author'] ?? '' );
		$email      = (string) ( $comment_args['comment_author_email'] ?? '' );
		$url        = (string) ( $comment_args['comment_author_url'] ?? '' );
		$content    = (string) ( $comment_args['comment_content'] ?? '' );
		$ip         = (string) ( $comment_args['comment_author_IP'] ?? '' );
		$user_agent = (string) ( $comment_args['comment_agent'] ?? '' );
		$type       = (string) ( $comment_args['comment_type'] ?? '' );

		$requires_moderation = ! check_comment( $author, $email, $url, $content, $ip, $user_agent, $type )
			|| wp_check_comment_disallowed_list( $author, $email, $url, $content, $ip, $user_agent );

		/**
		 * Filters whether an edited comment is sent back for moderation.
		 *
		 * When a caller without the `moderate_comments` capability edits the content of a comment
		 * that is already approved, WordPress's moderation checks are run against the new content
		 * and the comment is held for review if they do not pass. Return false to let the comment
		 * keep its approved status regardless of what the new content says.
		 *
		 * This does not run for every edit. A comment that is not already approved keeps the
		 * status it has, unchanged content is not re-checked, and the author of the node the
		 * comment was left on is exempt, the same exemption WordPress applies. In those cases
		 * no decision is made and the filter is never reached.
		 *
		 * @param bool                $requires_moderation Whether the comment will be held for review.
		 * @param array<string,mixed> $comment_args        The comment args about to be saved.
		 * @param array<string,mixed> $raw_comment_args    The comment as it is currently stored.
		 *
		 * @since 2.24.1
		 * @hookGroup models
		 */
		$requires_moderation = (bool) apply_filters( 'graphql_comment_update_requires_moderation', $requires_moderation, $comment_args, $raw_comment_args );

		if ( $requires_moderation ) {
			$comment_args['comment_approved'] = '0';
		}
	}
}
