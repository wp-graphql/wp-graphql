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
	protected function prepare_query_args( array $args ): array {
		// If any args are added to filter/sort the connection
		return [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string[]
	 */
	public function get_query() {
		$query_args = $this->get_query_args();

		if ( isset( $query_args['contentTypeNames'] ) && is_array( $query_args['contentTypeNames'] ) ) {
			return $query_args['contentTypeNames'];
		}

		if ( isset( $query_args['name'] ) ) {
			return [ $query_args['name'] ];
		}

		return \WPGraphQL::get_allowed_post_types( 'names', $query_args );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
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
}
