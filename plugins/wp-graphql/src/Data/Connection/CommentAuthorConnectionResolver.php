<?php

namespace WPGraphQL\Data\Connection;

/**
 * Connection resolver for guest CommentAuthor nodes.
 *
 * CommentAuthor models are backed by WP_Comment rows where user_id is 0.
 *
 * @package WPGraphQL\Data\Connection
 * @extends \WPGraphQL\Data\Connection\CommentConnectionResolver
 */
class CommentAuthorConnectionResolver extends CommentConnectionResolver {

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'comment_author';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string,mixed> $args The GraphQL input args.
	 *
	 * @return array<string,mixed>
	 */
	protected function prepare_query_args( array $args ): array {
		$query_args = parent::prepare_query_args( $args );

		// CommentAuthor is only for guest (non-user) comment authors.
		$query_args['user_id'] = 0;

		return $query_args;
	}
}
