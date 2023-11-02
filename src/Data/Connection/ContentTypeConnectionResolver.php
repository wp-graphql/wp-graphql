<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class ContentTypeConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class ContentTypeConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 *
	 * @var string[]
	 */
	protected $query;

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		$ids     = [];
		$queried = $this->query;

		if ( empty( $queried ) ) {
			return $ids;
		}

		foreach ( $queried as $item ) {
			$ids[] = $item;
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_query_args() {
		// If any args are added to filter/sort the connection
		return [];
	}


	/**
	 * {@inheritDoc}
	 *
	 * @return string[]
	 */
	public function get_query() {
		if ( isset( $this->query_args['contentTypeNames'] ) && is_array( $this->query_args['contentTypeNames'] ) ) {
			return $this->query_args['contentTypeNames'];
		}

		if ( isset( $this->query_args['name'] ) ) {
			return [ $this->query_args['name'] ];
		}

		$query_args = $this->query_args;
		return \WPGraphQL::get_allowed_post_types( 'names', $query_args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_loader_name() {
		return 'post_type';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $offset The offset (post type name) to check.
	 */
	public function is_valid_offset( $offset ) {
		return (bool) get_post_type_object( $offset );
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_execute() {
		return true;
	}
}
