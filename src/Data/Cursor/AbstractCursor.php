<?php

namespace WPGraphQL\Data\Cursor;

use GraphQL\Error\InvariantViolation;

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
	 * Stores SQL statement alias for the ID column applied to the cutoff
	 *
	 * @var string
	 */
	protected $id_key = '';

	/**
	 * The constructor
	 *
	 * @param array       $query_vars         Query variable for the query to be executed.
	 * @param string|null $cursor             Cursor type. Either 'after' or 'before'.
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
				_doing_it_wrong( self::class . "::get_query_var('graphql_cursor_offset')", "Use 'graphql_before_cursor' or 'graphql_after_cursor' instead.", '1.9.0' );
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
		if ( isset( $this->query_vars[ $name ] ) && '' !== $this->query_vars[ $name ] ) {
			return $this->query_vars[ $name ];
		}
		return null;
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
	 * Validates cursor compare field configuration. Validation failure results in a fatal
	 * error because query execution is guaranteed to fail.
	 *
	 * @param array|mixed $field  Threshold configuration.
	 *
	 * @throws \GraphQL\Error\InvariantViolation Invalid configuration format.
	 *
	 * @return void
	 */
	protected function validate_cursor_compare_field( $field ): void {
		// Throw if an array not provided.
		if ( ! is_array( $field ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
						/* translators: %1$s: Cursor class name. %2$s: value type. */
						__( 'Invalid value provided for %1$s cursor compare field. Expected Array, %2$s given.', 'wp-graphql' ),
						static::class,
						gettype( $field )
					)
				)
			);
		}

		// Guard against missing or invalid "table column".
		if ( empty( $field['key'] ) || ! is_string( $field['key'] ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
						/* translators: %s: Cursor class name. */
						__( 'Expected "key" value to be provided for %s cursor compare field. A string value must be given.', 'wp-graphql' ),
						static::class
					)
				)
			);
		}

		// Guard against missing or invalid "by".
		if ( ! isset( $field['value'] ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
						/* translators: %s: Cursor class name. */
						__( 'Expected "value" value to be provided for %s cursor compare field. A scalar value must be given.', 'wp-graphql' ),
						static::class
					)
				)
			);
		}

		// Guard against invalid "type".
		if ( ! empty( $field['type'] ) && ! is_string( $field['type'] ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
					/* translators: %s: Cursor class name. */
						__( 'Invalid value provided for "type" value to be provided for type of %s cursor compare field. A string value must be given.', 'wp-graphql' ),
						static::class
					)
				)
			);
		}

		// Guard against invalid "order".
		if ( ! empty( $field['order'] ) && ! in_array( strtoupper( $field['order'] ), [ 'ASC', 'DESC' ], true ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
					/* translators: %s: Cursor class name. */
						__( 'Invalid value provided for "order" value to be provided for type of %s cursor compare field. Either "ASC" or "DESC" must be given.', 'wp-graphql' ),
						static::class
					)
				)
			);
		}
	}

	/**
	 * Returns the ID key.
	 *
	 * @return mixed
	 */
	public function get_cursor_id_key() {
		$key = $this->get_query_var( 'graphql_cursor_id_key' );
		if ( null === $key ) {
			$key = $this->id_key;
		}

		return $key;
	}

	/**
	 * Applies cursor compare fields to the cursor cutoff.
	 *
	 * @param array $fallback  Fallback cursor compare fields.
	 *
	 * @throws \GraphQL\Error\InvariantViolation Invalid configuration format.
	 */
	protected function compare_with_cursor_fields( $fallback = [] ): void {
		/**
		 * Get cursor compare fields from query vars.
		 *
		 * @var array|null $cursor_compare_fields
		 */
		$cursor_compare_fields = $this->get_query_var( 'graphql_cursor_compare_fields' );
		if ( null === $cursor_compare_fields ) {
			$cursor_compare_fields = $fallback;
		}
		// Bail early if no cursor compare fields.
		if ( empty( $cursor_compare_fields ) ) {
			return;
		}

		if ( ! is_array( $cursor_compare_fields ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
						/* translators: %s: value type. */
						__( 'Invalid value provided for graphql_cursor_compare_fields. Expected Array, %s given.', 'wp-graphql' ),
						gettype( $cursor_compare_fields )
					)
				)
			);
		}

		// Check if only one cursor compare field provided, wrap it in an array.
		if ( ! isset( $cursor_compare_fields[0] ) ) {
			$cursor_compare_fields = [ $cursor_compare_fields ];
		}

		foreach ( $cursor_compare_fields as $field ) {
			$this->validate_cursor_compare_field( $field );

			$key   = $field['key'];
			$value = $field['value'];
			$type  = ! empty( $field['type'] ) ? $field['type'] : null;
			$order = ! empty( $field['order'] ) ? $field['order'] : null;

			$this->builder->add_field( $key, $value, $type, $order );
		}
	}

	/**
	 * Applies ID field to the cursor builder.
	 */
	protected function compare_with_id_field(): void {
		// Get ID value.
		$value = $this->get_query_var( 'graphql_cursor_id_value' );
		if ( null === $value ) {
			$value = (string) $this->cursor_offset;
		}

		// Get ID SQL Query alias.
		$key = $this->get_cursor_id_key();

		$this->builder->add_field( $key, $value, 'ID' );
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
