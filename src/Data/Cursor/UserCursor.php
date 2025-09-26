<?php

namespace WPGraphQL\Data\Cursor;

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
	 * @var ?\WP_User
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
	 * @param array<string,mixed>|\WP_User_Query $query_vars The query vars to use when building the SQL statement.
	 */
	public function __construct( $query_vars, $cursor = 'after' ) {
		// @todo remove in 3.0.0
		if ( $query_vars instanceof WP_User_Query ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The first argument should be an array of $query_vars, not the WP_Query object. This will throw an error in the next major release', 'wp-graphql' ),
				'1.9.0'
			);
			$query_vars = $query_vars->query_vars;
		}

		// Initialize the class properties.
		parent::__construct( $query_vars, $cursor );

		// Set ID key.
		$this->id_key = "{$this->wpdb->users}.ID";
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
	 * @return ?\WP_User
	 */
	public function get_cursor_node() {
		// Bail if no offset.
		if ( ! $this->cursor_offset ) {
			return null;
		}

		/**
		 * If pre-hooked, return filtered node.
		 *
		 * @param \WP_User|null                        $pre_user The pre-filtered user node.
		 * @param int                                  $offset   The cursor offset.
		 * @param \WPGraphQL\Data\Cursor\UserCursor    $node     The cursor instance.
		 *
		 * @return \WP_User|null
		 */
		$pre_user = apply_filters( 'graphql_pre_user_cursor_node', null, $this->cursor_offset, $this );
		if ( null !== $pre_user ) {
			return $pre_user;
		}

		// Get cursor node.
		$user = get_user_by( 'id', $this->cursor_offset );

		return false !== $user ? $user : null;
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
		 * If there's no orderby specified yet, compare with the following fields.
		 */
		if ( ! $this->builder->has_fields() ) {
			$this->compare_with_cursor_fields(
				[
					[
						'key'   => "{$this->wpdb->users}.user_login",
						'value' => $this->cursor_node ? $this->cursor_node->user_login : null,
						'type'  => 'CHAR',
					],
				]
			);
		}

		$this->compare_with_id_field();

		return $this->to_sql();
	}

	/**
	 * Get AND operator for given order by key
	 *
	 * @param string $by    The order by key
	 * @param string $order The order direction ASC or DESC
	 */
	private function compare_with( $by, $order ): void {
		// Bail early, if "key" and "value" provided in query_vars.
		$key   = $this->get_query_var( "graphql_cursor_compare_by_{$by}_key" );
		$value = $this->get_query_var( "graphql_cursor_compare_by_{$by}_value" );
		if ( ! empty( $key ) && ! empty( $value ) ) {
			$this->builder->add_field( $key, $value, null, $order );
			return;
		}

		/**
		 * Find out whether this is a user field
		 */
		$orderby_user_fields = [
			'user_email',
			'user_login',
			'user_nicename',
			'user_registered',
			'user_url',
		];
		if ( in_array( $by, $orderby_user_fields, true ) ) {
			$key   = "{$this->wpdb->users}.{$by}";
			$value = $this->cursor_node->{$by} ?? null;
		}

		/**
		 * If key or value are null, check whether this is a meta key based ordering before bailing.
		 */
		if ( null === $key || null === $value ) {
			$meta_key = $this->get_meta_key( $by );
			if ( $meta_key ) {
				$this->compare_with_meta_field( $meta_key, $order );
			}
			return;
		}

		// Add field to build.
		$this->builder->add_field( $key, $value, null, $order );
	}

	/**
	 * Compare with meta key field
	 *
	 * @param string $meta_key user meta key
	 * @param string $order    The comparison string
	 */
	private function compare_with_meta_field( string $meta_key, string $order ): void {
		$meta_type  = $this->get_query_var( 'meta_type' );
		$meta_value = get_user_meta( $this->cursor_offset, $meta_key, true );

		$key = "{$this->wpdb->usermeta}.meta_value";

		/**
		 * WP uses mt1, mt2 etc. style aliases for additional meta value joins.
		 */
		if ( 0 !== $this->meta_join_alias ) {
			$key = "mt{$this->meta_join_alias}.meta_value";
		}

		++$this->meta_join_alias;

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

	/**
	 * @todo remove in 3.0.0
	 * @deprecated 1.9.0
	 * @codeCoverageIgnore
	 *
	 * @return ?\WP_User
	 */
	public function get_cursor_user() {
		_doing_it_wrong(
			__METHOD__,
			sprintf(
				// translators: %s is the method name
				esc_html__( 'This method will be removed in the next major release. Use %s instead.', 'wp-graphql' ),
				self::class . '::get_cursor_node()'
			),
			'1.9.0'
		);

		return $this->cursor_node;
	}
}
