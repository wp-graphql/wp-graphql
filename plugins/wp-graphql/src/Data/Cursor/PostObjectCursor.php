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
	 * @var int
	 */
	public $meta_join_alias = 0;

	/**
	 * {@inheritDoc}
	 */
	public function __construct( $query_vars, $cursor = 'after' ) {
		// @todo remove in 3.0.0
		if ( $query_vars instanceof \WP_Query ) {
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
		 * @param \WP_Post|null                           $pre_post The pre-filtered post node.
		 * @param int                                     $offset   The cursor offset.
		 * @param \WPGraphQL\Data\Cursor\PostObjectCursor $node     The cursor instance.
		 *
		 * @hookGroup connections
		 * @since 0.0.5
		 *
		 * @return \WP_Post|null
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

		/**
		 * When WP_Query applies its search relevance ordering, the relevance expression
		 * is the primary sort, ahead of the date/ID comparisons collected above, so the
		 * cursor cutoff must compare against it first or pages will drop and duplicate
		 * results whenever relevance order differs from date order.
		 *
		 * @see https://github.com/wp-graphql/wp-graphql/issues/1818
		 */
		$relevance = $this->get_search_relevance_compare_config();

		if ( null !== $relevance ) {
			$inner_sql = $this->builder->to_sql();

			if ( '' !== trim( $inner_sql ) ) {
				/**
				 * The multi-term CASE expression sorts ascending while the rest of the
				 * ordering (and the single-term boolean expression) sorts descending, so
				 * the CASE comparison is the inverse of the cursor's base compare. This
				 * holds for backward pagination too, where Config inverts the relevance
				 * expression in the ORDER BY (see graphql_wp_query_cursor_pagination_search_orderby).
				 */
				$compare = $relevance['ascending'] ? ( '<' === $this->compare ? '>' : '<' ) : $this->compare;

				// Mirrors CursorBuilder::to_sql() nesting: rows past the cursor either rank
				// differently, or rank the same and resolve via the date/ID comparisons.
				return ' AND ' . sprintf(
					' %1$s %2$s= %3$d AND ( %1$s %2$s %3$d OR (%4$s ) ) ',
					$relevance['expression'],
					$compare,
					$relevance['value'],
					$inner_sql
				);
			}
		}

		return $this->to_sql();
	}

	/**
	 * When WP_Query's native search relevance ordering is active for this query, returns
	 * the relevance expression (matching what WP_Query::parse_search_order() generates),
	 * the cursor node's computed rank, and the expression's sort direction. Returns null
	 * when relevance ordering is not in play.
	 *
	 * @since 2.17.0
	 *
	 * @return array{expression:string,value:int,ascending:bool}|null
	 */
	private function get_search_relevance_compare_config() {
		$search               = $this->get_query_var( 's' );
		$orderby              = $this->get_query_var( 'orderby' );
		$search_orderby_title = $this->get_query_var( 'search_orderby_title' );

		// Mirrors WP_Query: relevance ordering applies when searching, title ordering
		// clauses exist, and no other orderby is specified (or it is explicitly 'relevance').
		if ( empty( $search ) || ! is_string( $search ) || empty( $search_orderby_title ) || ! is_array( $search_orderby_title ) ) {
			return null;
		}

		if ( ! empty( $orderby ) && 'relevance' !== $orderby ) {
			return null;
		}

		$node = $this->cursor_node;

		if ( ! $node instanceof \WP_Post ) {
			return null;
		}

		$terms_count = absint( $this->get_query_var( 'search_terms_count' ) ?? 1 );

		if ( $terms_count > 1 ) {
			$expression = $this->get_multi_term_relevance_expression( $search, $search_orderby_title );

			if ( '' === $expression ) {
				return null;
			}

			return [
				'expression' => $expression,
				'value'      => $this->get_multi_term_relevance_rank( $node, $search, $search_orderby_title ),
				'ascending'  => true,
			];
		}

		// Single word or sentence search: the expression is a boolean title match, sorted DESC.
		$exact   = (bool) $this->get_query_var( 'exact' );
		$matches = $exact ? 0 === strcasecmp( $node->post_title, $search ) : false !== stripos( $node->post_title, $search );

		return [
			'expression' => '(' . reset( $search_orderby_title ) . ')',
			'value'      => $matches ? 1 : 0,
			'ascending'  => false,
		];
	}

	/**
	 * Builds the same CASE expression WP_Query::parse_search_order() generates for
	 * multi-term searches, reusing the prepared title LIKE clauses WP_Query stored in
	 * the query vars during parse_search().
	 *
	 * @since 2.17.0
	 *
	 * @param string   $search               The raw search string (`s` query var).
	 * @param string[] $search_orderby_title The prepared post_title LIKE clauses from WP_Query.
	 */
	private function get_multi_term_relevance_expression( string $search, array $search_orderby_title ): string {
		$num_terms = count( $search_orderby_title );

		// If the search terms contain negative queries, WP doesn't order by sentence matches.
		$like = '';
		if ( ! preg_match( '/(?:\s|^)\-/', $search ) ) {
			$like = "'%" . esc_sql( $this->wpdb->esc_like( $search ) ) . "%'";
		}

		$expression = '';

		// Sentence match in 'post_title'.
		if ( $like ) {
			$expression .= "WHEN {$this->wpdb->posts}.post_title LIKE {$like} THEN 1 "; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// Sanity limit, sort as sentence when more than 6 terms.
		if ( $num_terms < 7 ) {
			// All words in title.
			$expression .= 'WHEN ' . implode( ' AND ', $search_orderby_title ) . ' THEN 2 ';
			// Any word in title, not needed when $num_terms == 1.
			if ( $num_terms > 1 ) {
				$expression .= 'WHEN ' . implode( ' OR ', $search_orderby_title ) . ' THEN 3 ';
			}
		}

		// Sentence match in 'post_content' and 'post_excerpt'.
		if ( $like ) {
			$expression .= "WHEN {$this->wpdb->posts}.post_excerpt LIKE {$like} THEN 4 "; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$expression .= "WHEN {$this->wpdb->posts}.post_content LIKE {$like} THEN 5 "; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return '' !== $expression ? '(CASE ' . $expression . 'ELSE 6 END)' : '';
	}

	/**
	 * Computes the cursor node's rank under the multi-term relevance CASE expression,
	 * evaluating the same conditions in the same order as the SQL.
	 *
	 * @since 2.17.0
	 *
	 * @param \WP_Post $node                 The cursor node post.
	 * @param string   $search               The raw search string (`s` query var).
	 * @param string[] $search_orderby_title The prepared post_title LIKE clauses from WP_Query.
	 */
	private function get_multi_term_relevance_rank( \WP_Post $node, string $search, array $search_orderby_title ): int {
		$num_terms = count( $search_orderby_title );

		$sentence_match_applies = ! preg_match( '/(?:\s|^)\-/', $search );

		// Sentence match in 'post_title'.
		if ( $sentence_match_applies && false !== stripos( $node->post_title, $search ) ) {
			return 1;
		}

		if ( $num_terms < 7 ) {
			$terms = $this->get_query_var( 'search_terms' );
			$terms = is_array( $terms ) ? array_values(
				array_filter(
					$terms,
					static function ( $term ) {
						return is_string( $term ) && '' !== $term && '-' !== $term[0];
					}
				)
			) : [];

			if ( ! empty( $terms ) ) {
				$matched = 0;
				foreach ( $terms as $term ) {
					if ( false !== stripos( $node->post_title, $term ) ) {
						++$matched;
					}
				}

				// All words in title.
				if ( count( $terms ) === $matched ) {
					return 2;
				}

				// Any word in title.
				if ( $num_terms > 1 && $matched > 0 ) {
					return 3;
				}
			}
		}

		// Sentence match in 'post_excerpt' and 'post_content'.
		if ( $sentence_match_applies && false !== stripos( $node->post_excerpt, $search ) ) {
			return 4;
		}

		if ( $sentence_match_applies && false !== stripos( $node->post_content, $search ) ) {
			return 5;
		}

		return 6;
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
	 */
	private function compare_with_meta_field( string $meta_key, string $order ): void {
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
		 *
		 * @hookGroup connections
		 * @since 0.0.5
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

	/**
	 * @todo Remove in 3.0.0
	 * @deprecated 1.9.0
	 * @codeCoverageIgnore
	 *
	 * @return ?\WP_Post
	 */
	public function get_cursor_post() {
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
