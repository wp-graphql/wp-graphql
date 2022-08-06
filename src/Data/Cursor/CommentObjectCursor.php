<?php

namespace WPGraphQL\Data\Cursor;

use WP_Comment;
use WP_Comment_Query;
use wpdb;
use function Symfony\Component\String\s;

/**
 * Comment Cursor
 *
 * This class generates the SQL and operators for cursor based pagination for comments
 */
class CommentObjectCursor {

	/**
	 * @var CursorBuilder
	 */
	public $builder;

	/**
	 * @var string
	 */
	public $compare;

	/**
	 * @var int
	 */
	public $cursor_offset;

	/**
	 * @var string|null
	 */
	public $cursor;

	/**
	 * @var WP_Comment_Query
	 */
	public $query;

	/**
	 * @var wpdb
	 */
	public $wpdb;

	/**
	 * @var array
	 */
	public $query_vars;

	/**
	 * @param WP_Comment_Query $query The instance of the WP_Comment_Query being executed
	 * @param string|null      $cursor Whether to generate the before or after cursor. Default "after"
	 *
	 * @return void
	 */
	public function __construct( WP_Comment_Query $query, $cursor = '' ) {

		global $wpdb;

		$this->wpdb       = $wpdb;
		$this->query      = $query;
		$this->query_vars = $this->query->query_vars;
		$this->cursor     = $cursor;

		$offset              = $this->get_query_var( 'graphql_cursor_offset' );
		$offset              = $this->get_query_var( 'graphql_' . $cursor . '_cursor' ) ?? $offset;
		$this->cursor_offset = ! empty( $offset ) ? absint( $offset ) : 0;

		$raw_compare   = $this->get_query_var( 'graphql_cursor_compare' ) ?: '>';
		$this->compare = in_array( $raw_compare, [ '>', '<' ], true ) ? $raw_compare : '>';

		if ( 'before' === $cursor ) {
			$this->compare = '>';
		} elseif ( 'after' === $cursor ) {
			$this->compare = '<';
		}

		$this->builder = new CursorBuilder( $this->compare );

	}

	/**
	 * Get the comment instance for the cursor
	 *
	 * This is cached internally so it should not generate additional queries
	 *
	 * @return false|WP_Comment|null
	 */
	public function get_cursor_node() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		return WP_Comment::get_instance( $this->cursor_offset );
	}

	/**
	 * @param string $name The name of the query var to get
	 *
	 * @return mixed|null
	 */
	public function get_query_var( string $name ) {
		return isset( $this->query->query_vars[ $name ] ) ? $this->query->query_vars[ $name ] : null;
	}

	/**
	 * Get AND operator for given order by key
	 *
	 * @param string $by    The order by key
	 * @param string $order The order direction ASC or DESC
	 *
	 * @return void
	 */
	public function compare_with( $by, $order ) {

		$type = null;

		if ( 'comment_date' === $by ) {
			$type = 'DATETIME';
		}

		$value = $this->get_cursor_node()->{$by} ?? null;
		if ( ! empty( $value ) ) {
			$this->builder->add_field( "{$this->wpdb->comments}.{$by}", $value, $type );
			return;
		}

	}

	/**
	 * Return the additional AND operators for the where statement
	 *
	 * @return string|null
	 */
	public function get_where() {

		if ( ! is_int( $this->cursor_offset ) || 0 >= $this->cursor_offset ) {
			return '';
		}

		if ( ! $this->get_cursor_node() ) {
			return '';
		}

		$orderby = $this->get_query_var( 'orderby' );
		$order   = $this->get_query_var( 'order' );

		// if there's custom ordering, use it to determine the cursor
		if ( ! empty( $orderby ) ) {
			$this->compare_with( $orderby, $order );
		}

		// if there's no specific orderby, then compare by date
		if ( ! $this->builder->has_fields() ) {
			$this->compare_with_date();
		}

		$this->builder->add_field( "{$this->wpdb->comments}.comment_ID", $this->cursor_offset, 'ID', null, $this );

		return $this->to_sql();

	}

	/**
	 * Use comment date based comparison
	 *
	 * @return void
	 */
	private function compare_with_date() {
		$this->builder->add_field( "{$this->wpdb->comments}.comment_date", $this->get_cursor_node()->comment_date ?? null, 'DATETIME' );
	}

	/**
	 * @return string
	 */
	private function to_sql() {
		$sql = $this->builder->to_sql();
		return ! empty( $sql ) ? ' AND ' . $sql : '';
	}

}
