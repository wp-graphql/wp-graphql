<?php

namespace WPGraphQL\Data\Connection;

/**
 * Class CommentConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class GuestCommenterConnectionResolver extends CommentConnectionResolver {
	/**
	 * {@inheritDoc}
	 */
	public function get_query_args() {

		$query_args = parent::get_query_args();

		$query_args['user_id'] = 0;

		return apply_filters( 'graphql_guest_commenter_connection_query_args', $query_args, $this->source, $this->args, $this->context, $this->info );
	}

	/**
	 * Return the name of the loader
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'guest_commenter';
	}
}
