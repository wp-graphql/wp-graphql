<?php

namespace WPGraphQL\Data\Connection;

use WPGraphQL\Model\User;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since   0.0.5
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<string[]>
 */
class UserRoleConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {

		// Given a list of role slugs
		$query_args = $this->get_query_args();
		if ( isset( $query_args['slugIn'] ) ) {
			return $query_args['slugIn'];
		}

		$ids     = [];
		$queried = $this->get_query();

		if ( empty( $queried ) ) {
			return $ids;
		}

		foreach ( $queried as $key => $item ) {
			$ids[ $key ] = $item;
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepare_query_args( array $args ): array {
		// If any args are added to filter/sort the connection
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function query( array $query_args ) {
		$wp_roles = wp_roles();

		return ! empty( $wp_roles->get_names() ) ? array_keys( $wp_roles->get_names() ) : [];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'user_role';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ) {
		return (bool) get_role( $offset );
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_execute() {
		if (
			current_user_can( 'list_users' ) ||
			(
				$this->source instanceof User &&
				get_current_user_id() === $this->source->databaseId
			)
		) {
			return true;
		}

		return false;
	}
}
