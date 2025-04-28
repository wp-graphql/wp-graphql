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
	 * @var int
	 */
	public $meta_join_alias = 0;

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $id_key = 't.term_id';

	/**
	 * Deprecated in favor of get_query_var()
	 *
	 * @param string $name The name of the query var to get
	 *
	 * @deprecated 1.9.0
	 *
	 * @return mixed|null
	 */
	public function get_query_arg( string $name ) {
		_deprecated_function( __METHOD__, '1.9.0', self::class . '::get_query_var()' );

		return $this->get_query_var( $name );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return ?\WP_Term ;
	 */
	public function get_cursor_node() {
		// Bail if no offset.
		if ( ! $this->cursor_offset ) {
			return null;
		}

		/**
		 * If pre-hooked, return filtered node.
		 *
		 * @param \WP_Term|null                           $pre_term The pre-filtered term node.
		 * @param int                                     $offset   The cursor offset.
		 * @param \WPGraphQL\Data\Cursor\TermObjectCursor $node     The cursor instance.
		 *
		 * @return \WP_Term|null
		 */
		$pre_term = apply_filters( 'graphql_pre_term_cursor_node', null, $this->cursor_offset, $this );
		if ( null !== $pre_term ) {
			return $pre_term;
		}

		// Get cursor node.
		$term = WP_Term::get_instance( $this->cursor_offset );

		return $term instanceof WP_Term ? $term : null;
	}

	/**
	 * Deprecated in favor of get_cursor_node().
	 *
	 * @return ?\WP_Term
	 * @deprecated 1.9.0
	 */
	public function get_cursor_term() {
		_deprecated_function( __METHOD__, '1.9.0', self::class . '::get_cursor_node()' );

		return $this->cursor_node;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<array<string,mixed>>[]|null $fields The fields from the CursorBuilder to convert to SQL.
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

		if ( 'name' === $orderby ) {
			if ( '>' === $this->compare ) {
				$order         = 'DESC';
				$this->compare = '<';
			} elseif ( '<' === $this->compare ) {
				$this->compare = '>';
				$order         = 'ASC';
			}
		}

		/**
		 * If $orderby is just a string just compare with it directly as DESC
		 */
		if ( ! empty( $orderby ) && is_string( $orderby ) ) {
			$this->compare_with( $orderby, $order );
		}

		/**
		 * If there's no orderby specified yet, compare with the following fields.
		 */
		if ( ! $this->builder->has_fields() ) {
			$this->compare_with_cursor_fields();
		}

		/**
		 * Stabilize cursor by consistently comparing with the ID.
		 */
		$this->compare_with_id_field();

		return $this->to_sql();
	}

	/**
	 * Get AND operator for given order by key
	 *
	 * @param string $by    The order by key
	 * @param string $order The order direction ASC or DESC
	 */
	private function compare_with( string $by, string $order ): void {

		// Bail early, if "key" and "value" provided in query_vars.
		$key   = $this->get_query_var( "graphql_cursor_compare_by_{$by}_key" );
		$value = $this->get_query_var( "graphql_cursor_compare_by_{$by}_value" );
		if ( ! empty( $key ) && ! empty( $value ) ) {
			$this->builder->add_field( $key, $value, null, $order );
			return;
		}

		// Set "key" as term table column and get "value" from cursor node.
		$key   = "t.{$by}";
		$value = $this->cursor_node->{$by};

		/**
		 * If key or value are null, check whether this is a meta key based ordering before bailing.
		 */
		if ( null === $value ) {
			$meta_key = $this->get_meta_key( $by );
			if ( $meta_key ) {
				$this->compare_with_meta_field( $meta_key, $order );
			}
			return;
		}

		$this->builder->add_field( $key, $value, null, $order );
	}

	/**
	 * Compare with meta key field
	 *
	 * @param string $meta_key meta key
	 * @param string $order    The comparison string
	 */
	private function compare_with_meta_field( string $meta_key, string $order ): void {
		$meta_type  = $this->get_query_var( 'meta_type' );
		$meta_value = get_term_meta( $this->cursor_offset, $meta_key, true );

		$key = "{$this->wpdb->termmeta}.meta_value";

		/**
		 * WP uses mt1, mt2 etc. style aliases for additional meta value joins.
		 */
		if ( 0 !== $this->meta_join_alias ) {
			$key = "mt{$this->meta_join_alias}.meta_value";
		}

		++$this->meta_join_alias;

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
