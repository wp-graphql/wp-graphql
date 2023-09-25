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
			_doing_it_wrong( __METHOD__, 'The first argument should be an array of $query_vars, not the WP_Query object', '1.9.0' );
			$query_vars = $query_vars->query_vars;
		}

		// Initialize the class properties.
		parent::__construct( $query_vars, $cursor );

		// Set ID key.
		$this->id_key = "{$this->wpdb->posts}.ID";
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return ?\WP_Post
	 */
	public function get_cursor_node() {
		// Bail if no offset.
		if ( ! $this->cursor_offset ) {
			return null;
		}

		/**
		 * If pre-hooked, return filtered node.
		 *
		 * @param null|\WP_Post                           $pre_post The pre-filtered post node.
		 * @param int                                     $offset   The cursor offset.
		 * @param \WPGraphQL\Data\Cursor\PostObjectCursor $node     The cursor instance.
		 *
		 * @return null|\WP_Post
		 */
		$pre_post = apply_filters( 'graphql_pre_post_cursor_node', null, $this->cursor_offset, $this );
		if ( null !== $pre_post ) {
			return $pre_post;
		}

		// Get cursor node.
		$post = \WP_Post::get_instance( $this->cursor_offset );

		return false !== $post ? $post : null;
	}

	/**
	 * @deprecated 1.9.0
	 *
	 * @return ?\WP_Post
	 */
	public function get_cursor_post() {
		_deprecated_function( __METHOD__, '1.9.0', self::class . '::get_cursor_node()' );

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
		 * If there's no orderby specified yet, compare with the following fields.
		 */
		if ( ! $this->builder->has_fields() ) {
			$this->compare_with_cursor_fields(
				[
					[
						'key'   => "{$this->wpdb->posts}.post_date",
						'value' => $this->cursor_node ? $this->cursor_node->post_date : null,
						'type'  => 'DATETIME',
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
	private function compare_with( $by, $order ) {
		// Bail early, if "key" and "value" provided in query_vars.
		$key   = $this->get_query_var( "graphql_cursor_compare_by_{$by}_key" );
		$value = $this->get_query_var( "graphql_cursor_compare_by_{$by}_value" );
		if ( ! empty( $key ) && ! empty( $value ) ) {
			$this->builder->add_field( $key, $value, null, $order );
			return;
		}

		/**
		 * Find out whether this is a post field
		 */
		$orderby_post_fields = [
			'post_author',
			'post_title',
			'post_type',
			'post_name',
			'post_modified',
			'post_date',
			'post_parent',
			'menu_order',
		];
		if ( in_array( $by, $orderby_post_fields, true ) ) {
			$key   = "{$this->wpdb->posts}.{$by}";
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
		$meta_query = $this->get_query_var( 'meta_query' );
		if ( ! empty( $meta_query ) && is_array( $meta_query ) ) {
			if ( ! empty( $meta_query['relation'] ) ) {
				unset( $meta_query['relation'] );
			}

			$meta_keys = array_column( $meta_query, 'key' );
			$index     = array_search( $meta_key, $meta_keys, true );

			if ( $index && 1 < count( $meta_query ) ) {
				$key = "mt{$index}.meta_value";
			}
		}

		/**
		 * Allow filtering the meta key used for cursor based pagination
		 *
		 * @param string $key       The meta key to use for cursor based pagination
		 * @param string $meta_key  The original meta key
		 * @param string $meta_type The meta type
		 * @param string $order     The order direction
		 * @param object $cursor    The PostObjectCursor instance
		 */
		$key = apply_filters( 'graphql_post_object_cursor_meta_key', $key, $meta_key, $meta_type, $order, $this );

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
