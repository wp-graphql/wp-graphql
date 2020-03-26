<?php

namespace WPGraphQL\Data\Cursor;

/**
 * User Cursor
 *
 * This class generates the SQL AND operators for cursor based pagination for users
 *
 * @package WPGraphQL\Data
 */
class UserCursor {

	/**
	 * The global wpdb instance
	 *
	 * @var $wpdb
	 */
	public $wpdb;

	/**
	 * The WP_User_Query instance
	 *
	 * @var $query
	 */
	public $query;

	/**
	 * The current user id which is our cursor offset
	 *
	 * @var $user
	 */
	public $cursor_offset;

	/**
	 * @var \WPGraphQL\Data\Cursor\CursorBuilder
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
	 */
	public $query_vars = null;

	/**
	 * UserCursor constructor.
	 *
	 * @param \WP_User_Query $query The WP_User_Query instance
	 */
	public function __construct( $query ) {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->query      = $query;
		$this->query_vars = $this->query->query_vars;

		/**
		 * Get the cursor offset if any
		 */
		$offset              = $this->get_query_var( 'graphql_cursor_offset' );
		$this->cursor_offset = ! empty( $offset ) ? $offset : 0;

		/**
		 * Get the direction for the query builder
		 */
		$compare = ! empty( $query->get( 'graphql_cursor_compare' ) ) ? $query->get( 'graphql_cursor_compare' ) : '>';
		$compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '>';

		$this->builder = new CursorBuilder( $compare );

	}

	/**
	 * Get user instance for the cursor.
	 *
	 * This is cached internally so it does not generate extra queries
	 *
	 * @return mixed WP_User|null
	 */
	public function get_cursor_user() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		return get_user_by( 'id', $this->cursor_offset );
	}

	/**
	 * Generate the final SQL string to be appended to WHERE clause
	 *
	 * @return string
	 */
	public function to_sql() {
		return ' AND ' . $this->builder->to_sql();
	}

	/**
	 * Get current WP_User_Query instance's query variables.
	 *
	 * @return mixed array|null
	 */
	public function get_query_var( $name ) {
		return empty( $this->query_vars[ $name ] ) ? null : $this->query_vars[ $name ];
	}

	/**
	 * Return the additional AND operators for the where statement
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
		if ( ! $this->get_cursor_user() ) {
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
		 * No custom comparing. Order by login
		 */
		if ( ! $this->builder->has_fields() ) {
			$this->compare_with_login();
		}

		$this->builder->add_field( "{$this->wpdb->users}.ID", $this->cursor_offset, 'ID' );

		return $this->to_sql();
	}

	/**
	 * Use user login based comparison
	 */
	private function compare_with_login() {
		$this->builder->add_field( "{$this->wpdb->users}.user_login", $this->get_cursor_user()->user_login, 'CHAR' );
	}

	/**
	 * Get AND operator for given order by key
	 *
	 * @param string $by    The order by key
	 * @param string $order The order direction ASC or DESC
	 *
	 * @return string
	 */
	private function compare_with( $by, $order ) {

		switch ( $by ) {
			case 'email':
			case 'login':
			case 'nicename':
			case 'registered':
			case 'url':
				$by = 'user_' . $by;
				break;
		}

		$value = $this->get_cursor_user()->{$by};

		/**
		 * Compare by the user field if the key matches a value
		 */
		if ( ! empty( $value ) ) {
			$this->builder->add_field( "{$this->wpdb->users}.{$by}", $value, null, $order );

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
	 * @param string $meta_key user meta key
	 * @param string $order    The comparison string
	 *
	 * @return string
	 */
	private function compare_with_meta_field( $meta_key, $order ) {
		$meta_type  = $this->get_query_var( 'meta_type' );
		$meta_value = get_user_meta( $this->cursor_offset, $meta_key, true );

		$key = "{$this->wpdb->usermeta}.meta_value";

		/**
		 * wp uses mt1, mt2 etc. style aliases for additional meta value joins.
		 */
		if ( $this->meta_join_alias !== 0 ) {
			$key = "mt{$this->meta_join_alias}.meta_value";

		}

		$this->meta_join_alias ++;

		$this->builder->add_field( $key, $meta_value, $meta_type, $order );
	}

	/**
	 * Get the actual meta key if any
	 *
	 * @param string $by The order by key
	 *
	 * @return string|null
	 */
	private function get_meta_key( $by ) {

		if ( 'meta_value' === $by ) {
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
