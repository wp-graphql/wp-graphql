<?php
namespace WPGraphQL\Data\Cursor;

use WP_Term;

class TermObjectCursor extends AbstractCursor {

	/**
	 * @var ?\WP_Term ;
	 */
	public $cursor_node;

	/**
	 * Counter for meta value joins
	 *
	 * @var integer
	 */
	public $meta_join_alias = 0;

	/**
	 * @param string $name The name of the query var to get
	 *
	 * @deprecated 1.9.0
	 *
	 * @return mixed|null
	 */
	public function get_query_arg( string $name ) {
		_deprecated_function( __METHOD__, '1.9.0', self::class . '::get_query_var()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return $this->get_query_var( $name );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return ?\WP_Term ;
	 */
	public function get_cursor_node() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		$term = WP_Term::get_instance( $this->cursor_offset );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * @return ?\WP_Term ;
	 * @deprecated 1.9.0
	 */
	public function get_cursor_term() {
		_deprecated_function( __METHOD__, '1.9.0', self::class . '::get_cursor_node()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return $this->cursor_node;
	}

	/**
	 * Build and return the SQL statement to add to the Query
	 *
	 * @param array|null $fields The fields from the CursorBuilder to convert to SQL
	 *
	 * @return string
	 */
	public function to_sql( $fields = null ) {
		$sql = $this->builder->to_sql( $fields );
		if ( empty( $sql ) ) {
			return '';
		}
		return ' AND ' . $sql;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_where() {
		// If we have a bad cursor, just skip.
		if ( ! $this->is_valid_offset_and_node() ) {
			return '';
		}

		$orderby = $this->get_query_var( 'orderby' );
		$order   = $this->get_query_var( 'order' );

		if ( ! empty( $orderby ) && is_string( $orderby ) ) {

			/**
			 * If $orderby is just a string just compare with it directly as DESC
			 */
			$this->compare_with( $orderby, $order );

		}

		$this->builder->add_field( 't.term_id', $this->cursor_offset, 'ID' );

		return $this->to_sql();
	}

	/**
	 * Get AND operator for given order by key
	 *
	 * @param string $by    The order by key
	 * @param string $order The order direction ASC or DESC
	 *
	 * @return void
	 */
	private function compare_with( string $by, string $order ) {

		$value = $this->cursor_node->{$by};

		/**
		 * Compare by the term field if the key matches an value
		 */
		if ( ! empty( $value ) ) {

			if ( '>' === $this->compare ) {
				$order = 'DESC';
			} else {
				$order = 'ASC';
			}

			$this->builder->add_field( "{$by}", $value, null, $order );

			return;
		}

		/**
		 * Find out whether this is a meta key based ordering
		 */
		$meta_key = $this->get_meta_key( $by );
		if ( $meta_key ) {
			$this->compare_with_meta_field( $meta_key, $order );

			return;
		}

	}

	/**
	 * Compare with meta key field
	 *
	 * @param string $meta_key meta key
	 * @param string $order    The comparison string
	 *
	 * @return void
	 */
	private function compare_with_meta_field( string $meta_key, string $order ) {
		$meta_type  = $this->get_query_var( 'meta_type' );
		$meta_value = get_term_meta( $this->cursor_offset, $meta_key, true );

		$key = "{$this->wpdb->termmeta}.meta_value";

		/**
		 * WP uses mt1, mt2 etc. style aliases for additional meta value joins.
		 */
		if ( 0 !== $this->meta_join_alias ) {
			$key = "mt{$this->meta_join_alias}.meta_value";

		}

		$this->meta_join_alias ++;

		$this->builder->add_field( $key, $meta_value, $meta_type, $order, $this );
	}

	/**
	 * Get the actual meta key if any
	 *
	 * @param string $by The order by key
	 *
	 * @return string|null
	 */
	private function get_meta_key( string $by ) {

		if ( 'meta_value' === $by || 'meta_value_num' === $by ) {
			return $this->get_query_var( 'meta_key' );
		}

		/**
		 * Check for the WP 4.2+ style meta clauses
		 * https://make.wordpress.org/core/2015/03/30/query-improvements-in-wp-4-2-orderby-and-meta_query/
		 */
		if ( ! isset( $this->query_vars['meta_query'][ $by ] ) ) {
			return null;
		}

		$clause = $this->query_vars['meta_query'][ $by ];

		return empty( $clause['key'] ) ? null : $clause['key'];
	}

}
