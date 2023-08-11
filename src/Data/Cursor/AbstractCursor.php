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
	 * @param string      $id_key             .
	 * @param string|null $cursor             Cursor type. Either 'after' or 'before'.
	 * @param array       $initial_threshold  
	 */
	public function __construct( $query_vars, $cursor = 'after', $initial_threshold = [] ) {
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
		return isset( $this->query_vars[ $name ] ) ? $this->query_vars[ $name ] : null;
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
	 * Validates threshold field configuration. Validation failure results in a fatal
	 * error because query execution is guaranteed to fail.
	 *
	 * @param array $field  Threshold configuration.
	 * 
	 * @throws InvariationViolation Invalid configuration format.
	 * 
	 * @return void
	 */
	protected function is_valid_threshold_field( $field ) {
		$class = get_called_class();
		// Throw if an array not provided.
		if ( ! is_array( $field ) ) {
			$type  = gettype( $field );
			throw new InvariantViolation( __( "Invalid value provided for {$class} threshold field. Expected Array, ${type} given.", 'ql-events' ) );
		}

		// Guard against missing or invalid "table column".
		if ( empty( $field['key'] ) || ! is_string( $field['key'] ) ) {
			throw new InvariantViolation( __( "Expected \"key\" value to be provided for {$class} threshold field. A string value must be given.", 'ql-events' ) );
		}

		// Guard against missing or invalid "by".
		if ( empty( $field['value'] ) ) {
			throw new InvariantViolation( __( "Expected \"value\" value to be provided for {$class} threshold field. A scalar value must be given.", 'ql-events' ) );
		}
		
		// Guard against invalid "type".
		if ( ! empty( $field['type'] ) && ! is_string( $field['type'] ) ) {
			throw new InvariantViolation( __( "Invalid value provided for \"by\" value to be provided for type of {$class} threshold field. A string value must be given.", 'ql-events' ) );
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
	 * Applies threshold fields to the cursor cutoff.
	 * 
	 * @param array $fallback  Default threshold fields.
	 *
	 * @return void
	 */
	protected function compare_with_threshold_fields( $fallback = [] ) {
		$threshold_fields = $this->get_query_var( 'graphql_cursor_threshold_fields' );
		if ( null === $threshold_fields ) {
			$threshold_fields = $fallback;
		}

		// Check if only one threshold field provided, wrap it in an array.
		if ( ! empty( $threshold_fields ) && is_array( $threshold_fields ) && ! isset( $threshold_fields[0] ) ) {
			$threshold_fields = [ $threshold_fields ];
		}

		foreach ( $this->threshold_fields as $field ) {
			$this->is_valid_threshold_field( $field );

			$key   = $field['key'];
			$value = $field['value'];
			$type  = ! empty( $field['type'] ) ? $field['type'] : null;

			$this->builder->add_field( $key, $value, $type );
		}
	}

	/**
	 * Applies ID field to the cursor builder.
	 *
	 * @return void
	 */
	protected function compare_with_id_field() {
		$value = $this->get_query_var( 'graphql_cursor_id_value' );
		if ( null === $value ) {
			$value = (string) $this->cursor_offset;
		}

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
