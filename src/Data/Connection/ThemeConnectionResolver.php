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
	 * @var string[]
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
		return [
			'allowed' => null,
		];
	}


	/**
	 * Get the items from the source
	 *
	 * @return string[]
	 */
	public function get_query() {
		$query_args = $this->query_args;

		return array_keys( wp_get_themes( $query_args ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_loader_name() {
		return 'theme';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ) {
		$theme = wp_get_theme( $offset );
		return $theme->exists();
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_execute() {
		return true;
	}
}
