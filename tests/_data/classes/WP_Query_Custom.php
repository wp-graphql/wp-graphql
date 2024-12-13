<?php
/**
 * WP_Query clone for testing
 *
 * @package Test\WPGraphQL
 */

/**
 * Class WP_Query_Custom
 */
class WP_Query_Custom {
	/**
	 * Stores query results.
	 *
	 * @var \WP_Query
	 */
	protected $query;

	/**
	 * WP_Query_Custom constructor.
	 *
	 * @param array $args   Query Arguments.
	 */
	public function __construct( $args = [] ) {
		$this->query = new \WP_Query(
			array_merge(
				$args,
				[ 'posts_per_page' => 3 ]
			)
		);
	}

	/**
	 * Returns the query.
	 *
	 * (This is what AbstractConnectionResolver::is_valid_query_class() checks for by default.)
	 */
	public function query( $args = [] ) {
		return $this->query;
	}

		/**
		 * Magic method to re-map the isset check on the child class looking for properties when
		 * resolving the fields
		 *
		 * @param string $key The name of the field you are trying to retrieve
		 *
		 * @return bool
		 */
	public function __isset( $key ) {
		return isset( $this->query->$key );
	}

	/**
	 * Pass thru for query instance.
	 *
	 * @param string $name  WP_Query_Custom member name.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->query->$name;
	}

		/**
		 * Forwards function calls to WP_Query instance.
		 *
		 * @param string $method - function name.
		 * @param array  $args   - function call arguments.
		 *
		 * @return mixed
		 *
		 * @throws \BadMethodCallException Method not found on WP_Query object.
		 */
	public function __call( $method, $args ) {
		if ( \is_callable( [ $this->query, $method ] ) ) {
			return $this->query->$method( ...$args );
		}

		$class = self::class;
		throw new BadMethodCallException( "Call to undefined method {$method} on the {$class}" );
	}
}
