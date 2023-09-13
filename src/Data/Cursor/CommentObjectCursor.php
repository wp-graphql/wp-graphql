<?php

namespace WPGraphQL\Data\Cursor;

use WP_Comment;

/**
 * Comment Cursor
 *
 * This class generates the SQL and operators for cursor based pagination for comments
 *
 * @package WPGraphQL\Data\Cursor
 */
class CommentObjectCursor extends AbstractCursor {

	/**
	 * @var ?\WP_Comment
	 */
	public $cursor_node;

	/**
	 * @param array|\WP_Comment_Query $query_vars The query vars to use when building the SQL statement.
	 * @param string|null            $cursor Whether to generate the before or after cursor. Default "after"
	 *
	 * @return void
	 */
	public function __construct( $query_vars, $cursor = 'after' ) {
		// Handle deprecated use of $query.
		if ( $query_vars instanceof \WP_Comment_Query ) {
			_doing_it_wrong( __METHOD__, 'The first argument should be an array of $query_vars, not the WP_Comment_Query object', '1.9.0' );
			$query_vars = $query_vars->query_vars;
		}

		// Initialize the class properties.
		parent::__construct( $query_vars, $cursor );

		// Set ID Key.
		$this->id_key = "{$this->wpdb->comments}.comment_ID";
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return ?\WP_Comment
	 */
	public function get_cursor_node() {
		// Bail if no offset.
		if ( ! $this->cursor_offset ) {
			return null;
		}

		/**
		 * If pre-hooked, return filtered node.
		 *
		 * @param null|\WP_Comment                           $pre_comment The pre-filtered comment node.
		 * @param int                                        $offset      The cursor offset.
		 * @param \WPGraphQL\Data\Cursor\CommentObjectCursor $node        The cursor instance.
		 *
		 * @return null|\WP_Comment
		 */
		$pre_comment = apply_filters( 'graphql_pre_comment_cursor_node', null, $this->cursor_offset, $this );
		if ( null !== $pre_comment ) {
			return $pre_comment;
		}

		// Get cursor node.
		$comment = WP_Comment::get_instance( $this->cursor_offset );

		return false !== $comment ? $comment : null;
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

		// if there's custom ordering, use it to determine the cursor
		if ( ! empty( $orderby ) ) {
			$this->compare_with( $orderby, $order );
		}

		/**
		 * If there's no orderby specified yet, compare with the following fields.
		 */
		if ( ! $this->builder->has_fields() ) {
			$this->compare_with_cursor_fields(
				[
					[
						'key'  => "{$this->wpdb->comments}.comment_date",
						'by'   => $this->cursor_node ? $this->cursor_node->comment_date : null,
						'type' => 'DATETIME',
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
	 *
	 * @return void
	 */
	public function compare_with( $by, $order ) {
		// Bail early, if "key" and "value" provided in query_vars.
		$key   = $this->get_query_var( "graphql_cursor_compare_by_{$by}_key" );
		$value = $this->get_query_var( "graphql_cursor_compare_by_{$by}_value" );
		if ( ! empty( $key ) && ! empty( $value ) ) {
			$this->builder->add_field( $key, $value, null, $order );
			return;
		}

		$key   = "{$this->wpdb->comments}.{$by}";
		$value = $this->cursor_node->{$by};
		$type  = null;

		if ( 'comment_date' === $by ) {
			$type = 'DATETIME';
		}

		$value = $this->cursor_node->{$by} ?? null;
		if ( ! empty( $value ) ) {
			$this->builder->add_field( $key, $value, $type );
			return;
		}
	}

	/**
	 *{@inheritDoc}
	 */
	public function to_sql() {
		$sql = $this->builder->to_sql();
		return ! empty( $sql ) ? ' AND ' . $sql : '';
	}
}
