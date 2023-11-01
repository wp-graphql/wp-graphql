<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class TaxonomyConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class TaxonomyConnectionResolver extends AbstractConnectionResolver {
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
	 * Get the items from the source
	 *
	 * @return string[]
	 */
	public function get_query() {
		if ( isset( $this->query_args['name'] ) ) {
			return [ $this->query_args['name'] ];
		}

		if ( isset( $this->query_args['in'] ) ) {
			return is_array( $this->query_args['in'] ) ? $this->query_args['in'] : [ $this->query_args['in'] ];
		}

		$query_args = $this->query_args;
		return \WPGraphQL::get_allowed_taxonomies( 'names', $query_args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_loader_name() {
		return 'taxonomy';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $offset The offset (taxonomy name) to check.
	 */
	public function is_valid_offset( $offset ) {
		return (bool) get_taxonomy( $offset );
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_execute() {
		return true;
	}
}
