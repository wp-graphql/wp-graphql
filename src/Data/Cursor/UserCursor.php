<?php

namespace WPGraphQL\Data\Cursor;

use WP_User;
use WP_User_Query;

/**
 * User Cursor
 *
 * This class generates the SQL AND operators for cursor based pagination for users
 *
 * @package WPGraphQL\Data\Cursor
 */
class UserCursor extends AbstractCursor {

	/**
	 * @var ?WP_User
	 */
	public $cursor_node;

	/**
	 * Counter for meta value joins
	 *
	 * @var integer
	 */
	public $meta_join_alias = 0;

	/**
	 * UserCursor constructor.
	 *
	 * @param array|WP_User_Query $query_vars The query vars to use when building the SQL statement.
	 * @param string|null         $cursor     Whether to generate the before or after cursor
	 *
	 * @return void
	 */
	public function __construct( $query_vars, $cursor = 'after' ) {
		// Handle deprecated use of $query.
		if ( $query_vars instanceof WP_User_Query ) {
			_doing_it_wrong( __FUNCTION__, 'The first argument should be an array of $query_vars, not the WP_Query object', '1.9.0' );
			$query_vars = $query_vars->query_vars;
		}

		// Initialize the class properties.
		parent::__construct( $query_vars, $cursor );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Unlike most queries, users by default are in ascending order.
	 */
	public function get_cursor_compare() {
		if ( 'before' === $this->cursor ) {
			return '<';
		}
		return '>';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return ?WP_User
	 */
	public function get_cursor_node() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		$user = get_user_by( 'id', $this->cursor_offset );

		return false !== $user ? $user : null;
	}

	/**
	 * @return ?WP_User
	 * @deprecated 1.9.0
	 */
	public function get_cursor_user() {
		_deprecated_function( __METHOD__, '1.9.0', self::class . '::get_cursor_node()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return $this->cursor_node;
	}

	/**
	 * {@inheritDoc}
	 */
	public function to_sql() {
		return ' AND ' . $this->builder->to_sql();
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

		if ( ! empty( $orderby ) && is_array( $orderby ) ) {

			/**
			 * Loop through all order keys if it is an array
			 */
			foreach ( $orderby as $by => $order ) {
				$this->compare_with( $by, $order );
			}
		} elseif ( ! empty( $orderby ) && is_string( $orderby ) && 'login' !== $orderby ) {

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

		$this->builder->add_field( "{$this->wpdb->users}.ID", (string) $this->cursor_offset, 'ID', $order );

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

		$value = $this->cursor_node->{$by} ?? null;

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
	 * Use user login based comparison
	 *
	 * @return void
	 */
	private function compare_with_login() {
		$this->builder->add_field( "{$this->wpdb->users}.user_login", $this->cursor_node->user_login ?? null, 'CHAR' );
	}

	/**
	 * Compare with meta key field
	 *
	 * @param string $meta_key user meta key
	 * @param string $order    The comparison string
	 *
	 * @return void
	 */
	private function compare_with_meta_field( string $meta_key, string $order ) {
		$meta_type  = $this->get_query_var( 'meta_type' );
		$meta_value = get_user_meta( $this->cursor_offset, $meta_key, true );

		$key = "{$this->wpdb->usermeta}.meta_value";

		/**
		 * WP uses mt1, mt2 etc. style aliases for additional meta value joins.
		 */
		if ( 0 !== $this->meta_join_alias ) {
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
