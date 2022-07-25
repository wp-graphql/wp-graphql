<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class ThemeConnectionResolver
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.5.0
 */
class ThemeConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 *
	 * @var array
	 */
	protected $query;

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		$ids     = [];
		$queried = ! empty( $this->query ) ? $this->query : [];

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
	public function get_query_args() {
		$query_args = [
			'allowed' => null,
		];

		return $query_args;
	}


	/**
	 * Get the items from the source
	 *
	 * @return array
	 */
	public function get_query() {
		$query_args = $this->query_args;

		return array_keys( wp_get_themes( $query_args ) );
	}

	/**
	 * The name of the loader to load the data
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'theme';
	}

	/**
	 * Determine if the offset used for pagination is valid
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		$theme = wp_get_theme( $offset );
		return $theme->exists();
	}

	/**
	 * Determine if the query should execute
	 *
	 * @return bool
	 */
	public function should_execute() {
		return true;
	}

}
