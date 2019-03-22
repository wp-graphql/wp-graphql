<?php

namespace WPGraphQL\Data;

/**
 * Post Cursor
 *
 * This class generates the SQL AND operators for cursor based pagination for posts
 *
 * @package WPGraphQL\Data
 */
class PostObjectCursor {

	/**
	 * The global wpdb instance
	 *
	 * @var $wpdb
	 */
	public $wpdb;

	/**
	 * The WP_Query instance
	 *
	 * @var $query
	 */
	public $query;

	/**
	 * The current post id which is our cursor offset
	 *
	 * @var $post_type
	 */
	public $cursor_offset;

	/**
	 * The current post instance
	 *
	 * @var $compare
	 */
	public $cursor_post;

	/**
	 * @var \WPGraphQL\Data\CursorBuilder
	 */
	public $builder;

	public $meta_join_alias = 0;

	/**
	 * PostCursor constructor.
	 *
	 * @param integer $cursor_offset the post id
	 * @param \WP_Query $query The WP_Query instance
	 */
	public function __construct( $cursor_offset, $query ) {
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->cursor_offset = $cursor_offset;
		$this->query = $query;

		$compare = ! empty( $query->get( 'graphql_cursor_compare' ) ) ? $query->get( 'graphql_cursor_compare' ) : '>';
		$compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

		$this->builder = new CursorBuilder( $compare );

		// Get the $cursor_post
		$this->cursor_post = get_post( $cursor_offset );
	}

	public function to_sql() {
		return ' AND ' . $this->builder->to_sql();
	}

	/**
	 * Return the additional AND operators for the where statement
	 */
	public function get_where() {

		/**
		 * If we have no cursor just compare with post_date like wp core
		 */
		if ( ! $this->cursor_post ) {
			$this->compare_with_date();
			return $this->to_sql();
		}

		$orderby = $this->query->get( 'orderby' );
		$order = $this->query->get( 'order' );

		if ( ! empty( $orderby ) && is_array( $orderby ) ) {
			/**
			 * Loop through all order keys if it is an array
			 */
			foreach ( $orderby as $by => $order ) {
				$this->compare_with( $by, $order );
			}
		} else if ( ! empty( $orderby ) && is_string( $orderby ) ) {
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

		return $this->to_sql();
	}

	/**
	 * Use post date based comparison
	 */
	private function compare_with_date() {
		$this->builder->add_field( "{$this->wpdb->posts}.post_date", $this->cursor_post->post_date, 'DATE' );
		$this->builder->add_field( "{$this->wpdb->posts}.ID", $this->cursor_offset, 'ID' );
	}

	/**
	 * Get AND operator for given order by key
	 *
	 * @param string    $by The order by key
	 * @param string    $order The order direction ASC or DESC
	 *
	 * @return string
	 */
	private function compare_with( $by, $order ) {

		$post_field = 'post_' . $by;
		$value = $this->cursor_post->{$post_field};

		/**
		 * Compare by the post field if the key matches an value
		 */
		if ( ! empty( $value ) ) {
			$this->builder->add_field( "{$this->wpdb->posts}.post_{$by}", $value, null, $order );
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
	 * @param string    $meta_key post meta key
	 * @param string    $order_compare The comparison string
	 *
	 * @return string
	 */
	private function compare_with_meta_field( $meta_key, $order ) {
		$meta_type = ! empty( $this->query->query_vars["meta_type"] ) ? esc_sql( $this->query->query_vars["meta_type"] ) : null;
		$meta_value = esc_sql( get_post_meta( $this->cursor_offset, $meta_key, true ) );

		$key = "{$this->wpdb->postmeta}.meta_value";

		/**
		 * wp uses mt1, mt2 etc. style aliases for additional meta value joins.
		 */
		if ( $this->meta_join_alias !== 0 ) {
			$key = "mt{$this->meta_join_alias}.meta_value";

		}

		$this->meta_join_alias++;

		$this->builder->add_field($key , $meta_value, $meta_type, $order );
	}

	/**
	 * Get the actual meta key if any
	 *
	 * @param string    $by The order by key
	 *
	 * @return string|null
	 */
	private function get_meta_key( $by ) {

		if ( 'meta_value' === $by ) {
			return ! empty( $this->query->query_vars["meta_key"] ) ? esc_sql( $this->query->query_vars["meta_key"] ) : null;
		}

		/**
		 * Check for the WP 4.2+ style meta clauses
		 * https://make.wordpress.org/core/2015/03/30/query-improvements-in-wp-4-2-orderby-and-meta_query/
		 */
		if ( ! isset( $this->query->query_vars['meta_query'][ $by ] ) ) {
			return null;
		}

		$clause = $this->query->query_vars["meta_query"][ $by ];

		return empty( $clause['key'] ) ? null : $clause['key'];
	}

}