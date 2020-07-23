<?php

namespace WPGraphQL\Data\Connection;

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
	 * @param $source
	 * @param $args
	 * @param $context
	 * @param $info
	 *
	 * @throws \Exception
	 */
	public function __construct( $source, $args, $context, $info ) {
		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * @return bool|int|mixed|null|string
	 */
	public function get_offset() {
		$offset = null;
		if ( ! empty( $this->args['after'] ) ) {
			$offset = substr( base64_decode( $this->args['after'] ), strlen( 'arrayconnection:' ) );
		} elseif ( ! empty( $this->args['before'] ) ) {
			$offset = substr( base64_decode( $this->args['before'] ), strlen( 'arrayconnection:' ) );
		}

		return $offset;
	}

	/**
	 * @return array
	 */
	public function get_ids() {

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
		return $this->query_args;
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
	 * @return array
	 * @throws \Exception
	 */
	public function get_nodes() {
		$nodes = parent::get_nodes();

		if ( isset( $this->args['after'] ) ) {
			$key   = array_search( $this->get_offset(), array_keys( $nodes ), true );
			$nodes = array_slice( $nodes, $key + 1, null, true );
		}

		if ( isset( $this->args['before'] ) ) {
			$nodes = array_reverse( $nodes );
			$key   = array_search( $this->get_offset(), array_keys( $nodes ), true );
			$nodes = array_slice( $nodes, $key + 1, null, true );
			$nodes = array_reverse( $nodes );
		}

		$nodes = array_slice( $nodes, 0, $this->query_amount, true );

		return ! empty( $this->args['last'] ) ? array_filter( array_reverse( $nodes, true ) ) : $nodes;
	}

	/**
	 * @return string
	 */
	public function get_loader_name() {
		return 'user_role';
	}

	/**
	 * @param $offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return true;
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
