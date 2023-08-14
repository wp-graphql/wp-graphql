<?php

namespace WPGraphQL\Data\Cursor;

use wpdb;

/**
 * Abstract Cursor
 *
 * @package WPGraphQL\Data\Loader
 * @since 1.9.0
 */
abstract class AbstractCursor {
		/**
	 * The global WordPress Database instance
	 *
	 * @var \wpdb $wpdb
	 */
	public $wpdb;

	/**
	 * @var \WPGraphQL\Data\Cursor\CursorBuilder
	 */
	public $builder;

	/**
	 * @var string
	 */
	public $compare;

	/**
	 * Our current cursor offset.
	 * For example, the term, post, user, or comment ID.
	 *
	 * @var int
	 */
	public $cursor_offset;

	/**
	 * @var string|null
	 */
	public $cursor;

	/**
	 * The WP object instance for the cursor.
	 *
	 * @var mixed
	 */
	public $cursor_node;

	/**
	 * Copy of query vars so we can modify them safely
	 *
	 * @var array
	 */
	public $query_vars = [];

	/**
	 * The constructor
	 *
	 * @param array $query_vars
	 * @param string|null $cursor
	 */
	public function __construct( $query_vars, $cursor = 'after' ) {
		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->query_vars = $query_vars;
		$this->cursor     = $cursor;

		/**
		 * Get the cursor offset if any
		 */
		$offset = $this->get_query_var( 'graphql_' . $cursor . '_cursor' );

		// Handle deprecated use of `graphql_cursor_offset`.
		if ( empty( $offset ) ) {
			$offset = $this->get_query_var( 'graphql_cursor_offset' );

			if ( ! empty( $offset ) ) {
				_doing_it_wrong( self::class . "::get_query_var('graphql_cursor_offset')", "Use 'graphql_before_cursor' or 'graphql_after_cursor' instead.", '1.9.0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		$this->cursor_offset = ! empty( $offset ) ? absint( $offset ) : 0;

		// Get the WP Object for the cursor.
		$this->cursor_node = $this->get_cursor_node();

		// Get the direction for the builder query.
		$this->compare = $this->get_cursor_compare();

		$this->builder = new CursorBuilder( $this->compare );
	}

	/**
	 * Get the query variable for the provided name.
	 *
	 * @param string $name .
	 *
	 * @return mixed|null
	 */
	public function get_query_var( string $name ) {
		return ! empty( $this->query_vars[ $name ] ) ? $this->query_vars[ $name ] : null;
	}

	/**
	 * Get the direction pagination is going in.
	 *
	 * @return string
	 */
	public function get_cursor_compare() {
		if ( 'before' === $this->cursor ) {
			return '>';
		}

		return '<';
	}

	/**
	 * Ensure the cursor_offset is a positive integer and we have a valid object for our cursor node.
	 *
	 * @return bool
	 */
	protected function is_valid_offset_and_node() {
		if (
			! is_int( $this->cursor_offset ) ||
			0 >= $this->cursor_offset ||
			! $this->cursor_node
		) {
			return false;
		}

		return true;
	}

	/**
	 * Get the WP Object instance for the cursor.
	 *
	 * This is cached internally so it should not generate additionl queries.
	 *
	 * @return mixed|null;
	 */
	abstract public function get_cursor_node();

	/**
	 * Return the additional AND operators for the where statement
	 *
	 * @return string
	 */
	abstract public function get_where();

	/**
	 * Generate the final SQL string to be appended to WHERE clause
	 *
	 * @return string
	 */
	abstract public function to_sql();

}
