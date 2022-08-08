<?php

namespace WPGraphQL\Data\Cursor;

/**
 * Post Cursor
 *
 * This class generates the SQL AND operators for cursor based pagination for posts
 *
 * @package WPGraphQL\Data\Cursor
 */
class PostObjectCursor extends AbstractCursor {
	/**
	 * @var ?\WP_Post
	 */
	public $cursor_node;

	/**
	 * Counter for meta value joins
	 *
	 * @var integer
	 */
	public $meta_join_alias = 0;

	/**
	 * PostCursor constructor.
	 *
	 * @param array|\WP_Query $query_vars The query vars to use when building the SQL statement.
	 * @param string|null     $cursor Whether to generate the before or after cursor. Default "after"
	 */
	public function __construct( $query_vars, $cursor = 'after' ) {
		// Handle deprecated use of $query.
		if ( $query_vars instanceof \WP_Query ) {
			_doing_it_wrong( __FUNCTION__, 'The first argument should be an array of $query_vars, not the WP_Query object', '1.9.0' );
			$query_vars = $query_vars->query_vars;
		}

		// Initialize the class properties.
		parent::__construct( $query_vars, $cursor );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return ?\WP_Post
	 */
	public function get_cursor_node() {
		if ( ! $this->cursor_offset ) {
			return null;
		}

		$post = \WP_Post::get_instance( $this->cursor_offset );

		return false !== $post ? $post : null;
	}

	/**
	 * @deprecated 1.9.0
	 *
	 * @return ?\WP_Post
	 */
	public function get_cursor_post() {
		_deprecated_function( __FUNCTION__, '1.9.0', self::class . '::get_cursor_node()' );

		return $this->cursor_node;
	}

	/**
	 * {@inheritDoc}
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
			return '';
		}

		$sql = $this->builder->to_sql();

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

		if ( 'menu_order' === $orderby ) {
			if ( '>' === $this->compare ) {

				$order         = 'DESC';
				$this->compare = '<';
			} elseif ( '<' === $this->compare ) {
				$this->compare = '>';
				$order         = 'ASC';
			}
		}

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

		$this->builder->add_field( "{$this->wpdb->posts}.ID", $this->cursor_offset, 'ID', $order );

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

		$value = $this->cursor_node->{$by} ?? null;

		/**
		 * Compare by the post field if the key matches a value
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
		}

	}

	/**
	 * Use post date based comparison
	 *
	 * @return void
	 */
	private function compare_with_date() {
		$this->builder->add_field( "{$this->wpdb->posts}.post_date", $this->cursor_node->post_date ?? null, 'DATETIME' );
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
