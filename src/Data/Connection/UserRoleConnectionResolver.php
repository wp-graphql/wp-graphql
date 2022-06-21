<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\User;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since   0.0.5
 */
class UserRoleConnectionResolver extends AbstractConnectionResolver {

	/**
	 * UserRoleConnectionResolver constructor.
	 *
	 * @param mixed       $source     source passed down from the resolve tree
	 * @param array       $args       array of arguments input in the field as part of the GraphQL query
	 * @param AppContext  $context    Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info       Info about fields passed down the resolve tree
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * @return array
	 */
	public function get_ids_from_query() {

		// Given a list of role slugs
		if ( isset( $this->query_args['slugIn'] ) ) {
			return $this->query_args['slugIn'];
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
	 * @return array
	 */
	public function get_query_args() {
		// If any args are added to filter/sort the connection
		return [];
	}

	/**
	 * @return array|mixed
	 */
	public function get_query() {
		$wp_roles = wp_roles();
		$roles    = ! empty( $wp_roles->get_names() ) ? array_keys( $wp_roles->get_names() ) : [];

		return $roles;
	}

	/**
	 * @return string
	 */
	public function get_loader_name() {
		return 'user_role';
	}

	/**
	 * @param mixed $offset Whether the provided offset is valid for the connection
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return get_role( $offset ) instanceof \WP_Role;
	}

	/**
	 * @return bool
	 */
	public function should_execute() {

		if (
			current_user_can( 'list_users' ) ||
			(
				$this->source instanceof User &&
				get_current_user_id() === $this->source->userId
			)
		) {
			return true;
		}

		return false;
	}

}
