<?php
namespace WPGraphQL\Data\Cursor;

use WP_Term_Query;
use wpdb;

class TermObjectCursor {

	/**
	 * The global WordPress Database instance
	 *
	 * @var wpdb $wpdb
	 */
	public $wpdb;

	/**
	 * The WP_Query instance
	 *
	 * @var WP_Term_Query $query
	 */
	public $query;

	/**
	 * The current term id which is our cursor offset
	 *
	 * @var int $cursor_offset
	 */
	public $cursor_offset;

	/**
	 * @var CursorBuilder
	 */
	public $builder;

	/**
	 * Counter for meta value joins
	 *
	 * @var integer
	 */
	public $meta_join_alias = 0;

	/**
	 * @var array
	 */
	public $query_args = [];


	/**
	 * @var string|null
	 */
	public $cursor;

	/**
	 * @var string
	 */
	public $compare;

	/**
	 * TermObjectCursor constructor.
	 *
	 * @param array  $args The query args used for the WP_Term_Query
	 * @param string $cursor Whether to generate the before or after cursor. Default "after"
	 */
	public function __construct( array $args, $cursor = '' ) {

		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->query_args = $args;
		$this->cursor     = $cursor;

		/**
		 * Get the cursor offset if any
		 */
		$offset              = $this->get_query_arg( 'graphql_' . $cursor . '_cursor' );
		$this->cursor_offset = ! empty( $offset ) ? absint( $offset ) : 0;
		$compare             = ! empty( $this->get_query_arg( 'graphql_cursor_compare' ) ) ? $this->get_query_arg( 'graphql_cursor_compare' ) : '>';
		$this->compare       = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

		if ( 'before' === $cursor ) {
			$this->compare = '>';
		} elseif ( 'after' === $cursor ) {
			$this->compare = '<';
		}

		$this->builder = new CursorBuilder( $this->compare );

	}

	/**
	 * @param string $name The name of the query var to get
	 *
	 * @return mixed|null
	 */
	public function get_query_arg( string $name ) {
		return ! isset( $this->query_args[ $name ] ) ? null : $this->query_args[ $name ];
	}

	/**
	 * Return the additional AND operators for the where statement
	 *
	 * @return string|null
	 */
	public function get_where() {

		/**
		 * Ensure the cursor_offset is a positive integer
		 */
		if ( ! is_integer( $this->cursor_offset ) || 0 >= $this->cursor_offset ) {
			return '';
		}

		/**
		 * If we have bad cursor just skip...
		 */
		if ( ! $this->get_cursor_term() ) {
			return '';
		}

		$orderby = $this->get_query_arg( 'orderby' );
		$order   = $this->get_query_arg( 'order' );

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
	 * Get term instance for the cursor.
	 *
	 * This is cached internally so it does not generate extra queries
	 *
	 * @return mixed \WP_Term|null
	 */
	public function get_cursor_term() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		$term = \WP_Term::get_instance( $this->cursor_offset );
		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * Build and return the SQL statement to add to the Query
	 *
	 * @param array|null $fields The fields from the CursorBuilder to convert to SQL
	 *
	 * @return string|null
	 */
	public function to_sql( $fields = null ) {
		$sql = $this->builder->to_sql( $fields );
		if ( empty( $sql ) ) {
			return null;
		}
		return ' AND ' . $sql;
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

		$value = $this->get_cursor_term()->{$by};

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
		$meta_type  = $this->get_query_arg( 'meta_type' );
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
			return $this->get_query_arg( 'meta_key' );
		}

		/**
		 * Check for the WP 4.2+ style meta clauses
		 * https://make.wordpress.org/core/2015/03/30/query-improvements-in-wp-4-2-orderby-and-meta_query/
		 */
		if ( ! isset( $this->query_args['meta_query'][ $by ] ) ) {
			return null;
		}

		$clause = $this->query_args['meta_query'][ $by ];

		return empty( $clause['key'] ) ? null : $clause['key'];
	}

}
