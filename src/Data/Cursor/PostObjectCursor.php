<?php

namespace WPGraphQL\Data\Cursor;

use WP_Query;
use wpdb;

/**
 * Post Cursor
 *
 * This class generates the SQL AND operators for cursor based pagination for posts
 *
 * @package WPGraphQL\Data
 */
class PostObjectCursor {

	/**
	 * The global WordPress Database instance
	 *
	 * @var wpdb $wpdb
	 */
	public $wpdb;

	/**
	 * The WP_Query instance
	 *
	 * @var WP_Query $query
	 */
	public $query;

	/**
	 * The current post id which is our cursor offset
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
	 * Copy of query vars so we can modify them safely
	 *
	 * @var array
	 */
	public $query_vars = [];

	/**
	 * @var string|null
	 */
	public $cursor;

	/**
	 * @var string
	 */
	public $compare;

	/**
	 * PostCursor constructor.
	 *
	 * @param WP_Query    $query  The WP_Query instance
	 * @param string|null $cursor Whether to generate the before or after cursor. Default "after"
	 */
	public function __construct( WP_Query $query, $cursor = '' ) {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->query      = $query;
		$this->query_vars = $this->query->query_vars;
		$this->cursor     = $cursor;

		/**
		 * Get the cursor offset if any
		 */
		$offset              = $this->get_query_var( 'graphql_cursor_offset' );
		$offset              = isset( $this->query_vars[ 'graphql_' . $cursor . '_cursor' ] ) ? $this->query_vars[ 'graphql_' . $cursor . '_cursor' ] : $offset;
		$this->cursor_offset = ! empty( $offset ) ? absint( $offset ) : 0;

		$compare       = ! empty( $query->get( 'graphql_cursor_compare' ) ) ? $query->get( 'graphql_cursor_compare' ) : '>';
		$this->compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

		if ( 'before' === $cursor ) {
			$this->compare = '>';
		} elseif ( 'after' === $cursor ) {
			$this->compare = '<';
		}

		$this->builder = new CursorBuilder( $this->compare );

	}

	/**
	 * Get post instance for the cursor.
	 *
	 * This is cached internally so it does not generate extra queries
	 *
	 * @return mixed WP_Post|null
	 */
	public function get_cursor_post() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		return \WP_Post::get_instance( $this->cursor_offset );
	}

	/**
	 * @return string|null
	 */
	public function to_sql() {

		$orderby = isset( $this->query_vars['orderby'] ) ? $this->query_vars['orderby'] : null;

		$orderby_should_not_convert_to_sql = isset( $orderby ) && in_array(
			$orderby,
			[
				'post__in',
				'post_name__in',
				'post_parent__in',
			],
			true
		);

		if ( true === $orderby_should_not_convert_to_sql ) {
			return null;
		}

		$sql = $this->builder->to_sql();

		if ( empty( $sql ) ) {
			return null;
		}

		return ' AND ' . $sql;
	}

	/**
	 * @param string $name The name of the query var to get
	 *
	 * @return mixed|null
	 */
	public function get_query_var( string $name ) {
		return empty( $this->query_vars[ $name ] ) ? null : $this->query_vars[ $name ];
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
		if ( ! $this->get_cursor_post() ) {
			return '';
		}

		$orderby = $this->get_query_var( 'orderby' );
		$order   = $this->get_query_var( 'order' );

		if ( ! empty( $orderby ) && is_array( $orderby ) ) {

			/**
			 * Loop through all order keys if it is an array
			 */
			foreach ( $orderby as $by => $order ) {
				$this->compare_with( $by, $order );
			}
		} elseif ( ! empty( $orderby ) && is_string( $orderby ) ) {

			/**
			 * If $orderby is just a string just compare with it directly as DESC
			 */
			$this->compare_with( $orderby, $order );

		}

		/**
		 * No custom comparing. Use the default date
		 */
		if ( ! $this->builder->has_fields() ) {
			$this->compare_with_date();
		}

		$this->builder->add_field( "{$this->wpdb->posts}.ID", $this->cursor_offset, 'ID' );

		return $this->to_sql();
	}

	/**
	 * Use post date based comparison
	 *
	 * @return void
	 */
	private function compare_with_date() {
		$this->builder->add_field( "{$this->wpdb->posts}.post_date", $this->get_cursor_post()->post_date, 'DATETIME' );
	}

	/**
	 * Get AND operator for given order by key
	 *
	 * @param string $by    The order by key
	 * @param string $order The order direction ASC or DESC
	 *
	 * @return void
	 */
	private function compare_with( $by, $order ) {

		switch ( $by ) {
			case 'author':
			case 'title':
			case 'type':
			case 'name':
			case 'modified':
			case 'date':
			case 'parent':
				$by = 'post_' . $by;
				break;
		}

		$value = $this->get_cursor_post()->{$by};

		/**
		 * Compare by the post field if the key matches an value
		 */
		if ( ! empty( $value ) ) {

			$this->builder->add_field( "{$this->wpdb->posts}.{$by}", $value, null, $order );

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
	 * @param string $meta_key post meta key
	 * @param string $order    The comparison string
	 *
	 * @return void
	 */
	private function compare_with_meta_field( string $meta_key, string $order ) {
		$meta_type  = $this->get_query_var( 'meta_type' );
		$meta_value = get_post_meta( $this->cursor_offset, $meta_key, true );

		$key = "{$this->wpdb->postmeta}.meta_value";

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
	private function get_meta_key( $by ) {

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
